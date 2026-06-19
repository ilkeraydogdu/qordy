<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class SystemSettingsRepository extends BaseRepository {
    protected $table = 'system_settings';
    protected $primaryKey = 'id';

    /** @var string|null Last error from setValue (for debugging) */
    public static $lastSetValueError = null;

    /**
     * Keys that are considered "platform wide" and safe to read from a
     * tenant-less (NULL tenant_id) row when no tenant context exists.
     * Tenant-specific keys (addresses, coordinates, WiFi, Meta/WhatsApp
     * credentials, SMTP, Iyzico, working hours, etc.) must NEVER fall
     * back to another tenant's row or the first row.
     */
    private const GLOBAL_SAFE_KEYS = [
        'site_name', 'logo_url', 'favicon_url',
        'currency', 'app_env', 'app_debug', 'timezone', 'default_language',
        'session_timeout', 'session_lifetime', 'session_secure_cookie', 'session_http_only', 'session_same_site',
        'max_upload_size', 'supported_languages', 'language_switcher_enabled', 'auto_detect_language',
        'log_level', 'csrf_protection', 'rate_limiting',
        'enable_analytics', 'enable_notifications', 'enable_multi_language',
        'websocket_port',
        // Platform-wide payment gateway toggles (managed by super-admin).
        'payment_bank_transfer_enabled',
        // Meta API credentials + platform-wide queue template name are owned
        // by the super admin and live in the tenant-less settings row.
        'meta_app_id', 'meta_app_secret', 'meta_access_token',
        'meta_webhook_verify_token', 'meta_phone_number_id',
        'meta_whatsapp_business_account_id',
        'meta_queue_template_name',
        // Platform-wide feature toggle: whether businesses may use the
        // shared Meta Cloud API account to send queue-position messages
        // to their customers. Lives on the tenant-less settings row.
        'meta_queue_messaging_enabled',
        // Platform-wide WhatsApp/Meta usage limits.
        'whatsapp_daily_limit', 'whatsapp_monthly_limit',
        // SMTP transport config + "from" identity is global, not tenant-scoped.
        'smtp_host', 'smtp_port', 'smtp_encryption',
        'smtp_username', 'smtp_password', 'smtp_from_name',
        // Welcome notification toggles are global (super-admin controlled).
        'welcome_email_enabled', 'welcome_whatsapp_enabled',
        // Two-factor auth method toggles are platform-wide.
        'auth_2fa_totp_enabled', 'auth_2fa_whatsapp_enabled',
        'auth_2fa_email_enabled', 'auth_2fa_sms_enabled',
        // Gemini / AI API key is a single platform credential.
        'gemini_api_key',
        'opusmax_api_key',
    ];

    public function __construct($database) {
        parent::__construct($database);
    }

    private function getTenantBusinessId(): ?string {
        $id = $_SESSION['business_id'] ?? ($_SESSION['customer_id'] ?? null);
        if (empty($id) && class_exists('\App\Core\TenantContext')) {
            $id = \App\Core\TenantContext::getId();
        }
        return $id ?: null;
    }

    public function getByKey(string $key): ?string {
        // Whitelist of allowed column names for security
        $allowedColumns = [
            'site_name', 'logo_url', 'favicon_url', 'service_charge_rate', 'cover_charge',
            'currency', 'gemini_api_key', 'opusmax_api_key', 'app_env', 'app_debug', 'timezone', 'default_language',
            'session_timeout', 'session_lifetime', 'session_secure_cookie', 'session_http_only', 'session_same_site',
            'max_upload_size', 'supported_languages', 'language_switcher_enabled',
            'auto_detect_language', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username',
            'smtp_password', 'smtp_from_name', 'require_2fa',
            'auth_2fa_totp_enabled', 'auth_2fa_whatsapp_enabled',
            'auth_2fa_email_enabled', 'auth_2fa_sms_enabled',
            'log_level', 'csrf_protection', 'rate_limiting', 'enable_analytics', 'enable_notifications',
            'enable_multi_language', 'websocket_port', 'order_id_prefix', 'order_number_length',
            'wifi_name', 'wifi_password', 'wifi_show_to_customer',
            'working_hours_enabled', 'working_hours_start', 'working_hours_end', 'working_hours_days',
            'waiter_delete_requires_approval', 'order_edit_requires_approval', 'order_edit_approval_role',
            'staff_show_delete_reduce_buttons', 'manager_show_delete_reduce_buttons',
            'business_latitude', 'business_longitude', 'business_radius', 'business_address',
            'meta_app_id', 'meta_app_secret', 'meta_access_token', 'meta_webhook_verify_token', 'meta_phone_number_id', 'meta_whatsapp_business_account_id',
            'meta_queue_template_name', 'meta_queue_messaging_enabled',
            'whatsapp_daily_limit', 'whatsapp_monthly_limit',
            'payment_bank_transfer_enabled',
            'welcome_email_enabled', 'welcome_whatsapp_enabled'
        ];
        
        $businessId = $this->getTenantBusinessId();
        
        // Check if setting_key column exists
        try {
            $hasSettingKey = \App\Core\DbSchema::hasColumn($this->table, 'setting_key');

            if ($hasSettingKey) {
                // Key-value mode: legacy single-row fallback is acceptable here
                // because this code path expects tenant-less global settings.
                $sql = "SELECT setting_value FROM {$this->table} WHERE setting_key = :key LIMIT 1";
                $result = $this->fetchOne($sql, ['key' => $key]);
                return $result['setting_value'] ?? null;
            } else {
                if (!in_array($key, $allowedColumns)) {
                    return null;
                }
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                    return null;
                }

                // Platform geneli (GLOBAL_SAFE_KEYS) — giriş yapmış işletme müşterisi
                // olsa bile asla tenant satırından okunmamalı; aksi halde sütun boş
                // kalınca getSetting() varsayımı (ör. havale) yanlış açık kalırdı.
                if (in_array($key, self::GLOBAL_SAFE_KEYS, true)) {
                    $sql = "SELECT `{$key}` FROM {$this->table} WHERE tenant_id IS NULL ORDER BY id ASC LIMIT 1";
                    $result = $this->fetchOne($sql);
                    if ($result !== null && array_key_exists($key, $result)) {
                        $v = $result[$key];
                        return $v === null || $v === '' ? null : (string) $v;
                    }
                    return null;
                }
                
                if ($businessId) {
                    $sql = "SELECT `{$key}` FROM {$this->table} WHERE tenant_id = :bid LIMIT 1";
                    $result = $this->fetchOne($sql, ['bid' => $businessId]);
                    if ($result !== null && isset($result[$key])) {
                        return $result[$key];
                    }
                    // SECURITY: When the tenant has no row yet, return null
                    // instead of leaking the first row in the table.
                    return null;
                }
                // No tenant in context: only allow truly global/non-sensitive
                // keys from the first row (platform-wide configuration such as
                // site_name, currency, SMTP, log level, etc.).
                if (!in_array($key, self::GLOBAL_SAFE_KEYS, true)) {
                    return null;
                }
                $sql = "SELECT `{$key}` FROM {$this->table} WHERE tenant_id IS NULL ORDER BY id ASC LIMIT 1";
                $result = $this->fetchOne($sql);
                if ($result !== null && isset($result[$key])) {
                    return $result[$key];
                }
                return null;
            }
        } catch (\Exception $e) {
            try {
                if (!in_array($key, $allowedColumns) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                    return null;
                }
                if (in_array($key, self::GLOBAL_SAFE_KEYS, true)) {
                    $sql = "SELECT `{$key}` FROM {$this->table} WHERE tenant_id IS NULL ORDER BY id ASC LIMIT 1";
                    $result = $this->fetchOne($sql);
                    $v = $result[$key] ?? null;
                    return $v === null || $v === '' ? null : (string) $v;
                }
                if ($businessId) {
                    $sql = "SELECT `{$key}` FROM {$this->table} WHERE tenant_id = :bid LIMIT 1";
                    $result = $this->fetchOne($sql, ['bid' => $businessId]);
                    if ($result !== null && isset($result[$key])) {
                        return $result[$key];
                    }
                    return null;
                }
                if (!in_array($key, self::GLOBAL_SAFE_KEYS, true)) {
                    return null;
                }
                $sql = "SELECT `{$key}` FROM {$this->table} WHERE tenant_id IS NULL ORDER BY id ASC LIMIT 1";
                $result = $this->fetchOne($sql);
                return $result[$key] ?? null;
            } catch (\Exception $e2) {
                return null;
            }
        }
    }

    public function setValue(string $key, string $value): bool {
        // Whitelist of allowed column names for security
        $allowedColumns = [
            'site_name', 'logo_url', 'favicon_url', 'service_charge_rate', 'cover_charge',
            'currency', 'gemini_api_key', 'opusmax_api_key', 'app_env', 'app_debug', 'timezone', 'default_language',
            'session_timeout', 'session_lifetime', 'session_secure_cookie', 'session_http_only', 'session_same_site',
            'max_upload_size', 'supported_languages', 'language_switcher_enabled',
            'auto_detect_language', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username',
            'smtp_password', 'smtp_from_name', 'require_2fa',
            'auth_2fa_totp_enabled', 'auth_2fa_whatsapp_enabled',
            'auth_2fa_email_enabled', 'auth_2fa_sms_enabled',
            'log_level', 'csrf_protection', 'rate_limiting', 'enable_analytics', 'enable_notifications',
            'enable_multi_language', 'websocket_port', 'order_id_prefix', 'order_number_length',
            'wifi_name', 'wifi_password', 'wifi_show_to_customer',
            'working_hours_enabled', 'working_hours_days',
            'waiter_delete_requires_approval', 'order_edit_requires_approval', 'order_edit_approval_role',
            'staff_show_delete_reduce_buttons', 'manager_show_delete_reduce_buttons',
            'business_latitude', 'business_longitude', 'business_radius', 'business_address',
            'meta_app_id', 'meta_app_secret', 'meta_access_token', 'meta_webhook_verify_token', 'meta_phone_number_id', 'meta_whatsapp_business_account_id',
            'meta_queue_template_name', 'meta_queue_messaging_enabled',
            'whatsapp_daily_limit', 'whatsapp_monthly_limit',
            'payment_bank_transfer_enabled',
            'welcome_email_enabled', 'welcome_whatsapp_enabled'
        ];
        
        self::$lastSetValueError = null;
        // Validate column name
        if (!in_array($key, $allowedColumns) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
            \App\Core\Logger::error("SystemSettingsRepository::setValue - Key not allowed: {$key}");
            self::$lastSetValueError = "Key not allowed: {$key}";
            return false;
        }
        
        // Check if setting_key column exists
        try {
            $hasSettingKey = \App\Core\DbSchema::hasColumn($this->table, 'setting_key');

            if ($hasSettingKey) {
                $sql = "INSERT INTO {$this->table} (setting_key, setting_value) 
                        VALUES (:key, :value) 
                        ON DUPLICATE KEY UPDATE setting_value = :value";
                return $this->execute($sql, [
                    'key' => $key,
                    'value' => $value
                ]);
            } else {
                $businessId = null;
                try {
                    $businessId = $this->getTenantBusinessId();
                } catch (\Throwable $e) {
                    // Session/tenant not set - DO NOT fall back to first row
                }
                if (in_array($key, self::GLOBAL_SAFE_KEYS, true)) {
                    $businessId = null;
                }

                $checkRow = null;
                if ($businessId) {
                    try {
                        $checkRowSql = "SELECT id FROM {$this->table} WHERE tenant_id = :bid LIMIT 1";
                        $stmt = $this->db->prepare($checkRowSql);
                        $stmt->execute(['bid' => $businessId]);
                        $checkRow = $stmt->fetch();
                        if (!$checkRow) {
                            $insertSql = "INSERT INTO {$this->table} (tenant_id) VALUES (:bid)";
                            $this->db->prepare($insertSql)->execute(['bid' => $businessId]);
                            $stmt = $this->db->prepare($checkRowSql);
                            $stmt->execute(['bid' => $businessId]);
                            $checkRow = $stmt->fetch();
                        }
                    } catch (\Throwable $e) {
                        // Column missing or insert failed - bail out so we
                        // never accidentally write to another tenant's row.
                        \App\Core\Logger::error("SystemSettingsRepository::setValue - Could not resolve tenant row: " . $e->getMessage(), [
                            'tenant_id' => $businessId,
                            'key' => $key,
                        ]);
                        self::$lastSetValueError = "Tenant settings row could not be prepared.";
                        return false;
                    }
                } else {
                    // SECURITY: Without a tenant in context, we only allow
                    // writing to a single tenant-less row (tenant_id IS NULL).
                    // Tenant-scoped keys are rejected entirely.
                    if (!in_array($key, self::GLOBAL_SAFE_KEYS, true)) {
                        \App\Core\Logger::error("SystemSettingsRepository::setValue - Refusing to write tenant-scoped key without tenant context", [
                            'key' => $key,
                        ]);
                        self::$lastSetValueError = "Tenant context is required to save '{$key}'.";
                        return false;
                    }
                    try {
                        $checkRowSql = "SELECT id FROM {$this->table} WHERE tenant_id IS NULL ORDER BY id ASC LIMIT 1";
                        $stmt = $this->db->prepare($checkRowSql);
                        $stmt->execute();
                        $checkRow = $stmt->fetch();
                        if (!$checkRow) {
                            $this->db->prepare("INSERT INTO {$this->table} (tenant_id) VALUES (NULL)")->execute();
                            $stmt = $this->db->prepare($checkRowSql);
                            $stmt->execute();
                            $checkRow = $stmt->fetch();
                        }
                    } catch (\Throwable $e) {
                        self::$lastSetValueError = "Global settings row could not be prepared.";
                        return false;
                    }
                }
                if (!$checkRow) {
                    self::$lastSetValueError = "No settings row available for write.";
                    return false;
                }
                
                if ($checkRow) {
                    try {
                        $optionalColumns = [
                            'order_edit_requires_approval' => "TINYINT(1) DEFAULT 1",
                            'staff_show_delete_reduce_buttons' => "TINYINT(1) DEFAULT 1",
                            'manager_show_delete_reduce_buttons' => "TINYINT(1) DEFAULT 1",
                            'waiter_delete_requires_approval' => "TINYINT(1) DEFAULT 0",
                            'order_edit_approval_role' => "VARCHAR(50) DEFAULT 'MANAGER'",
                            'payment_bank_transfer_enabled' => "VARCHAR(1) DEFAULT '1'",
                            'meta_app_id' => "VARCHAR(100) DEFAULT NULL",
                            'meta_app_secret' => "VARCHAR(255) DEFAULT NULL",
                            'meta_access_token' => "TEXT DEFAULT NULL",
                            'meta_webhook_verify_token' => "VARCHAR(255) DEFAULT NULL",
                            'meta_phone_number_id' => "VARCHAR(50) DEFAULT NULL",
                            'meta_whatsapp_business_account_id' => "VARCHAR(50) DEFAULT NULL",
                            'meta_queue_messaging_enabled' => "VARCHAR(1) DEFAULT '1'",
                            'whatsapp_daily_limit' => "INT DEFAULT NULL",
                            'whatsapp_monthly_limit' => "INT DEFAULT NULL",
                            'welcome_email_enabled' => "VARCHAR(1) DEFAULT '1'",
                            'welcome_whatsapp_enabled' => "VARCHAR(1) DEFAULT '1'",
                            'auth_2fa_totp_enabled' => "VARCHAR(1) DEFAULT '1'",
                            'auth_2fa_whatsapp_enabled' => "VARCHAR(1) DEFAULT '0'",
                            'auth_2fa_email_enabled' => "VARCHAR(1) DEFAULT '1'",
                            'auth_2fa_sms_enabled' => "VARCHAR(1) DEFAULT '0'",
                            'opusmax_api_key' => "VARCHAR(255) DEFAULT NULL",
                        ];
                        // Eksik sütunları ekle (migration çalışmamış olabilir)
                        if (isset($optionalColumns[$key]) && !\App\Core\DbSchema::hasColumn($this->table, $key)) {
                            try {
                                $this->db->exec("ALTER TABLE `{$this->table}` ADD COLUMN `{$key}` " . $optionalColumns[$key]);
                                \App\Core\DbSchema::forget($this->table);
                            } catch (\Throwable $alterEx) {
                                if (class_exists('\App\Core\Logger')) {
                                    \App\Core\Logger::error("SystemSettingsRepository::setValue - ALTER failed for {$key}: " . $alterEx->getMessage());
                                }
                            }
                        }
                        
                        // For DECIMAL/numeric columns, convert empty string to NULL
                        $numericColumns = ['business_latitude', 'business_longitude', 'business_radius', 'service_charge_rate', 'cover_charge', 'session_timeout', 'max_upload_size', 'websocket_port', 'smtp_port', 'order_number_length', 'session_lifetime'];
                        $dbValue = $value;
                        if (in_array($key, $numericColumns) && ($value === '' || $value === null)) {
                            $dbValue = null;
                        }
                        
                        $sql = "UPDATE {$this->table} SET `{$key}` = :value WHERE id = :id LIMIT 1";
                        $stmt = $this->db->prepare($sql);
                        try {
                            $result = $stmt->execute(['value' => $dbValue, 'id' => $checkRow['id']]);
                        } catch (\PDOException $e) {
                            $result = false;
                            if (strpos($e->getMessage(), 'Unknown column') !== false && isset($optionalColumns[$key])) {
                                try {
                                    $this->db->exec("ALTER TABLE `{$this->table}` ADD COLUMN `{$key}` " . $optionalColumns[$key]);
                                    $stmt = $this->db->prepare($sql);
                                    $result = $stmt->execute(['value' => $dbValue, 'id' => $checkRow['id']]);
                                } catch (\Throwable $retryEx) {
                                    self::$lastSetValueError = $key . ': ' . $retryEx->getMessage();
                                    return false;
                                }
                            } else {
                                throw $e;
                            }
                        }
                        
                        if (!$result) {
                            $errorInfo = $stmt->errorInfo();
                            error_log("SystemSettingsRepository::setValue - Execute failed for key: {$key}, Error: " . json_encode($errorInfo));
                            return false;
                        }
                        
                        // Checkbox/boolean ayarlar için doğrulama atlanır
                        $skipVerificationKeys = ['order_edit_requires_approval', 'staff_show_delete_reduce_buttons', 'manager_show_delete_reduce_buttons', 'wifi_show_to_customer', 'working_hours_enabled', 'waiter_delete_requires_approval', 'payment_bank_transfer_enabled', 'meta_queue_messaging_enabled', 'welcome_email_enabled', 'welcome_whatsapp_enabled', 'auth_2fa_totp_enabled', 'auth_2fa_whatsapp_enabled', 'auth_2fa_email_enabled', 'auth_2fa_sms_enabled'];
                        if (in_array($key, $skipVerificationKeys, true)) {
                            return true;
                        }
                        
                        // Verify update was successful (for non-JSON fields, simple comparison)
                        $verifySql = "SELECT `{$key}` FROM {$this->table} WHERE id = :id LIMIT 1";
                        $verifyStmt = $this->db->prepare($verifySql);
                        $verifyStmt->execute(['id' => $checkRow['id']]);
                        $verifyRow = $verifyStmt->fetch(\PDO::FETCH_ASSOC);
                        
                        if ($verifyRow && array_key_exists($key, $verifyRow)) {
                            $savedValue = $verifyRow[$key];
                            
                            // For JSON fields, compare by decoding and re-encoding (normalize format)
                            if ($key === 'working_hours_days' || (is_string($value) && strlen($value) > 0 && ($value[0] === '{' || $value[0] === '['))) {
                                // Try JSON comparison - decode both and compare
                                $decodedValue = json_decode($value, true);
                                $decodedSaved = json_decode($savedValue, true);
                                
                                if ($decodedValue !== null && $decodedSaved !== null) {
                                    // Both are valid JSON, compare decoded values
                                    if ($decodedValue === $decodedSaved || json_encode($decodedValue) === json_encode($decodedSaved)) {
                                        return true;
                                    } else {
                                        // Fallback to string comparison with trimmed whitespace
                                        if (trim($savedValue) === trim($value)) {
                                            return true;
                                        }
                                        error_log("SystemSettingsRepository::setValue - JSON content mismatch for key: {$key}");
                                        return false;
                                    }
                                } else {
                                    // Not valid JSON, fallback to string comparison
                                    if (trim($savedValue) === trim($value)) {
                                        return true;
                                    }
                                    error_log("SystemSettingsRepository::setValue - JSON decode failed or mismatch for key: {$key}");
                                    return false;
                                }
                            } else {
                                // PDO can return TINYINT(1) as boolean - normalize so '0' matches
                                if ($savedValue === false) {
                                    $savedValue = '0';
                                } elseif ($savedValue === true) {
                                    $savedValue = '1';
                                }
                                $savedValueStr = $savedValue === null ? '' : (string)$savedValue;
                                $valueStr = $value === null ? '' : (string)$value;
                                
                                if ($savedValueStr === $valueStr) {
                                    return true;
                                }
                                
                                // Both null/empty = match (DECIMAL NULL vs empty string)
                                if (($savedValue === null || $savedValueStr === '') && ($value === null || $valueStr === '')) {
                                    return true;
                                }
                                
                                // Handle numeric columns: MySQL may store "" as "0" or "0.00" for numeric types
                                if ($valueStr === '' && is_numeric($savedValueStr) && (float)$savedValueStr == 0) {
                                    return true;
                                }
                                if (is_numeric($valueStr) && is_numeric($savedValueStr) && (float)$valueStr == (float)$savedValueStr) {
                                    return true;
                                }
                                
                                // 0/1 and false/true equivalence
                                if (($valueStr === '0' && ($savedValueStr === '0' || $savedValue === 0)) || ($valueStr === '1' && ($savedValueStr === '1' || $savedValue === 1))) {
                                    return true;
                                }
                                
                                error_log("SystemSettingsRepository::setValue - Value mismatch for key: {$key}, Expected: " . substr($valueStr, 0, 100) . ", Got: " . substr($savedValueStr, 0, 100));
                                return false;
                            }
                        } else {
                            error_log("SystemSettingsRepository::setValue - Verification query returned no row or missing key for key: {$key}");
                            return false;
                        }
                    } catch (\PDOException $e) {
                        $msg = $e->getMessage();
                        \App\Core\Logger::error("SystemSettingsRepository::setValue - PDO Exception for key: {$key}, Error: " . $msg);
                        self::$lastSetValueError = "{$key}: " . $msg;
                        return false;
                    } catch (\Exception $e) {
                        $msg = $e->getMessage();
                        \App\Core\Logger::error("SystemSettingsRepository::setValue - Exception for key: {$key}, Error: " . $msg);
                        self::$lastSetValueError = "{$key}: " . $msg;
                        return false;
                    }
                } else {
                    // Insert new row scoped to tenant (or tenant_id = NULL for globals).
                    $columns = \App\Core\DbSchema::columns($this->table);

                    $placeholders = [];
                    $values = [];
                    foreach ($columns as $col) {
                        if ($col === 'id') {
                            continue;
                        }
                        if ($col === $key) {
                            $placeholders[] = "`{$col}` = :value";
                            $values['value'] = $value;
                        } elseif ($col === 'tenant_id') {
                            if (!empty($businessId)) {
                                $placeholders[] = "`tenant_id` = :__tid";
                                $values['__tid'] = $businessId;
                            } else {
                                $placeholders[] = "`tenant_id` = NULL";
                            }
                        } else {
                            $placeholders[] = "`{$col}` = DEFAULT";
                        }
                    }
                    
                    if (!empty($placeholders)) {
                        $sql = "INSERT INTO {$this->table} SET " . implode(', ', $placeholders);
                        $stmt = $this->db->prepare($sql);
                        return $stmt->execute($values);
                    }
                    return false;
                }
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('SystemSettingsRepository::setValue error: ' . $e->getMessage());
            // Fallback to direct column update scoped to the current tenant.
            // SECURITY: Never update a row we cannot prove belongs to the caller.
            try {
                $businessId = $this->getTenantBusinessId();
                $checkRow = null;
                if ($businessId) {
                    $checkRowSql = "SELECT id FROM {$this->table} WHERE tenant_id = :bid LIMIT 1";
                    $stmt = $this->db->prepare($checkRowSql);
                    $stmt->execute(['bid' => $businessId]);
                    $checkRow = $stmt->fetch();
                } elseif (in_array($key, self::GLOBAL_SAFE_KEYS, true)) {
                    $checkRowSql = "SELECT id FROM {$this->table} WHERE tenant_id IS NULL LIMIT 1";
                    $stmt = $this->db->prepare($checkRowSql);
                    $stmt->execute();
                    $checkRow = $stmt->fetch();
                } else {
                    // Tenant-scoped key without tenant context: refuse.
                    self::$lastSetValueError = "Tenant context is required to save '{$key}'.";
                    return false;
                }
                
                if ($checkRow) {
                    $sql = "UPDATE {$this->table} SET `{$key}` = :value WHERE id = :id LIMIT 1";
                    $stmt = $this->db->prepare($sql);
                    return $stmt->execute(['value' => $value, 'id' => $checkRow['id']]);
                } else {
                    // Insert new row scoped to tenant (or tenant_id = NULL for globals).
                    $columns = \App\Core\DbSchema::columns($this->table);

                    $placeholders = [];
                    $values = [];
                    foreach ($columns as $col) {
                        if ($col === 'id') {
                            continue;
                        }
                        if ($col === $key) {
                            $placeholders[] = "`{$col}` = :value";
                            $values['value'] = $value;
                        } elseif ($col === 'tenant_id') {
                            if (!empty($businessId)) {
                                $placeholders[] = "`tenant_id` = :__tid";
                                $values['__tid'] = $businessId;
                            } else {
                                $placeholders[] = "`tenant_id` = NULL";
                            }
                        } else {
                            $placeholders[] = "`{$col}` = DEFAULT";
                        }
                    }
                    
                    if (!empty($placeholders)) {
                        $sql = "INSERT INTO {$this->table} SET " . implode(', ', $placeholders);
                        $stmt = $this->db->prepare($sql);
                        return $stmt->execute($values);
                    }
                    return false;
                }
            } catch (\Exception $e2) {
                return false;
            }
        }
    }

    public function getAll(): array {
        $businessId = $this->getTenantBusinessId();
        try {
            $hasSettingKey = \App\Core\DbSchema::hasColumn($this->table, 'setting_key');

            if ($hasSettingKey) {
                $sql = "SELECT * FROM {$this->table} ORDER BY setting_key";
                return $this->fetchAll($sql);
            } else {
                if ($businessId) {
                    $sql = "SELECT * FROM {$this->table} WHERE tenant_id = :bid ORDER BY id LIMIT 1";
                    $result = $this->fetchAll($sql, ['bid' => $businessId]);
                    if (!empty($result)) {
                        return $result;
                    }
                    // SECURITY: This tenant has no settings row yet. Auto-create an
                    // empty row for them so subsequent reads/writes are scoped
                    // correctly, and return only the safe global defaults. Never
                    // fall back to another tenant's row or the first row.
                    try {
                        $insertSql = "INSERT INTO {$this->table} (tenant_id) VALUES (:bid)";
                        $this->db->prepare($insertSql)->execute(['bid' => $businessId]);
                        $result = $this->fetchAll($sql, ['bid' => $businessId]);
                        if (!empty($result)) {
                            return $result;
                        }
                    } catch (\Throwable $ignored) {}
                    return [$this->buildGlobalDefaultsRow($businessId)];
                }
                // No tenant in context: return only a tenant-less row (never
                // leak an arbitrary tenant's row).
                $sql = "SELECT * FROM {$this->table} WHERE tenant_id IS NULL ORDER BY id LIMIT 1";
                $result = $this->fetchAll($sql);
                return !empty($result) ? $result : [];
            }
        } catch (\Exception $e) {
            // If anything goes wrong, return empty rather than leaking data.
            return [];
        }
    }

    /**
     * Return the single platform-wide settings row (tenant_id IS NULL).
     * Used by super-admin /qodmin/settings so platform ayarları (Meta API,
     * SMTP, 2FA, ödeme vs.) hiçbir zaman bir işletmenin tenant satırı ile
     * karışmaz. Hiç satır yoksa boş array döner.
     */
    public function getPlatformSettings(): array {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE tenant_id IS NULL ORDER BY id ASC LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Build a synthetic "empty" settings row carrying only global defaults.
     * Used when a tenant has no row yet to avoid blank UIs while still
     * protecting tenant-private fields.
     */
    private function buildGlobalDefaultsRow(string $tenantId): array {
        $defaults = [
            'id' => null,
            'tenant_id' => $tenantId,
            'currency' => 'TRY',
            'timezone' => 'Europe/Istanbul',
            'default_language' => 'tr',
            'site_name' => 'Qordy',
        ];
        return $defaults;
    }

    /**
     * Override findAll to prevent ORDER BY on non-existent column
     * Check if setting_key column exists before using it
     */
    public function findAll(array $criteria = []): array {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $field => $value) {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        // Try to order by setting_key if it exists, otherwise skip ORDER BY
        // Check if column exists by trying a simple query
        try {
            $columnExists = \App\Core\DbSchema::hasColumn($this->table, 'setting_key');

            if ($columnExists) {
                $sql .= " ORDER BY setting_key ASC";
            }
        } catch (\Exception $e) {
            // If check fails, skip ORDER BY
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

