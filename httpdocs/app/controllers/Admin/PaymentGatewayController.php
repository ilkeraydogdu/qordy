<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class PaymentGatewayController extends Controller {
    
    public function paymentGateways() {
        // Check payment.gateways.view or settings.edit permission
        // BUSINESS_MANAGER için payment.gateways.view temel izinler listesinde var
        if (!$this->hasPermission('payment.gateways.view') && !$this->hasPermission('settings.edit')) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
            header('Location: ' . BASE_URL . '/admin');
            exit;
        }

        $paymentGatewayRepository = \App\Core\DependencyFactory::getPaymentGatewayRepository();
        $gateways = $paymentGatewayRepository->getAll();
        
        // Gateway field tanımlarını ekle
        foreach ($gateways as &$gateway) {
            $code = $gateway['gateway_code'] ?? '';
            $gateway['fields'] = \App\Services\PaymentGatewayRegistry::getGatewayFields($code);
        }

        // NOTE: payment_bank_transfer_enabled tek bir yerden yönetilsin diye
        // /qodmin/settings Ödeme sekmesinde tutuluyor; burada duplicate
        // yüklememize gerek yok.
        $data = [
            'gateways' => $gateways,
            'is_super_admin' => $this->isSuperAdmin(),
            'api_prefix' => $this->isSuperAdmin() ? '/api/qodmin' : '/api/business',
        ];

        $this->view('admin/payment-gateways', $data);
    }

    /**
     * DEPRECATED: Havale / EFT aç-kapa ayarı tek bir yerden (settings modülü)
     * yönetilmektedir. Eski endpoint'ler HTTP 410 ile yanıt verir; UI artık
     * /qodmin/settings?tab=payment üzerinden çağrı yapıyor.
     */
    public function updateBankTransferEnabled(): void {
        $this->toastNotificationService->sendApiResponse(
            'error',
            'Bu endpoint kaldırıldı. Havale/EFT ayarı /qodmin/settings -> Ödeme sekmesinden yapılır.',
            [],
            410,
            ['error' => 'endpoint_removed', 'redirect' => BASE_URL . '/qodmin/settings?tab=payment#payment']
        );
    }

    public function updatePaymentGateway() {
        // Check payment.gateways.edit or settings.edit permission
        if (!$this->hasPermission('payment.gateways.edit') && !$this->hasPermission('settings.edit')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $gatewayId = $requestData['gateway_id'] ?? '';
        $config = $requestData['config'] ?? [];

        if (empty($gatewayId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }

        $paymentGatewayRepository = \App\Core\DependencyFactory::getPaymentGatewayRepository();
        $result = $paymentGatewayRepository->updateConfig($gatewayId, $config);

        if ($result) {
            $paymentGatewayService = \App\Core\DependencyFactory::getPaymentGatewayService();
            $paymentGatewayService->reloadGateways();
            
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
        }
    }
    
    /**
     * Seed payment gateways (initialize default gateways)
     * Dinamik olarak registry'den gateway tanımlarını alır
     */
    public function seedGateways() {
        try {
            if (!$this->hasPermission('settings.edit')) {
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Yetkisiz erişim'
                ], 401);
                return;
            }

            // Registry dosyasını kontrol et ve yükle
            $registryPath = __DIR__ . '/../../services/PaymentGatewayRegistry.php';
            if (!file_exists($registryPath)) {
                throw new \Exception('PaymentGatewayRegistry dosyası bulunamadı: ' . $registryPath);
            }
            require_once $registryPath;
            
            // Config dosyasını kontrol et
            $configPath = __DIR__ . '/../../config/payment_gateways.php';
            if (!file_exists($configPath)) {
                throw new \Exception('Payment gateways config dosyası bulunamadı: ' . $configPath);
            }
            
            $paymentGatewayRepository = \App\Core\DependencyFactory::getPaymentGatewayRepository();
            
            // Tablo yoksa oluştur veya eksik sütunları ekle
            $db = \App\Core\DependencyFactory::getDatabase();
            try {
                $checkTable = $db->query("SHOW TABLES LIKE 'payment_gateways'");
                if ($checkTable->rowCount() === 0) {
                    $createTableSql = "CREATE TABLE IF NOT EXISTS payment_gateways (
                        gateway_id VARCHAR(50) PRIMARY KEY,
                        gateway_code VARCHAR(50) NOT NULL UNIQUE,
                        gateway_name VARCHAR(100) NOT NULL,
                        display_name VARCHAR(100) NOT NULL,
                        description TEXT,
                        is_enabled TINYINT(1) DEFAULT 0,
                        test_mode TINYINT(1) DEFAULT 1,
                        sort_order INT DEFAULT 0,
                        api_key VARCHAR(255) DEFAULT '',
                        secret_key VARCHAR(255) DEFAULT '',
                        merchant_id VARCHAR(255) DEFAULT NULL,
                        config_json TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_gateway_code (gateway_code),
                        INDEX idx_is_enabled (is_enabled),
                        INDEX idx_sort_order (sort_order)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $db->exec($createTableSql);
                    \App\Core\Logger::info('Payment gateways table created successfully');
                } else {
                    // Tablo mevcut, eksik sütunları kontrol et ve ekle
                    $columns = \App\Core\DbSchema::columns('payment_gateways');
                    
                    // Mevcut satır sayısını kontrol et
                    $rowCount = $db->query("SELECT COUNT(*) FROM payment_gateways")->fetchColumn();
                    $hasData = $rowCount > 0;
                    
                    $requiredColumns = [
                        'gateway_code' => [
                            'sql' => $hasData 
                                ? "ALTER TABLE payment_gateways ADD COLUMN gateway_code VARCHAR(50) NULL AFTER gateway_id"
                                : "ALTER TABLE payment_gateways ADD COLUMN gateway_code VARCHAR(50) NOT NULL UNIQUE AFTER gateway_id",
                            'needsUpdate' => $hasData
                        ],
                        'gateway_name' => [
                            'sql' => "ALTER TABLE payment_gateways ADD COLUMN gateway_name VARCHAR(100) " . ($hasData ? "NULL" : "NOT NULL") . " AFTER gateway_code",
                            'needsUpdate' => false
                        ],
                        'display_name' => [
                            'sql' => "ALTER TABLE payment_gateways ADD COLUMN display_name VARCHAR(100) " . ($hasData ? "NULL" : "NOT NULL") . " AFTER gateway_name",
                            'needsUpdate' => false
                        ],
                        'description' => [
                            'sql' => "ALTER TABLE payment_gateways ADD COLUMN description TEXT AFTER display_name",
                            'needsUpdate' => false
                        ],
                        'is_enabled' => [
                            'sql' => "ALTER TABLE payment_gateways ADD COLUMN is_enabled TINYINT(1) DEFAULT 0 AFTER description",
                            'needsUpdate' => false
                        ],
                        'test_mode' => [
                            'sql' => "ALTER TABLE payment_gateways ADD COLUMN test_mode TINYINT(1) DEFAULT 1 AFTER is_enabled",
                            'needsUpdate' => false
                        ],
                        'sort_order' => [
                            'sql' => "ALTER TABLE payment_gateways ADD COLUMN sort_order INT DEFAULT 0 AFTER test_mode",
                            'needsUpdate' => false
                        ],
                        'api_key' => [
                            'sql' => "ALTER TABLE payment_gateways ADD COLUMN api_key VARCHAR(255) DEFAULT '' AFTER sort_order",
                            'needsUpdate' => false
                        ],
                        'secret_key' => [
                            'sql' => "ALTER TABLE payment_gateways ADD COLUMN secret_key VARCHAR(255) DEFAULT '' AFTER api_key",
                            'needsUpdate' => false
                        ],
                        'merchant_id' => [
                            'sql' => "ALTER TABLE payment_gateways ADD COLUMN merchant_id VARCHAR(255) DEFAULT NULL AFTER secret_key",
                            'needsUpdate' => false
                        ],
                        'config_json' => [
                            'sql' => "ALTER TABLE payment_gateways ADD COLUMN config_json TEXT AFTER merchant_id",
                            'needsUpdate' => false
                        ],
                        'created_at' => [
                            'sql' => "ALTER TABLE payment_gateways ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER config_json",
                            'needsUpdate' => false
                        ],
                        'updated_at' => [
                            'sql' => "ALTER TABLE payment_gateways ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
                            'needsUpdate' => false
                        ]
                    ];
                    
                    foreach ($requiredColumns as $columnName => $columnConfig) {
                        if (!in_array($columnName, $columns)) {
                            try {
                                $db->exec($columnConfig['sql']);
                                \App\Core\Logger::info("Added missing column '{$columnName}' to payment_gateways table");
                                
                                // gateway_code için index ekle (UNIQUE constraint eklenmişse)
                                if ($columnName === 'gateway_code' && !$hasData) {
                                    try {
                                        $db->exec("CREATE INDEX idx_gateway_code ON payment_gateways (gateway_code)");
                                    } catch (\Exception $e) {
                                        // Index zaten varsa hata vermez, devam et
                                    }
                                }
                                
                                // Eğer mevcut veri varsa ve gateway_code eklendiyse, gateway_id'den türet
                                if ($columnName === 'gateway_code' && $hasData && $columnConfig['needsUpdate']) {
                                    // gateway_id'den gateway_code türet (örn: gw_iyzico -> iyzico)
                                    $db->exec("UPDATE payment_gateways SET gateway_code = REPLACE(gateway_id, 'gw_', '') WHERE gateway_code IS NULL");
                                    // Şimdi NOT NULL ve UNIQUE yap
                                    $db->exec("ALTER TABLE payment_gateways MODIFY COLUMN gateway_code VARCHAR(50) NOT NULL");
                                    $db->exec("ALTER TABLE payment_gateways ADD UNIQUE KEY unique_gateway_code (gateway_code)");
                                    try {
                                        $db->exec("CREATE INDEX idx_gateway_code ON payment_gateways (gateway_code)");
                                    } catch (\Exception $e) {
                                        // Index zaten varsa hata vermez
                                    }
                                }
                            } catch (\Exception $e) {
                                \App\Core\Logger::warning("Could not add column '{$columnName}': " . $e->getMessage());
                            }
                        }
                    }
                    
                    // Eski 'name' sütununu kontrol et ve düzelt (eğer varsa ve NOT NULL ise)
                    if (in_array('name', $columns)) {
                        try {
                            $nameColumnInfo = \App\Core\DbSchema::columnMeta('payment_gateways', 'name');
                            if ($nameColumnInfo && ($nameColumnInfo['Null'] === 'NO' || $nameColumnInfo['Null'] === '')) {
                                // name sütunu NOT NULL ise, nullable yap veya default değer ver
                                try {
                                    $db->exec("ALTER TABLE payment_gateways MODIFY COLUMN name VARCHAR(100) NULL DEFAULT NULL");
                                    \App\Core\Logger::info("Modified 'name' column to be nullable in payment_gateways table");
                                } catch (\Exception $e) {
                                    \App\Core\Logger::warning("Could not modify 'name' column: " . $e->getMessage());
                                }
                            }
                        } catch (\Exception $e) {
                            \App\Core\Logger::warning("Could not check 'name' column: " . $e->getMessage());
                        }
                    }
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error('PaymentGateway table creation/migration error: ' . $e->getMessage());
                throw new \Exception('Tablo oluşturulamadı veya güncellenemedi: ' . $e->getMessage());
            }
            
            // Mevcut gateway'leri kontrol et
            $existingGateways = $paymentGatewayRepository->getAll();
            $existingCodes = array_column($existingGateways, 'gateway_code');
            
            // Registry'den gateway tanımlarını al
            $gateways = \App\Services\PaymentGatewayRegistry::getSeedData();
            
            if (empty($gateways)) {
                throw new \Exception('Registry\'den gateway tanımları alınamadı');
            }
            
            $inserted = 0;
            $skipped = 0;
            $messages = [];
            
            foreach ($gateways as $gateway) {
                if (in_array($gateway['gateway_code'], $existingCodes)) {
                    $skipped++;
                    $messages[] = "⊘ {$gateway['display_name']} zaten mevcut";
                    continue;
                }
                
                try {
                    $result = $paymentGatewayRepository->create($gateway);
                    if ($result) {
                        $inserted++;
                        $messages[] = "✓ {$gateway['display_name']} eklendi";
                    } else {
                        $messages[] = "✗ {$gateway['display_name']} eklenirken bilinmeyen hata";
                    }
                } catch (\Exception $e) {
                    \App\Core\Logger::error("PaymentGateway seed error for {$gateway['gateway_code']}: " . $e->getMessage());
                    $messages[] = "✗ {$gateway['display_name']} eklenirken hata: " . $e->getMessage();
                }
            }
            
            // Gateway'leri yeniden yükle
            try {
                $paymentGatewayService = \App\Core\DependencyFactory::getPaymentGatewayService();
                $paymentGatewayService->reloadGateways();
            } catch (\Exception $e) {
                \App\Core\Logger::warning("PaymentGateway reload error: " . $e->getMessage());
            }
            
            // Eğer hiç gateway eklenmediyse ve zaten gateway'ler varsa, başarılı sayma
            // Ama eğer hiç gateway yoksa ve eklenemediyse, hata döndür
            if ($inserted === 0 && empty($existingGateways)) {
                $errorMsg = 'Hiç gateway eklenemedi. ';
                if (!empty($messages)) {
                    $errorMsg .= implode(', ', $messages);
                } else {
                    $errorMsg .= 'Bilinmeyen hata oluştu.';
                }
                throw new \Exception($errorMsg);
            }
            
            $this->apiResponse([
                'success' => true,
                'inserted' => $inserted,
                'skipped' => $skipped,
                'messages' => $messages,
                'message' => "Toplam {$inserted} gateway eklendi" . ($skipped > 0 ? ", {$skipped} gateway atlandı" : ""),
                'has_gateways' => ($inserted > 0 || !empty($existingGateways))
            ]);
            
        } catch (\Exception $e) {
            \App\Core\Logger::error('PaymentGateway seedGateways error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->apiResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

