<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

/**
 * QueueSettings - per-tenant configuration of the QR queue / sıramatik system
 */
class QueueSettings extends \App\Core\Model
{
    protected $table = 'queue_settings';

    /**
     * Fetch settings for a tenant, creating defaults row on first access.
     */
    public function findForTenant(string $tenantId): array
    {
        $row = $this->fetch(
            "SELECT * FROM queue_settings WHERE tenant_id = :tid LIMIT 1",
            ['tid' => $tenantId]
        );

        if ($row) {
            return $this->decode($row);
        }

        // Seed defaults on first access so the admin page is always populated
        $defaults = $this->defaultRow($tenantId);
        $this->insert('queue_settings', $defaults);

        $row = $this->fetch(
            "SELECT * FROM queue_settings WHERE tenant_id = :tid LIMIT 1",
            ['tid' => $tenantId]
        );

        return $this->decode($row ?: $defaults);
    }

    public function updateForTenant(string $tenantId, array $data): bool
    {
        $payload = $this->encode($data);
        unset($payload['tenant_id'], $payload['id']);

        if (empty($payload)) {
            return true;
        }

        $result = $this->update(
            'queue_settings',
            $payload,
            'tenant_id = :__tid',
            ['__tid' => $tenantId]
        );

        return $result !== false;
    }

    private function defaultRow(string $tenantId): array
    {
        return [
            'tenant_id'                => $tenantId,
            'is_enabled'               => 1,
            'average_wait_minutes'     => 15,
            'notify_positions_ahead'   => 0,
            'qr_token_ttl_seconds'     => 90,
            'max_party_size'           => 12,
            'languages'                => json_encode(['tr', 'en', 'de', 'ar'], JSON_UNESCAPED_UNICODE),
            'default_language'         => 'tr',
            'display_title'            => json_encode([
                'tr' => 'Tüm masalarımız dolu',
                'en' => 'All tables are occupied',
                'de' => 'Alle Tische sind besetzt',
                'ar' => 'جميع الطاولات محجوزة',
            ], JSON_UNESCAPED_UNICODE),
            'display_subtitle'         => json_encode([
                'tr' => 'QR kodu okutarak sıra alın ya da rezervasyon yapın',
                'en' => 'Scan the QR to get in line or book a table',
                'de' => 'Scannen Sie den QR, um sich anzustellen',
                'ar' => 'امسح رمز QR لحجز دورك',
            ], JSON_UNESCAPED_UNICODE),
            'display_call_to_action'   => json_encode([
                'tr' => 'Telefonunuzla QR kodu okutun',
                'en' => 'Scan the QR with your phone',
                'de' => 'Scannen Sie den QR-Code',
                'ar' => 'امسح رمز QR بهاتفك',
            ], JSON_UNESCAPED_UNICODE),
            'display_theme_color'      => '#0f172a',
            'display_accent_color'     => '#f97316',
            'require_email'            => 0,
            'require_note'             => 0,
            'allow_baby'               => 1,
            'allow_accessibility'      => 1,
            'whatsapp_enabled'         => 1,
            'email_enabled'            => 1,
            'auto_no_show_minutes'     => 5,
            'entry_cooldown_minutes'   => 90,
            'is_accepting_queue'       => 0,
            'auto_queue_from_tables'   => 0,
        ];
    }

    /**
     * Decode JSON columns on read.
     */
    private function decode(array $row): array
    {
        foreach (['languages', 'display_title', 'display_subtitle', 'display_call_to_action'] as $k) {
            if (!isset($row[$k])) {
                $row[$k] = null;
                continue;
            }
            if (is_string($row[$k])) {
                $decoded = json_decode($row[$k], true);
                $row[$k] = is_array($decoded) ? $decoded : null;
            }
        }

        // Normalize booleans to int 0/1 for consistent view layer usage
        foreach ([
            'is_enabled', 'is_accepting_queue', 'auto_queue_from_tables',
            'show_logo', 'show_active_numbers', 'show_estimated_wait', 'show_waiting_count', 'show_powered_by',
            'require_email', 'require_note', 'allow_baby',
            'allow_accessibility', 'whatsapp_enabled', 'email_enabled',
        ] as $k) {
            if (isset($row[$k])) {
                $row[$k] = (int) $row[$k];
            }
        }

        // Sensible defaults when columns are empty
        if (empty($row['languages'])) {
            $row['languages'] = ['tr', 'en'];
        }
        if (empty($row['default_language'])) {
            $row['default_language'] = $row['languages'][0] ?? 'tr';
        }

        return $row;
    }

    /**
     * Encode JSON fields on write.
     */
    private function encode(array $data): array
    {
        foreach (['languages', 'display_title', 'display_subtitle', 'display_call_to_action'] as $k) {
            if (array_key_exists($k, $data) && is_array($data[$k])) {
                $data[$k] = json_encode($data[$k], JSON_UNESCAPED_UNICODE);
            }
        }
        return $data;
    }
}
