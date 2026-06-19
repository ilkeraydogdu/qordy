<?php
namespace App\Services;

use App\Repositories\BankTransferPaymentRepository;
use App\Repositories\BankAccountRepository;

class BankTransferService {
    
    private $transferRepo;
    private $bankAccountRepo;
    
    public function __construct(BankTransferPaymentRepository $transferRepo, BankAccountRepository $bankAccountRepo) {
        $this->transferRepo = $transferRepo;
        $this->bankAccountRepo = $bankAccountRepo;
    }

    /**
     * Generate a unique transfer code: QORDY-{username_prefix}-{4digits}
     */
    public function generateUniqueCode(string $customerEmail): string {
        $prefix = strtoupper(substr(explode('@', $customerEmail)[0], 0, 5));
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix);
        if (empty($prefix)) $prefix = 'USER';

        $maxAttempts = 10;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $random = str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            $code = "QORDY-{$prefix}-{$random}";
            $existing = $this->transferRepo->getByUniqueCode($code);
            if (!$existing) {
                return $code;
            }
        }
        // Fallback with timestamp
        return "QORDY-{$prefix}-" . substr(time(), -4);
    }

    /**
     * Create a bank transfer payment record
     */
    public function createTransfer(array $data): array {
        try {
            require_once __DIR__ . '/../helpers/functions.php';
            $data['transfer_id'] = generateId('btpay');

            $result = $this->transferRepo->create($data);
            if ($result) {
                return ['success' => true, 'transfer_id' => $data['transfer_id'], 'unique_code' => $data['unique_code']];
            }
            return ['success' => false, 'error' => 'Havale kaydı oluşturulamadı.'];
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('BankTransferService::createTransfer error', ['error' => $e->getMessage()]);
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upload receipt file for a transfer
     */
    public function uploadReceipt(string $transferId, array $file): array {
        try {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if ($file['error'] !== UPLOAD_ERR_OK) {
                return ['success' => false, 'error' => 'Dosya yükleme hatası.'];
            }
            if ($file['size'] > $maxSize) {
                return ['success' => false, 'error' => 'Dosya boyutu 5MB\'ı geçemez.'];
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (!in_array($mime, $allowedMimes)) {
                return ['success' => false, 'error' => 'Geçersiz dosya türü. Sadece JPG, PNG, GIF, WebP ve PDF kabul edilir.'];
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'receipt_' . $transferId . '_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../../uploads/receipts/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $destPath = $uploadDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                return ['success' => false, 'error' => 'Dosya kaydedilemedi.'];
            }

            $relativePath = '/uploads/receipts/' . $filename;
            $this->transferRepo->update($transferId, ['receipt_file_path' => $relativePath]);

            return ['success' => true, 'path' => $relativePath];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Approve a bank transfer → activate subscription
     */
    public function approveTransfer(string $transferId, ?string $adminNote, string $reviewerId): array {
        try {
            $transfer = $this->transferRepo->findById($transferId);
            if (!$transfer) {
                return ['success' => false, 'error' => 'Havale kaydı bulunamadı.'];
            }
            if ($transfer['status'] !== 'pending') {
                return ['success' => false, 'error' => 'Bu havale zaten işlenmiş.'];
            }

            $updated = $this->transferRepo->updateStatus($transferId, 'approved', $adminNote, $reviewerId);
            if (!$updated) {
                return ['success' => false, 'error' => 'Durum güncellenemedi.'];
            }

            // Activate subscription
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            $activateResult = $subscriptionService->activateSubscription($transfer['subscription_id']);

            if (!$activateResult['success']) {
                return ['success' => false, 'error' => 'Abonelik aktif edilemedi: ' . ($activateResult['error'] ?? '')];
            }

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Reject a bank transfer → cancel subscription
     */
    public function rejectTransfer(string $transferId, ?string $adminNote, string $reviewerId): array {
        try {
            $transfer = $this->transferRepo->findById($transferId);
            if (!$transfer) {
                return ['success' => false, 'error' => 'Havale kaydı bulunamadı.'];
            }
            if ($transfer['status'] !== 'pending') {
                return ['success' => false, 'error' => 'Bu havale zaten işlenmiş.'];
            }

            $updated = $this->transferRepo->updateStatus($transferId, 'rejected', $adminNote, $reviewerId);
            if (!$updated) {
                return ['success' => false, 'error' => 'Durum güncellenemedi.'];
            }

            // Cancel subscription
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            $subscriptionService->cancelSubscription($transfer['subscription_id']);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getPendingTransfers(): array {
        return $this->transferRepo->getPendingTransfers();
    }

    public function getAllTransfers(int $limit = 50, int $offset = 0): array {
        return $this->transferRepo->getAllTransfers($limit, $offset);
    }

    public function getTransferById(string $transferId): ?array {
        return $this->transferRepo->findById($transferId);
    }

    /**
     * Delete a single transfer: remove receipt file (if any) and delete DB record.
     * Does not touch subscriptions, customers or other data.
     */
    public function deleteTransfer(string $transferId): array {
        try {
            $transfer = $this->transferRepo->findById($transferId);
            if (!$transfer) {
                return ['success' => false, 'error' => 'Havale kaydı bulunamadı.'];
            }

            $basePath = realpath(__DIR__ . '/../..') ?: (__DIR__ . '/../..');
            if (!empty($transfer['receipt_file_path'])) {
                $fullPath = $basePath . '/' . ltrim($transfer['receipt_file_path'], '/');
                if (file_exists($fullPath) && is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }

            $deleted = $this->transferRepo->delete($transferId);
            return $deleted ? ['success' => true] : ['success' => false, 'error' => 'Kayıt silinemedi.'];
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('BankTransferService::deleteTransfer', ['error' => $e->getMessage(), 'transferId' => $transferId]);
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get transfers for a customer (for mobile app)
     */
    public function getTransfersByCustomerId(string $customerId, ?string $status = null): array {
        return $this->transferRepo->getByCustomerId($customerId, $status);
    }

    public function getActiveBankAccounts(): array {
        return $this->bankAccountRepo->getActiveAccounts();
    }

    public function getAllBankAccounts(): array {
        return $this->bankAccountRepo->getAll();
    }

    public function createBankAccount(array $data): array {
        try {
            require_once __DIR__ . '/../helpers/functions.php';
            $data['account_id'] = generateId('bacc');
            $result = $this->bankAccountRepo->create($data);
            return $result ? ['success' => true, 'account_id' => $data['account_id']] : ['success' => false, 'error' => 'Kayıt oluşturulamadı.'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function updateBankAccount(string $accountId, array $data): array {
        try {
            if (empty($accountId)) {
                return ['success' => false, 'error' => 'Hesap ID gerekli.'];
            }
            if (empty($data['bank_name']) || empty($data['iban']) || empty($data['account_holder'])) {
                return ['success' => false, 'error' => 'Banka adı, IBAN ve hesap sahibi zorunludur.'];
            }
            $result = $this->bankAccountRepo->update($accountId, $data);
            return $result ? ['success' => true] : ['success' => false, 'error' => 'Kayıt güncellenemedi.'];
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('BankTransferService::updateBankAccount', ['error' => $e->getMessage(), 'accountId' => $accountId]);
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function deleteBankAccount(string $accountId): array {
        try {
            $result = $this->bankAccountRepo->delete($accountId);
            return $result ? ['success' => true] : ['success' => false, 'error' => 'Silme başarısız.'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
