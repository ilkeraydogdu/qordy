<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class LegalPageController extends Controller {
    
    protected $legalPageService;
    
    public function __construct() {
        parent::__construct();
        $this->legalPageService = \App\Core\DependencyFactory::getLegalPageService();
        if (!function_exists('getAdminUrl')) {
            require_once __DIR__ . '/../../helpers/url_helper.php';
        }
    }
    
    public function index() {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        $pages = $this->legalPageService->getAll();
        
        $this->render('admin/legal_pages', [
            'pages' => $pages,
            'pageTypes' => $this->legalPageService->getPageTypes(),
            'title' => 'Hukuksal Sayfalar',
            'is_super_admin' => true,
        ]);
    }
    
    public function create() {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        $this->render('admin/legal_page_form', [
            'page' => null,
            'isEdit' => false,
            'pageTypes' => $this->legalPageService->getPageTypes(),
            'title' => 'Yeni Sayfa Oluştur',
            'is_super_admin' => true,
        ]);
    }
    
    public function store() {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        $data = \App\Core\RequestParser::getRequestData();
        
        $pageData = [
            'title' => trim($data['title'] ?? ''),
            'slug' => trim($data['slug'] ?? ''),
            'content' => $data['content'] ?? '',
            'meta_description' => trim($data['meta_description'] ?? ''),
            'page_type' => $data['page_type'] ?? 'custom',
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'show_in_footer' => isset($data['show_in_footer']) ? 1 : 0,
            'show_in_register' => isset($data['show_in_register']) ? 1 : 0,
            'footer_group' => $data['footer_group'] ?? 'legal',
            'display_order' => (int)($data['display_order'] ?? 0),
            'updated_by' => $_SESSION['email'] ?? 'admin',
        ];
        
        if (empty($pageData['title'])) {
            $this->toastNotificationService->setFlash('error', 'Sayfa başlığı zorunludur.');
            header('Location: ' . getAdminUrl('legal-pages/create'));
            exit;
        }
        
        $id = $this->legalPageService->create($pageData);
        
        if ($id) {
            $this->toastNotificationService->setFlash('success', 'Sayfa başarıyla oluşturuldu.');
            header('Location: ' . getAdminUrl('legal-pages'));
        } else {
            $this->toastNotificationService->setFlash('error', 'Sayfa oluşturulurken hata oluştu.');
            header('Location: ' . getAdminUrl('legal-pages/create'));
        }
        exit;
    }
    
    public function edit($id) {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        $page = $this->legalPageService->getById((int)$id);
        if (!$page) {
            $this->toastNotificationService->setFlash('error', 'Sayfa bulunamadı.');
            header('Location: ' . getAdminUrl('legal-pages'));
            exit;
        }
        
        $this->render('admin/legal_page_form', [
            'page' => $page,
            'isEdit' => true,
            'pageTypes' => $this->legalPageService->getPageTypes(),
            'title' => 'Sayfa Düzenle: ' . $page['title'],
            'is_super_admin' => true,
        ]);
    }
    
    public function update($id) {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        $data = \App\Core\RequestParser::getRequestData();
        
        $pageData = [
            'title' => trim($data['title'] ?? ''),
            'slug' => trim($data['slug'] ?? ''),
            'content' => $data['content'] ?? '',
            'meta_description' => trim($data['meta_description'] ?? ''),
            'page_type' => $data['page_type'] ?? 'custom',
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'show_in_footer' => isset($data['show_in_footer']) ? 1 : 0,
            'show_in_register' => isset($data['show_in_register']) ? 1 : 0,
            'footer_group' => $data['footer_group'] ?? 'legal',
            'display_order' => (int)($data['display_order'] ?? 0),
            'updated_by' => $_SESSION['email'] ?? 'admin',
        ];
        
        $result = $this->legalPageService->update((int)$id, $pageData);
        
        if ($result) {
            $this->toastNotificationService->setFlash('success', 'Sayfa başarıyla güncellendi.');
        } else {
            $this->toastNotificationService->setFlash('error', 'Güncelleme sırasında hata oluştu.');
        }
        
        header('Location: ' . getAdminUrl('legal-pages/' . $id . '/edit'));
        exit;
    }
    
    public function destroy($id) {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            \App\Core\ApiResponseHelper::error('Yetkisiz', 403);
            return;
        }
        
        $result = $this->legalPageService->delete((int)$id);
        
        if ($result) {
            \App\Core\ApiResponseHelper::success([], 'Sayfa silindi.');
        } else {
            \App\Core\ApiResponseHelper::error('Silinemedi.', 400);
        }
    }
    
    public function toggle($id) {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            \App\Core\ApiResponseHelper::error('Yetkisiz', 403);
            return;
        }
        
        $result = $this->legalPageService->toggleActive((int)$id);
        
        if ($result) {
            \App\Core\ApiResponseHelper::success([], 'Durum değiştirildi.');
        } else {
            \App\Core\ApiResponseHelper::error('Hata oluştu.', 400);
        }
    }
}
