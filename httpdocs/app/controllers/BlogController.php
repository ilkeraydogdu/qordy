<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

class BlogController extends \App\Core\Controller {
    protected $blogService;
    
    public function __construct() {
        // Blog is public, skip authentication but initialize session
        \App\Core\SessionManager::ensureSession(true);
        \App\Core\HelperLoader::ensureLoaded();
        
        // Initialize services without parent constructor (which requires auth)
        try {
            require_once __DIR__ . '/../helpers/functions.php';
            $this->translationService = getTranslationService();
            $this->seoService = getSEOService();
        } catch (\Exception $e) {
            // Services are optional for blog
        }
        
        try {
            $this->blogService = \App\Core\DependencyFactory::getBlogService();
        } catch (\Exception $e) {
            // If service fails, log but don't break
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('BlogController: Failed to initialize blog service', ['error' => $e->getMessage()]);
            }
        }
    }
    
    public function index() {
        if (!$this->blogService) {
            http_response_code(500);
            echo "Blog servisi kullanılamıyor.";
            exit;
        }
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        try {
            $data = [
                'posts' => $this->blogService->getPublished($limit, $offset),
                'categories' => $this->blogService->getCategories(),
                'currentPage' => $page,
                'totalPosts' => $this->blogService->getRepository()->getPublishedCount()
            ];
            
            // Use simple view rendering for public blog
            $viewPath = __DIR__ . '/../views/blog/index.php';
            if (file_exists($viewPath)) {
                extract($data);
                require $viewPath;
            } else {
                echo "Blog view dosyası bulunamadı.";
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo "Bir hata oluştu: " . htmlspecialchars($e->getMessage());
        }
    }
    
    public function post($slug) {
        if (!$this->blogService) {
            http_response_code(500);
            echo "Blog servisi kullanılamıyor.";
            exit;
        }
        
        $post = $this->blogService->getBySlug($slug);
        
        if (!$post) {
            http_response_code(404);
            echo "Blog yazısı bulunamadı.";
            exit;
        }
        
        $data = [
            'post' => $post,
            'categories' => $this->blogService->getCategories(),
            'relatedPosts' => $post['category_id'] 
                ? $this->blogService->getRepository()->getRelated($post['post_id'], $post['category_id'], 5)
                : []
        ];
        
        // Use simple view rendering for public blog
        $viewPath = __DIR__ . '/../views/blog/post.php';
        if (file_exists($viewPath)) {
            extract($data);
            require $viewPath;
        } else {
            echo "Blog post view dosyası bulunamadı.";
        }
    }
    
    public function category($slug) {
        if (!$this->blogService) {
            http_response_code(500);
            echo "Blog servisi kullanılamıyor.";
            exit;
        }
        
        $category = $this->blogService->getCategoryBySlug($slug);
        
        if (!$category) {
            http_response_code(404);
            echo "Kategori bulunamadı.";
            exit;
        }
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $data = [
            'category' => $category,
            'posts' => $this->blogService->getRepository()->getByCategory($category['category_id'], $limit, $offset),
            'categories' => $this->blogService->getCategories(),
            'currentPage' => $page
        ];
        
        // Use simple view rendering for public blog
        $viewPath = __DIR__ . '/../views/blog/category.php';
        if (file_exists($viewPath)) {
            extract($data);
            require $viewPath;
        } else {
            echo "Blog category view dosyası bulunamadı.";
        }
    }
}
