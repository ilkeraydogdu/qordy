<?php
namespace App\Controllers\Customer;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;
use App\Core\DependencyFactory;
use App\Core\Logger;

/**
 * Business owner's saved-card management.
 *
 * Historical note:
 *   Eski hâli hem "index()" hem "add()" hem "delete()" metodlarını stub
 *   olarak bırakmıştı; `$paymentMethods = []` dönüp, add/delete sadece
 *   "yakında eklenecektir" flash mesajı gösteriyordu. Gerçekte altyapı
 *   (SavedPaymentMethodRepository + PaymentService::getSavedCards) hazırdı,
 *   sadece kontrolcü tarafı bağlanmamıştı. Aşağıdaki kod;
 *   - listeyi DB'den çekiyor,
 *   - silme işlemini gerçek soft-delete olarak yapıyor,
 *   - "Varsayılan yap" eylemini de gerçek hale getiriyor,
 *   - kart EKLEME akışı iyzico'nun hosted checkout form'u ile mümkün
 *     olduğu için, "add" stub'ı yerine kullanıcıyı paket satın alma
 *     akışına / iyzico card management sayfasına yönlendiren anlamlı
 *     bir flow veriyor. Çiğ kart numarası/CVV toplamıyoruz (PCI).
 */
class PaymentMethodsController extends Controller {

