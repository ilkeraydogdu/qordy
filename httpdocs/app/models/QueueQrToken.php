<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

/**
 * QueueQrToken - rotating short-lived tokens rendered as QR on the door screen.
 * Each scan resolves to the public queue form while the token is live.
 */
class QueueQrToken extends \App\Core\Model
{
    protected $table = 'queue_qr_tokens';

    public function findByToken(string $token): ?array
    {
        $row = $this->fetch(
            "SELECT * FROM queue_qr_tokens WHERE token = :t LIMIT 1",
            ['t' => $token]
        );
        return $row ?: null;
    }

    public function findCurrentForTenant(string $tenantId): ?array
    {
        $row = $this->fetch(
            "SELECT * FROM queue_qr_tokens
             WHERE tenant_id = :tid AND is_revoked = 0 AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1",
            ['tid' => $tenantId]
        );
        return $row ?: null;
    }

    public function createToken(array $data): ?int
    {
        $required = ['tenant_id', 'token', 'expires_at'];
        foreach ($required as $k) {
            if (!isset($data[$k])) {
                return null;
            }
        }
        $id = $this->insert('queue_qr_tokens', $data);
        return $id ? (int) $id : null;
    }

    public function revokeForTenant(string $tenantId, ?int $exceptId = null): int
    {
        if ($exceptId) {
            $sql = "UPDATE queue_qr_tokens SET is_revoked = 1
                    WHERE tenant_id = :tid AND is_revoked = 0 AND id != :eid";
            $stmt = $this->rawQuery($sql, ['tid' => $tenantId, 'eid' => $exceptId]);
        } else {
            $sql = "UPDATE queue_qr_tokens SET is_revoked = 1
                    WHERE tenant_id = :tid AND is_revoked = 0";
            $stmt = $this->rawQuery($sql, ['tid' => $tenantId]);
        }
        return $stmt ? $stmt->rowCount() : 0;
    }

    public function incrementConsumption(int $id): bool
    {
        $stmt = $this->rawQuery(
            "UPDATE queue_qr_tokens SET consumed_count = consumed_count + 1 WHERE id = :id",
            ['id' => $id]
        );
        return $stmt !== false;
    }

    public function purgeExpired(int $olderThanHours = 24): int
    {
        $stmt = $this->rawQuery(
            "DELETE FROM queue_qr_tokens
             WHERE expires_at < (NOW() - INTERVAL :h HOUR)",
            ['h' => $olderThanHours]
        );
        return $stmt ? $stmt->rowCount() : 0;
    }
}
