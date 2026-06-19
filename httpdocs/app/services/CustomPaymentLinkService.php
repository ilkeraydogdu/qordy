<?php
namespace App\Services;

use App\Repositories\CustomPaymentLinkRepository;

/**
 * Business logic for personalized super-admin payment links.
 *
 * A link describes a one-off offer for a specific customer or e-mail
 * at a custom price and duration. Links can be one-shot or
 * multi-use, and they gate access to `/pay/{token}`.
 */
class CustomPaymentLinkService {
    /** @var CustomPaymentLinkRepository */
    protected $repository;

    public function __construct(CustomPaymentLinkRepository $repository) {
        $this->repository = $repository;
    }

    /**
     * Create a new payment link. Returns the persisted row (including
     * `token` and a server-rendered absolute URL).
     *
     * @param array $data {
     *   @type string  $mode             existing_customer|new_customer (required)
     *   @type string  $package_id       (required)
     *   @type float   $custom_price     (required, > 0)
     *   @type int     $duration_months  (required, > 0)
     *   @type string  $customer_id      (required when mode=existing_customer)
     *   @type string  $target_email     (required when mode=new_customer)
     *   @type string  $target_name      (optional)
     *   @type string  $currency         default TRY
     *   @type string  $note             admin-only free text
     *   @type bool    $is_single_use    default true
     *   @type int     $max_uses         default 1
     *   @type string  $expires_at       optional ISO timestamp
     *   @type string  $created_by       admin user id (required)
     * }
     *
     * @return array ['success' => bool, 'link' => array|null, 'url' => string|null, 'error' => string|null]
     */
    public function createLink(array $data): array {
        $mode = $data['mode'] ?? '';
        if (!in_array($mode, ['existing_customer', 'new_customer'], true)) {
            return ['success' => false, 'link' => null, 'url' => null, 'error' => 'Geçersiz mod.'];
        }

        $packageId = trim((string)($data['package_id'] ?? ''));
        if ($packageId === '') {
            return ['success' => false, 'link' => null, 'url' => null, 'error' => 'Paket seçimi zorunludur.'];
        }

        $price = (float)($data['custom_price'] ?? 0);
        if ($price <= 0) {
            return ['success' => false, 'link' => null, 'url' => null, 'error' => 'Özel fiyat 0\'dan büyük olmalıdır.'];
        }

        $duration = (int)($data['duration_months'] ?? 0);
        if ($duration <= 0) {
            return ['success' => false, 'link' => null, 'url' => null, 'error' => 'Süre en az 1 ay olmalıdır.'];
        }

        $createdBy = trim((string)($data['created_by'] ?? ''));
        if ($createdBy === '') {
            return ['success' => false, 'link' => null, 'url' => null, 'error' => 'Oluşturucu bilgisi eksik.'];
        }

        $customerId = null;
        $targetEmail = null;
        $targetName = null;

        if ($mode === 'existing_customer') {
            $customerId = trim((string)($data['customer_id'] ?? ''));
            if ($customerId === '') {
                return ['success' => false, 'link' => null, 'url' => null, 'error' => 'Müşteri seçimi zorunludur.'];
            }
        } else {
            $targetEmail = strtolower(trim((string)($data['target_email'] ?? '')));
            if ($targetEmail === '' || !filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'link' => null, 'url' => null, 'error' => 'Geçerli bir e-posta adresi girin.'];
            }
            $targetName = trim((string)($data['target_name'] ?? ''));
        }

        $isSingleUse = !empty($data['is_single_use']);
        $maxUses = (int)($data['max_uses'] ?? 1);
        if ($isSingleUse) {
            $maxUses = 1;
        } else {
            $maxUses = max(1, $maxUses);
        }

        $linkId = 'cpl_' . bin2hex(random_bytes(8));
        $token  = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');

        $row = [
            'link_id'         => $linkId,
            'token'           => $token,
            'mode'            => $mode,
            'customer_id'     => $customerId,
            'target_email'    => $targetEmail,
            'target_name'     => $targetName,
            'package_id'      => $packageId,
            'custom_price'    => number_format($price, 2, '.', ''),
            'duration_months' => $duration,
            'currency'        => $data['currency'] ?? 'TRY',
            'note'            => $data['note'] ?? null,
            'is_single_use'   => $isSingleUse ? 1 : 0,
            'max_uses'        => $maxUses,
            'used_count'      => 0,
            'is_active'       => 1,
            'expires_at'      => !empty($data['expires_at']) ? $data['expires_at'] : null,
            'created_by'      => $createdBy,
        ];

        $ok = $this->repository->insert($row);
        if (!$ok) {
            return ['success' => false, 'link' => null, 'url' => null, 'error' => 'Bağlantı kaydedilemedi.'];
        }

        return [
            'success' => true,
            'link'    => $this->repository->findById($linkId),
            'url'     => $this->buildUrl($token),
            'error'   => null,
        ];
    }

    public function getByToken(string $token): ?array {
        return $this->repository->findByToken($token);
    }

    public function getById(string $linkId): ?array {
        return $this->repository->findById($linkId);
    }

    public function listAll(array $filters = [], int $limit = 200, int $offset = 0): array {
        return $this->repository->listAll($filters, $limit, $offset);
    }

    /**
     * Validate whether a link is currently usable. Does not mutate
     * the link; call `markConsumed()` after a successful payment.
     *
     * @return array ['ok' => bool, 'reason' => string]
     */
    public function canConsume(array $link): array {
        if (!$link) {
            return ['ok' => false, 'reason' => 'Bağlantı bulunamadı.'];
        }
        if ((int)$link['is_active'] !== 1) {
            return ['ok' => false, 'reason' => 'Bu ödeme bağlantısı artık aktif değil.'];
        }
        $used = (int)$link['used_count'];
        $max  = (int)$link['max_uses'];
        if ($max > 0 && $used >= $max) {
            return ['ok' => false, 'reason' => 'Bu ödeme bağlantısı kullanım limitine ulaşmış.'];
        }
        if (!empty($link['expires_at'])) {
            $expiresTs = strtotime($link['expires_at']);
            if ($expiresTs !== false && $expiresTs < time()) {
                return ['ok' => false, 'reason' => 'Bu ödeme bağlantısının süresi dolmuş.'];
            }
        }
        return ['ok' => true, 'reason' => ''];
    }

    public function markConsumed(string $linkId): bool {
        return $this->repository->markConsumed($linkId);
    }

    public function revoke(string $linkId): bool {
        return $this->repository->setActive($linkId, false);
    }

    public function reactivate(string $linkId): bool {
        return $this->repository->setActive($linkId, true);
    }

    public function setReusable(string $linkId, bool $reusable, int $maxUses = 1): bool {
        return $this->repository->setReusable($linkId, $reusable, $maxUses);
    }

    public function delete(string $linkId): bool {
        return $this->repository->delete($linkId);
    }

    /**
     * Build the absolute public URL for a given token. Falls back to
     * the current host when BASE_URL is not configured.
     */
    public function buildUrl(string $token): string {
        $base = defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '';
        if ($base === '') {
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'qordy.com';
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = $proto . '://' . $host;
        }
        return $base . '/pay/' . $token;
    }
}
