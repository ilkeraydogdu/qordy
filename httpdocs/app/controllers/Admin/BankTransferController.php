<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class BankTransferController extends Controller {

    protected $bankTransferService;

    public function __construct() {
        parent::__construct();
        $this->bankTransferService = \App\Core\DependencyFactory::getBankTransferService();
    }

    /**
     * List pending bank transfer payments for admin approval
     */
    public function pendingPayments() {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }

        $pendingTransfers = $this->bankTransferService->getPendingTransfers();
        $allTransfers = $this->bankTransferService->getAllTransfers(100);

        $this->view('admin/bank_transfer_approvals', [
            'pendingTransfers' => $pendingTransfers,
            'allTransfers' => $allTransfers,
            'pageTitle' => 'Havale Ödeme Onayları',
            'layout' => 'admin',
            'is_super_admin' => $this->isSuperAdmin()
        ]);
    }

    /**
     * Approve a bank transfer payment (API)
     */
    public function approve($transferId) {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $adminNote = $requestData['admin_note'] ?? null;
        $reviewerId = $_SESSION['user_id'] ?? 'admin';

        $result = $this->bankTransferService->approveTransfer($transferId, $adminNote, $reviewerId);

        if ($result['success']) {
            echo json_encode(['success' => true, 'message' => 'Ödeme onaylandı ve abonelik aktif edildi.'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Onay başarısız.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /**
     * Reject a bank transfer payment (API)
     */
    public function reject($transferId) {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $adminNote = $requestData['admin_note'] ?? null;
        $reviewerId = $_SESSION['user_id'] ?? 'admin';

        $result = $this->bankTransferService->rejectTransfer($transferId, $adminNote, $reviewerId);

        if ($result['success']) {
            echo json_encode(['success' => true, 'message' => 'Ödeme reddedildi ve abonelik iptal edildi.'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Red işlemi başarısız.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /**
     * Delete a bank transfer: receipt file + DB record only (no subscription/customer delete)
     */
    public function deleteTransfer($transferId) {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = $this->bankTransferService->deleteTransfer($transferId);

        if ($result['success']) {
            echo json_encode(['success' => true, 'message' => 'Havale kaydı ve dekont silindi.'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Silme başarısız.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /**
     * View transfer receipt image/PDF
     */
    public function viewReceipt($transferId) {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }

        $transfer = $this->bankTransferService->getTransferById($transferId);
        if (!$transfer || empty($transfer['receipt_file_path'])) {
            http_response_code(404);
            echo 'Dekont bulunamadı.';
            exit;
        }

        $filePath = __DIR__ . '/../../..' . $transfer['receipt_file_path'];
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo 'Dosya bulunamadı.';
            exit;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($filePath);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    // ===== Bank Account Management =====

    public function bankAccounts() {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }

        $accounts = $this->bankTransferService->getAllBankAccounts();
        $this->view('admin/bank_accounts', [
            'accounts' => $accounts,
            'pageTitle' => 'Banka Hesapları',
            'layout' => 'admin'
        ]);
    }

    public function createBankAccount() {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $data = \App\Core\RequestParser::getRequestData();
        unset($data['csrf_token'], $data['_method']);

        if (empty($data['bank_name']) || empty($data['iban']) || empty($data['account_holder'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Banka adı, IBAN ve hesap sahibi zorunludur.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = $this->bankTransferService->createBankAccount($data);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function updateBankAccount($accountId) {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $data = \App\Core\RequestParser::getRequestData();
        unset($data['csrf_token'], $data['_method'], $data['account_id']);

        $result = $this->bankTransferService->updateBankAccount($accountId, $data);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function deleteBankAccount($accountId) {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = $this->bankTransferService->deleteBankAccount($accountId);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