    /** Müşteri bilgilerini oturumdan güvenli şekilde çözer (customer_id). */
    private function resolveCustomerId(): ?string {
        $fromSession = $_SESSION['customer_id'] ?? null;
        if ($fromSession) {
            return (string)$fromSession;
        }
        // E-posta üzerinden çözüm (fallback) — bazı oturumlar customer_id
        // taşımadan açılıyor (ör. eski BUSINESS_MANAGER login path'i).
        $email = $_SESSION['username'] ?? $_SESSION['email'] ?? '';
        if (!$email) return null;
        try {
            $repo = DependencyFactory::getCustomerRepository();
            $row = $repo->findByEmail($email);
            return $row['customer_id'] ?? null;
        } catch (\Throwable $e) {
            Logger::warning('PaymentMethodsController::resolveCustomerId fallback failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * View'in beklediği alan adlarına normalize eder.
     * DB: brand, last4, expiry_month, expiry_year, is_default, gateway
     * View: brand, last4, exp_date ("MM/YY"), is_default, gateway
     */
    private function normalizeCards(array $rows): array {
        $out = [];
        foreach ($rows as $r) {
            $mm = isset($r['expiry_month']) ? str_pad((string)(int)$r['expiry_month'], 2, '0', STR_PAD_LEFT) : '';
            $yy = isset($r['expiry_year'])  ? substr((string)(int)$r['expiry_year'], -2)                   : '';
            $expDate = ($mm && $yy) ? "$mm/$yy" : '';
            $out[] = [
                'saved_card_id' => $r['saved_card_id'] ?? '',
                'gateway'       => $r['gateway']       ?? 'iyzico',
                'brand'         => $r['brand']         ?? '',
                'last4'         => $r['last4']         ?? '',
                'exp_date'      => $expDate,
                'expiry_month'  => $mm,
                'expiry_year'   => $yy,
                'is_default'    => !empty($r['is_default']),
            ];
        }
        return $out;
    }

    public function index() {
        $this->requireLogin();

        $customerId = $this->resolveCustomerId();
        if (!$customerId) {
            $this->toastNotificationService->setFlash('error', 'Müşteri bilgileri bulunamadı');
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }

        $paymentMethods = [];
        try {
            $paymentService = DependencyFactory::getPaymentService();
            $raw = $paymentService->getSavedCards($customerId);
            $paymentMethods = $this->normalizeCards(is_array($raw) ? $raw : []);
        } catch (\Throwable $e) {
            Logger::error('PaymentMethodsController::index load failed', [
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);
        }

        $this->view('customer/payment_methods', [
            'paymentMethods' => $paymentMethods,
            'page'           => 'payment-methods',
        ]);
    }

    public function add() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/business/payment-methods');
            exit;
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        require_once __DIR__ . '/../../core/Security/CSRFManager.php';
        if (!\App\Core\Security\CSRFManager::validateToken($csrfToken)) {
            $this->toastNotificationService->setFlash('error', 'Geçersiz istek');
            header('Location: ' . BASE_URL . '/business/payment-methods');
            exit;
        }

        // PCI: we deliberately do NOT accept raw PAN/CVV here. Saved cards in
        // our DB are populated via iyzico's hosted checkout form (with
        // "cardUserKey" + registerCard=1) during a real purchase. Direct
        // standalone "add card" without an actual payment is not supported
        // by our current gateway wiring. Guide the user to the package
        // purchase flow where they can opt-in to save their card.
        $this->toastNotificationService->setFlash(
            'info',
            'Kart kaydı güvenlik nedeniyle ödeme sırasında yapılır. Bir paket/ödeme işlemi sırasında "Kartımı kaydet" seçeneğini işaretleyerek kartınızı ekleyebilirsiniz.'
        );
        header('Location: ' . BASE_URL . '/customer/packages');
        exit;
    }

    public function delete() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/business/payment-methods');
            exit;
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        require_once __DIR__ . '/../../core/Security/CSRFManager.php';
        if (!\App\Core\Security\CSRFManager::validateToken($csrfToken)) {
            $this->toastNotificationService->setFlash('error', 'Geçersiz istek');
            header('Location: ' . BASE_URL . '/business/payment-methods');
            exit;
        }

        $customerId  = $this->resolveCustomerId();
        $savedCardId = trim((string)($_POST['saved_card_id'] ?? ''));

        if (!$customerId || !$savedCardId) {
            $this->toastNotificationService->setFlash('error', 'Silinecek kart bulunamadı.');
            header('Location: ' . BASE_URL . '/business/payment-methods');
            exit;
        }

        try {
            $repo = DependencyFactory::getSavedPaymentMethodRepository();
            // Sahiplik kontrolü: başka müşterinin kartını soft-delete edemesin.
            $all = $repo->getByCustomerId($customerId);
            $owns = false;
            foreach ($all as $card) {
                if (($card['saved_card_id'] ?? '') === $savedCardId) { $owns = true; break; }
            }
            if (!$owns) {
                $this->toastNotificationService->setFlash('error', 'Bu kart size ait değil.');
                header('Location: ' . BASE_URL . '/business/payment-methods');
                exit;
            }

            $repo->deactivate($savedCardId);
            $this->toastNotificationService->setFlash('success', 'Kart kaldırıldı.');
        } catch (\Throwable $e) {
            Logger::error('PaymentMethodsController::delete failed', [
                'customer_id'   => $customerId,
                'saved_card_id' => $savedCardId,
                'error'         => $e->getMessage(),
            ]);
            $this->toastNotificationService->setFlash('error', 'Kart silinirken bir hata oluştu.');
        }

        header('Location: ' . BASE_URL . '/business/payment-methods');
        exit;
    }

    public function setDefault() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/business/payment-methods');
            exit;
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        require_once __DIR__ . '/../../core/Security/CSRFManager.php';
        if (!\App\Core\Security\CSRFManager::validateToken($csrfToken)) {
            $this->toastNotificationService->setFlash('error', 'Geçersiz istek');
            header('Location: ' . BASE_URL . '/business/payment-methods');
            exit;
        }

        $customerId  = $this->resolveCustomerId();
        $savedCardId = trim((string)($_POST['saved_card_id'] ?? ''));

        if (!$customerId || !$savedCardId) {
            $this->toastNotificationService->setFlash('error', 'Kart bulunamadı.');
            header('Location: ' . BASE_URL . '/business/payment-methods');
            exit;
        }

        try {
            $repo = DependencyFactory::getSavedPaymentMethodRepository();
            $all  = $repo->getByCustomerId($customerId);
            $owns = false;
            foreach ($all as $card) {
                if (($card['saved_card_id'] ?? '') === $savedCardId) { $owns = true; break; }
            }
            if (!$owns) {
                $this->toastNotificationService->setFlash('error', 'Bu kart size ait değil.');
                header('Location: ' . BASE_URL . '/business/payment-methods');
                exit;
            }

            $repo->setDefault($savedCardId, $customerId);
            $this->toastNotificationService->setFlash('success', 'Varsayılan kart güncellendi.');
        } catch (\Throwable $e) {
            Logger::error('PaymentMethodsController::setDefault failed', [
                'customer_id'   => $customerId,
                'saved_card_id' => $savedCardId,
                'error'         => $e->getMessage(),
            ]);
            $this->toastNotificationService->setFlash('error', 'Varsayılan kart ayarlanırken bir hata oluştu.');
        }

        header('Location: ' . BASE_URL . '/business/payment-methods');
        exit;
    }
}
