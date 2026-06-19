<?php
namespace App\Controllers\Customer;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;
use App\Core\Logger;

/**
 * JSON API backing the in-app "special offer" popup and the Flutter
 * bottom sheet. Returns all ACTIVE personalized payment links that
 * apply to the current authenticated customer, enriched with basic
 * package info, the public /pay/{token} URL, and a per-customer
 * dismissed timestamp so the UI can enforce a re-prompt cooldown.
 */
class CustomOfferApiController extends Controller {
    /** How many minutes must pass after a dismiss before we re-prompt. */
    public const POPUP_COOLDOWN_MINUTES = 45;

    protected function jsonResponse(array $payload, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function resolveCurrentCustomer(): ?array {
        if (empty($_SESSION['logged_in'])) {
            return null;
        }
        $customerId = \App\Core\TenantResolver::resolve();
        if (!$customerId) {
            return null;
        }
        try {
            $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
            $customer = $customerRepo->findById($customerId);
            if (!$customer) {
                return null;
            }
            return [
                'customer_id' => $customer['customer_id'] ?? $customerId,
                'email'       => strtolower((string)($customer['email'] ?? '')),
            ];
        } catch (\Throwable $e) {
            Logger::warning('CustomOfferApiController: customer lookup failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * GET /api/customer/custom-offers
     *
     * Returns:
     * {
     *   success: true,
     *   offers: [
     *     {
     *       link_id, token, public_url, mode, package_id, package_name,
     *       package_description, custom_price, currency, duration_months,
     *       note, expires_at, created_at,
     *       dismissed_at,           // müşteri son ne zaman kapattı
     *       cooldown_until,         // bu tarihten önce popup gösterme
     *       should_show_popup       // true ise JS popup açsın
     *     }, ...
     *   ],
     *   cooldown_minutes: 45
     * }
     */
    public function list() {
        $customer = $this->resolveCurrentCustomer();
        if (!$customer) {
            $this->jsonResponse(['success' => false, 'error' => 'not_authenticated', 'offers' => []], 401);
        }

        try {
            $linkRepo      = \App\Core\DependencyFactory::getCustomPaymentLinkRepository();
            $dismissalRepo = \App\Core\DependencyFactory::getCustomPaymentLinkDismissalRepository();
            $linkService   = \App\Core\DependencyFactory::getCustomPaymentLinkService();

            $links = $linkRepo->findActiveForCustomer(
                (string)$customer['customer_id'],
                $customer['email'] ?: null
            );

            $dismissals = $dismissalRepo->getAllForCustomer((string)$customer['customer_id']);

            $cooldownSec = self::POPUP_COOLDOWN_MINUTES * 60;
            $now = time();

            $out = [];
            foreach ($links as $l) {
                $check = $linkService->canConsume($l);
                if (!$check['ok']) {
                    continue;
                }
                $linkId = (string)$l['link_id'];
                $dismissedAt = $dismissals[$linkId] ?? null;
                $dismissedTs = $dismissedAt ? strtotime($dismissedAt) : 0;
                $cooldownUntilTs = $dismissedTs ? ($dismissedTs + $cooldownSec) : 0;
                $shouldShow = $cooldownUntilTs === 0 || $cooldownUntilTs <= $now;

                $out[] = [
                    'link_id'             => $linkId,
                    'token'               => (string)$l['token'],
                    'public_url'          => $linkService->buildUrl((string)$l['token']),
                    'mode'                => $l['mode'],
                    'package_id'          => $l['package_id'],
                    'package_name'        => $l['package_name'] ?? 'Özel Paket',
                    'package_description' => $l['package_description'] ?? null,
                    'custom_price'        => (float)$l['custom_price'],
                    'currency'            => $l['currency'] ?? 'TRY',
                    'duration_months'     => (int)$l['duration_months'],
                    'note'                => $l['note'] ?? null,
                    'expires_at'          => $l['expires_at'] ?? null,
                    'created_at'          => $l['created_at'] ?? null,
                    'dismissed_at'        => $dismissedAt,
                    'cooldown_until'      => $cooldownUntilTs > 0 ? date('c', $cooldownUntilTs) : null,
                    'should_show_popup'   => $shouldShow,
                ];
            }

            $this->jsonResponse([
                'success'          => true,
                'offers'           => $out,
                'cooldown_minutes' => self::POPUP_COOLDOWN_MINUTES,
            ]);
        } catch (\Throwable $e) {
            Logger::error('CustomOfferApiController::list failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->jsonResponse(['success' => false, 'error' => 'server_error', 'offers' => []], 500);
        }
    }

    /**
     * POST /api/customer/custom-offers/{link_id}/dismiss
     */
    public function dismiss($linkId = '') {
        $customer = $this->resolveCurrentCustomer();
        if (!$customer) {
            $this->jsonResponse(['success' => false, 'error' => 'not_authenticated'], 401);
        }
        $linkId = trim((string)$linkId);
        if ($linkId === '') {
            $this->jsonResponse(['success' => false, 'error' => 'invalid_link'], 400);
        }

        try {
            $linkRepo      = \App\Core\DependencyFactory::getCustomPaymentLinkRepository();
            $dismissalRepo = \App\Core\DependencyFactory::getCustomPaymentLinkDismissalRepository();

            $link = $linkRepo->findById($linkId);
            if (!$link) {
                $this->jsonResponse(['success' => false, 'error' => 'link_not_found'], 404);
            }
            // Ownership guard: ya existing_customer link'i o müşteriye ait olmalı
            // ya da new_customer modunda target_email eşleşmeli.
            $ownedByMe = false;
            if (($link['mode'] ?? '') === 'existing_customer'
                && ($link['customer_id'] ?? '') === $customer['customer_id']) {
                $ownedByMe = true;
            }
            if (($link['mode'] ?? '') === 'new_customer'
                && strtolower((string)($link['target_email'] ?? '')) === $customer['email']) {
                $ownedByMe = true;
            }
            if (!$ownedByMe) {
                $this->jsonResponse(['success' => false, 'error' => 'forbidden'], 403);
            }

            $dismissalRepo->dismiss($linkId, (string)$customer['customer_id']);
            $this->jsonResponse([
                'success'        => true,
                'cooldown_until' => date('c', time() + self::POPUP_COOLDOWN_MINUTES * 60),
            ]);
        } catch (\Throwable $e) {
            Logger::error('CustomOfferApiController::dismiss failed', [
                'error' => $e->getMessage(),
            ]);
            $this->jsonResponse(['success' => false, 'error' => 'server_error'], 500);
        }
    }

    /**
     * GET /api/customer/purchase-history
     *
     * Müşterinin abonelik + ödeme geçmişini döner. Web geçmiş sayfası
     * ve Flutter "Satın Alma Geçmişi" ekranı tarafından tüketilir.
     */
    public function history() {
        $customer = $this->resolveCurrentCustomer();
        if (!$customer) {
            $this->jsonResponse(['success' => false, 'error' => 'not_authenticated'], 401);
        }

        try {
            $db = \App\Core\DependencyFactory::getDatabase();

            $sql = "SELECT s.subscription_id, s.package_id, s.amount, s.currency,
                           s.billing_cycle, s.status, s.is_trial, s.trial_ends_at,
                           s.current_period_start, s.current_period_end,
                           s.created_at, s.updated_at, s.cancelled_at,
                           p.name AS package_name
                    FROM subscriptions s
                    LEFT JOIN packages p ON p.package_id = s.package_id
                    WHERE s.tenant_id = :cid
                    ORDER BY s.created_at DESC
                    LIMIT 100";
            $stmt = $db->prepare($sql);
            $stmt->execute(['cid' => $customer['customer_id']]);
            $subs = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $subIds = array_column($subs, 'subscription_id');
            $payments = [];
            if (!empty($subIds)) {
                $in = implode(',', array_fill(0, count($subIds), '?'));
                $paySql = "SELECT payment_id, subscription_id, amount, currency,
                                  payment_method, payment_status, gateway_transaction_id,
                                  payment_date, created_at
                           FROM subscription_payments
                           WHERE subscription_id IN ($in)
                           ORDER BY COALESCE(payment_date, created_at) DESC";
                $payStmt = $db->prepare($paySql);
                $payStmt->execute($subIds);
                $rows = $payStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $r) {
                    $payments[$r['subscription_id']][] = $r;
                }
            }

            $out = [];
            foreach ($subs as $s) {
                $out[] = [
                    'subscription_id'      => $s['subscription_id'],
                    'package_id'           => $s['package_id'],
                    'package_name'         => $s['package_name'] ?? 'Paket',
                    'amount'               => (float)$s['amount'],
                    'currency'             => $s['currency'] ?? 'TRY',
                    'billing_cycle'        => $s['billing_cycle'],
                    'status'               => $s['status'],
                    'is_trial'             => !empty($s['is_trial']),
                    'trial_ends_at'        => $s['trial_ends_at'],
                    'current_period_start' => $s['current_period_start'],
                    'current_period_end'   => $s['current_period_end'],
                    'created_at'           => $s['created_at'],
                    'cancelled_at'         => $s['cancelled_at'],
                    'payments'             => $payments[$s['subscription_id']] ?? [],
                ];
            }

            $this->jsonResponse([
                'success' => true,
                'history' => $out,
            ]);
        } catch (\Throwable $e) {
            Logger::error('CustomOfferApiController::history failed', [
                'error' => $e->getMessage(),
            ]);
            $this->jsonResponse(['success' => false, 'error' => 'server_error', 'history' => []], 500);
        }
    }
}
