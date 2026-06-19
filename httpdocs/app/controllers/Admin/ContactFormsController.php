<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;
use App\Services\ContactFormService;

class ContactFormsController extends Controller {
    protected $contactFormService;
    
    public function __construct() {
        parent::__construct();
        $this->contactFormService = new ContactFormService();
    }
    
    /**
     * List all contact forms
     */
    public function index() {
        // CRITICAL: Ensure tenant context is set (unless super admin)
        $isSuperAdmin = $this->isSuperAdmin();
        if (!$isSuperAdmin) {
            $this->ensureTenantContext();
        }
        
        // Require login
        $this->requireLogin();
        
        // Check permission
        if (!$isSuperAdmin && !$this->hasPermission('admin.contact_forms.view') && !$this->hasRole('ADMIN') && !$this->hasRole('ADMINISTRATOR')) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        $status = $_GET['status'] ?? 'all';
        
        if ($status === 'new') {
            $contactForms = $this->contactFormService->getNew();
        } elseif ($status === 'all') {
            $contactForms = $this->contactFormService->getAll();
        } else {
            $contactForms = $this->contactFormService->getByStatus($status);
        }
        
        // Get counts for status badges
        $allCount = count($this->contactFormService->getAll());
        $newCount = count($this->contactFormService->getNew());
        $contactedCount = count($this->contactFormService->getByStatus('contacted'));
        $closedCount = count($this->contactFormService->getByStatus('closed'));
        
        $this->view('admin/contact_forms', [
            'contactForms' => $contactForms,
            'status' => $status,
            'allCount' => $allCount,
            'newCount' => $newCount,
            'contactedCount' => $contactedCount,
            'closedCount' => $closedCount,
            'is_super_admin' => $isSuperAdmin
        ]);
    }
    
    /**
     * View single contact form
     * Note: This method name conflicts with Controller::view() - using show() instead
     */
    public function show($contactId = null) {
        // CRITICAL: Ensure tenant context is set (unless super admin)
        $isSuperAdmin = $this->isSuperAdmin();
        if (!$isSuperAdmin) {
            $this->ensureTenantContext();
        }
        
        if (!$contactId) {
            header('Location: ' . BASE_URL . '/qodmin/contact-forms');
            exit;
        }
        
        // Check permission - Super Admin bypass
        if (!$isSuperAdmin && !$this->hasPermission('admin.contact_forms.view') && !$this->hasRole('ADMIN') && !$this->hasRole('ADMINISTRATOR')) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        $contactForm = $this->contactFormService->getById($contactId);
        
        if (!$contactForm) {
            header('Location: ' . BASE_URL . '/qodmin/contact-forms');
            exit;
        }
        
        // CRITICAL: Verify tenant isolation (unless super admin)
        if (!$isSuperAdmin) {
            $tenantId = \App\Core\TenantContext::getId();
            $contactBusinessId = $contactForm['business_id'] ?? null;
            
            if ($contactBusinessId && $contactBusinessId !== $tenantId) {
                \App\Core\Logger::warning('Admin/ContactFormsController::show - Tenant isolation violation', [
                    'contact_id' => $contactId,
                    'contact_business_id' => $contactBusinessId,
                    'tenant_id' => $tenantId
                ]);
                header('Location: ' . BASE_URL . '/qodmin/contact-forms');
                exit;
            }
        }
        
        $this->view('admin/contact_form_detail', [
            'contactForm' => $contactForm
        ]);
    }
    
    /**
     * View single contact form (alias for show - route compatibility)
     */
    public function viewContact($contactId = null) {
        $this->show($contactId);
    }
    
    /**
     * Update contact form status (API endpoint)
     */
    public function updateStatus() {
        // Require login
        $this->requireLogin();
        
        // Super Admin bypass
        $isSuperAdmin = $this->isSuperAdmin();
        
        // Check permission
        if (!$isSuperAdmin && !$this->hasPermission('admin.contact_forms.update') && !$this->hasRole('ADMIN') && !$this->hasRole('ADMINISTRATOR')) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $contactId = $input['contact_id'] ?? null;
        $status = $input['status'] ?? null;
        $notes = $input['notes'] ?? null;
        
        if (!$contactId || !$status) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Missing required fields'
            ], 400);
        }
        
        if (!in_array($status, ['new', 'contacted', 'closed'])) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Invalid status'
            ], 400);
        }
        
        $result = $this->contactFormService->updateStatus($contactId, $status, $notes);
        
        if ($result) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Durum başarıyla güncellendi'
            ]);
        } else {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Durum güncellenirken bir hata oluştu'
            ], 500);
        }
    }
    
    /**
     * Delete contact form (API endpoint)
     */
    public function delete() {
        // Require login
        $this->requireLogin();
        
        // Super Admin bypass
        $isSuperAdmin = $this->isSuperAdmin();
        
        // Check permission
        if (!$isSuperAdmin && !$this->hasPermission('admin.contact_forms.delete') && !$this->hasRole('ADMIN') && !$this->hasRole('ADMINISTRATOR')) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $contactId = $input['contact_id'] ?? null;
        
        if (!$contactId) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Missing contact ID'
            ], 400);
        }
        
        $result = $this->contactFormService->delete($contactId);
        
        if ($result) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'İletişim formu başarıyla silindi'
            ]);
        } else {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Silme işlemi sırasında bir hata oluştu'
            ], 500);
        }
    }
    
    /**
     * Send reply email (API endpoint)
     */
    public function sendReply() {
        // Require login
        $this->requireLogin();
        
        // Super Admin bypass
        $isSuperAdmin = $this->isSuperAdmin();
        
        // Check permission
        if (!$isSuperAdmin && !$this->hasPermission('admin.contact_forms.reply') && !$this->hasRole('ADMIN') && !$this->hasRole('ADMINISTRATOR')) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $contactId = $input['contact_id'] ?? null;
        $replyMessage = $input['reply_message'] ?? null;
        
        if (!$contactId || !$replyMessage) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Eksik alanlar var'
            ], 400);
        }
        
        $result = $this->contactFormService->sendReply($contactId, $replyMessage);
        
        if ($result['success']) {
            return $this->jsonResponse([
                'success' => true,
                'message' => $result['message']
            ]);
        } else {
            return $this->jsonResponse([
                'success' => false,
                'message' => $result['message']
            ], 500);
        }
    }
    
    /**
     * Improve text with Gemini AI (API endpoint)
     */
    public function improveText() {
        // Require login
        $this->requireLogin();
        
        // Super Admin bypass
        $isSuperAdmin = $this->isSuperAdmin();
        
        // Check permission
        if (!$isSuperAdmin && !$this->hasPermission('admin.contact_forms.view') && !$this->hasRole('ADMIN') && !$this->hasRole('ADMINISTRATOR')) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $text = $input['text'] ?? null;
        
        if (!$text) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Metin boş olamaz'
            ], 400);
        }
        
        $result = $this->contactFormService->improveTextWithGemini($text);
        
        if ($result['success']) {
            return $this->jsonResponse([
                'success' => true,
                'improved_text' => $result['improved_text']
            ]);
        } else {
            return $this->jsonResponse([
                'success' => false,
                'message' => $result['message']
            ], 500);
        }
    }
}
