<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

class LegalPageController extends \App\Core\Controller {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function show($slug) {
        $service = \App\Core\DependencyFactory::getLegalPageService();
        $page = $service->getBySlug($slug);
        
        if (!$page) {
            http_response_code(404);
            echo "Sayfa bulunamadı.";
            return;
        }
        
        $this->render('legal/show', [
            'page' => $page,
            'title' => $page['title'] . ' - Qordy',
        ]);
    }
}
