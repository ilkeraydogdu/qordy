<?php
namespace App\Controllers\Business;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

class AccountController extends \App\Core\Controller {
    
    public function profile() {
        $this->requireLogin();
        $this->requirePermission('account.view');
        
        $customerId = $_SESSION['customer_id'] ?? null;
        if (!$customerId) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
        
        $customerService = \App\Core\DependencyFactory::getCustomerService();
        $customer = $customerService->getById($customerId);
        
        $this->view('business/account/profile', [
            'title' => 'Profil',
            'customer' => $customer
        ]);
    }
    
    public function payments() {
        $this->requireLogin();
        $this->requirePermission('payments.view');
        
        $customerId = $_SESSION['customer_id'] ?? null;
        if (!$customerId) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
        
        $subscriptionPaymentRepository = \App\Core\DependencyFactory::getSubscriptionPaymentRepository();
        $payments = $subscriptionPaymentRepository->getByCustomerId($customerId);
        
        $this->view('business/account/payments', [
            'title' => 'Ödeme Geçmişi',
            'payments' => $payments
        ]);
    }
    
    public function subscription() {
        $this->requireLogin();
        $this->requirePermission('account.view');

        $customerId = $_SESSION['customer_id'] ?? null;
        if (!$customerId) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
        $subscription = $subscriptionService->getCustomerSubscription($customerId);

        $packageService = \App\Core\DependencyFactory::getPackageService();
        $package = null;
        if ($subscription && !empty($subscription['package_id'])) {
            $package = $packageService->getById($subscription['package_id']);
        }

        // Satın alma / abonelik geçmişi + ödeme kalemleri.
        $history = [];
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $sql = "SELECT s.subscription_id, s.package_id, s.amount, s.currency,
                           s.billing_cycle, s.status, s.is_trial, s.trial_ends_at,
                           s.current_period_start, s.current_period_end,
                           s.created_at, s.cancelled_at,
                           p.name AS package_name
                    FROM subscriptions s
                    LEFT JOIN packages p ON p.package_id = s.package_id
                    WHERE s.tenant_id = :cid
                    ORDER BY s.created_at DESC
                    LIMIT 100";
            $stmt = $db->prepare($sql);
            $stmt->execute(['cid' => $customerId]);
            $subs = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $subIds = array_column($subs, 'subscription_id');
            $paymentsBySub = [];
            if (!empty($subIds)) {
                $in = implode(',', array_fill(0, count($subIds), '?'));
                $pStmt = $db->prepare(
                    "SELECT payment_id, subscription_id, amount, currency,
                            payment_method, payment_status, gateway_transaction_id,
                            payment_date, created_at
                     FROM subscription_payments
                     WHERE subscription_id IN ($in)
                     ORDER BY COALESCE(payment_date, created_at) DESC"
                );
                $pStmt->execute($subIds);
                foreach ($pStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                    $paymentsBySub[$row['subscription_id']][] = $row;
                }
            }
            foreach ($subs as $s) {
                $s['payments'] = $paymentsBySub[$s['subscription_id']] ?? [];
                $history[] = $s;
            }
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('AccountController::subscription history failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->view('business/account/subscription', [
            'title' => 'Paket Bilgim',
            'subscription' => $subscription,
            'package' => $package,
            'history' => $history,
        ]);
    }
}
