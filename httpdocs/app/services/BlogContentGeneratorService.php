<?php
namespace App\Services;

require_once __DIR__ . '/SEOContentService.php';
require_once __DIR__ . '/BlogService.php';

/**
 * Blog Content Generator Service
 * Automatically generates SEO-optimized blog content using Gemini AI
 */
class BlogContentGeneratorService {
    private $geminiService;
    private $seoContentService;
    private $blogService;
    private $db;
    
    // Blog konuları ve anahtar kelimeleri
    private $contentTopics = [
        [
            'title' => 'QR Menü Nedir? Restoranlar İçin Dijital Menü Çözümleri',
            'category_slug' => 'qr-menu',
            'keywords' => ['QR menü', 'dijital menü', 'temasız menü', 'QR kod menü', 'restoran teknolojisi'],
            'excerpt' => 'QR menü sistemi ile restoranınızı dijitalleştirin. Temasız menü deneyimi ve müşteri memnuniyeti için ideal çözüm.',
            'priority' => 10
        ],
        [
            'title' => 'Restoran Yönetim Sistemi Seçerken Dikkat Edilmesi Gerekenler',
            'category_slug' => 'restoran-yonetimi',
            'keywords' => ['restoran yönetim sistemi', 'restoran yazılımı', 'restoran otomasyonu', 'işletme yönetimi'],
            'excerpt' => 'Doğru restoran yönetim sistemi seçimi işletmenizin başarısını belirler. İşte dikkat etmeniz gerekenler.',
            'priority' => 9
        ],
        [
            'title' => 'POS Sistemi ile Restoran İşletmeciliğini Dijitalleştirme',
            'category_slug' => 'pos-sistemi',
            'keywords' => ['POS sistemi', 'ödeme sistemi', 'kasa sistemi', 'restoran POS'],
            'excerpt' => 'Modern POS sistemleri ile restoran işletmeciliğinizi dijitalleştirin ve verimliliğinizi artırın.',
            'priority' => 9
        ],
        [
            'title' => 'Mutfak Yönetim Sistemi: Sipariş Takibi ve Verimlilik',
            'category_slug' => 'restoran-yonetimi',
            'keywords' => ['mutfak yönetimi', 'sipariş takibi', 'mutfak ekranı', 'hazırlık süresi'],
            'excerpt' => 'Mutfak yönetim sistemleri ile sipariş takibini kolaylaştırın ve mutfak verimliliğini artırın.',
            'priority' => 8
        ],
        [
            'title' => 'Stok Takip Sistemi ile Restoran Maliyetlerini Düşürme',
            'category_slug' => 'restoran-yonetimi',
            'keywords' => ['stok takibi', 'envanter yönetimi', 'maliyet kontrolü', 'stok yönetimi'],
            'excerpt' => 'Etkili stok takip sistemi ile restoran maliyetlerinizi kontrol altına alın ve kârlılığınızı artırın.',
            'priority' => 8
        ],
        [
            'title' => 'Rezervasyon Yönetimi: Masa Yönetiminin Önemi',
            'category_slug' => 'restoran-yonetimi',
            'keywords' => ['rezervasyon sistemi', 'masa yönetimi', 'rezervasyon takibi', 'müşteri yönetimi'],
            'excerpt' => 'Profesyonel rezervasyon yönetimi ile müşteri deneyimini iyileştirin ve masa doluluk oranınızı artırın.',
            'priority' => 7
        ],
        [
            'title' => 'Restoran Dijitalleşme Rehberi 2024',
            'category_slug' => 'dijitallesme',
            'keywords' => ['restoran dijitalleşme', 'dijital dönüşüm', 'teknoloji entegrasyonu', 'modern restoran'],
            'excerpt' => 'Restoranınızı dijitalleştirmek için kapsamlı rehber. Teknoloji entegrasyonu ve dijital dönüşüm stratejileri.',
            'priority' => 10
        ],
        [
            'title' => 'QR Menü Avantajları: Neden Dijital Menü Kullanmalısınız?',
            'category_slug' => 'qr-menu',
            'keywords' => ['QR menü avantajları', 'dijital menü faydaları', 'temasız hizmet', 'müşteri deneyimi'],
            'excerpt' => 'QR menü sisteminin restoran işletmecilerine sağladığı avantajlar ve müşteri deneyimine etkileri.',
            'priority' => 8
        ],
        [
            'title' => 'Restoran Otomasyonu ile İşletme Verimliliğini Artırma',
            'category_slug' => 'restoran-yonetimi',
            'keywords' => ['restoran otomasyonu', 'işletme verimliliği', 'operasyon yönetimi', 'süreç otomasyonu'],
            'excerpt' => 'Restoran otomasyon sistemleri ile işletme verimliliğinizi artırın ve operasyonel maliyetleri düşürün.',
            'priority' => 7
        ],
        [
            'title' => 'Mobil Uygulama ile Restoran Yönetimi',
            'category_slug' => 'restoran-yonetimi',
            'keywords' => ['mobil uygulama', 'mobil yönetim', 'uzaktan yönetim', 'mobil erişim'],
            'excerpt' => 'Mobil uygulamalar ile restoranınızı her yerden yönetin. Uzaktan kontrol ve gerçek zamanlı takip.',
            'priority' => 6
        ]
    ];
    
    public function __construct() {
        try {
            $this->geminiService = \App\Core\DependencyFactory::getGeminiService();
            $this->seoContentService = \App\Core\DependencyFactory::getSEOContentService();
            $this->db = \App\Core\DependencyFactory::getDatabase();
            $this->blogService = \App\Core\DependencyFactory::getBlogService();
        } catch (\Exception $e) {
            $this->geminiService = null;
            $this->seoContentService = null;
            $this->blogService = null;
            $this->db = null;
        }
    }
    
    /**
     * Generate a blog post for a topic
     * @param array $topic Topic data
     * @return array Result with post_id or error
     */
    public function generateBlogPost($topic) {
        if (!$this->geminiService || !$this->geminiService->isAvailable()) {
            return ['success' => false, 'error' => 'Gemini AI servisi kullanılamıyor'];
        }
        
        if (!$this->blogService) {
            return ['success' => false, 'error' => 'Blog servisi kullanılamıyor'];
        }
        
        try {
            // Get category
            $category = $this->blogService->getCategoryBySlug($topic['category_slug']);
            if (!$category) {
                return ['success' => false, 'error' => 'Kategori bulunamadı: ' . $topic['category_slug']];
            }
            
            // Generate content using Gemini
            $content = $this->generateContent($topic);
            if (empty($content)) {
                return ['success' => false, 'error' => 'İçerik üretilemedi'];
            }
            
            // Generate SEO-optimized meta tags
            $metaTags = $this->generateMetaTags($topic, $content);
            
            // Create blog post
            $postData = [
                'post_id' => generateId('post'),
                'title' => $topic['title'],
                'slug' => $this->generateSlug($topic['title']),
                'excerpt' => $topic['excerpt'] ?? substr(strip_tags($content), 0, 160),
                'content' => $content,
                'category_id' => $category['category_id'],
                'status' => 'published',
                'published_at' => date('Y-m-d H:i:s'),
                'meta_title' => $metaTags['title'],
                'meta_description' => $metaTags['description'],
                'meta_keywords' => implode(', ', $topic['keywords']),
                'is_featured' => ($topic['priority'] ?? 0) >= 9,
                'author_id' => 'SYSTEM'
            ];
            
            $postId = $this->blogService->createPost($postData);
            
            // Set tags
            $tags = array_merge($topic['keywords'], [$category['name']]);
            $this->blogService->getRepository()->setTags($postId, $tags);
            
            return [
                'success' => true,
                'post_id' => $postId,
                'title' => $topic['title'],
                'slug' => $postData['slug']
            ];
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('BlogContentGeneratorService error: ' . $e->getMessage(), [
                    'topic' => $topic,
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate blog content using Gemini AI
     * @param array $topic
     * @return string
     */
    private function generateContent($topic) {
        $keywordsStr = implode(', ', $topic['keywords']);
        
        $prompt = "Sen profesyonel bir restoran işletme danışmanı ve içerik yazarısın. Aşağıdaki konu hakkında SEO optimizasyonlu, bilgilendirici ve değerli bir blog yazısı yaz.

Konu: {$topic['title']}
Anahtar Kelimeler: {$keywordsStr}
Hedef Uzunluk: 800-1200 kelime
Dil: Türkçe

Gereksinimler:
1. İçerik SEO optimizasyonlu olmalı (anahtar kelimeler doğal şekilde kullanılmalı)
2. Başlıklar (H2, H3) içermeli ve yapılandırılmış olmalı
3. İlk paragrafta ana konu ve anahtar kelimeler geçmeli
4. Okuyucuya değerli bilgiler ve pratik öneriler sunmalı
5. Qordy restoran yönetim sistemi hakkında doğal referanslar içerebilir
6. Profesyonel ve kurumsal bir dil kullanılmalı
7. İçerik HTML formatında olmalı (başlıklar için <h2>, <h3> etiketleri kullan)

İçerik yapısı:
- Giriş paragrafı (ana konu ve önem)
- Ana başlıklar ve alt başlıklar (H2, H3)
- Pratik bilgiler ve öneriler
- Sonuç paragrafı

Sadece HTML formatında içeriği döndür, başka açıklama ekleme.";
        
        $content = $this->geminiService->callGeminiAPI('gemini-2.5-pro', $prompt);
        
        // Clean and format content
        if ($content) {
            // Ensure proper HTML structure
            $content = '<div class="blog-content">' . $content . '</div>';
        }
        
        return $content ?: '';
    }
    
    /**
     * Generate SEO-optimized meta tags
     * @param array $topic
     * @param string $content
     * @return array
     */
    private function generateMetaTags($topic, $content) {
        if ($this->seoContentService) {
            $optimized = $this->seoContentService->optimizeMetaTags(
                'blog',
                $topic['title'],
                $topic['excerpt'] ?? substr(strip_tags($content), 0, 160),
                $topic['keywords'],
                'tr'
            );
            
            return [
                'title' => $optimized['title'] ?? $topic['title'],
                'description' => $optimized['description'] ?? ($topic['excerpt'] ?? substr(strip_tags($content), 0, 160))
            ];
        }
        
        // Fallback
        $title = $topic['title'];
        if (strlen($title) > 60) {
            $title = substr($title, 0, 57) . '...';
        }
        
        $description = $topic['excerpt'] ?? substr(strip_tags($content), 0, 160);
        if (strlen($description) > 160) {
            $description = substr($description, 0, 157) . '...';
        }
        
        return [
            'title' => $title,
            'description' => $description
        ];
    }
    
    /**
     * Generate URL-friendly slug
     * @param string $title
     * @return string
     */
    private function generateSlug($title) {
        $slug = strtolower(trim($title));
        $slug = str_replace(['ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['i', 'g', 'u', 's', 'o', 'c'], $slug);
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Check if slug exists and add number if needed
        $originalSlug = $slug;
        $counter = 1;
        while ($this->slugExists($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Check if slug exists
     * @param string $slug
     * @return bool
     */
    private function slugExists($slug) {
        try {
            $postRepo = new \App\Repositories\BlogPostRepository($this->db);
            $existing = $postRepo->getBySlug($slug);
            return $existing !== null;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get topics that haven't been published yet
     * @return array
     */
    public function getUnpublishedTopics() {
        try {
            $postRepo = \App\Core\DependencyFactory::getBlogPostRepository();
            $publishedPosts = $postRepo->getPublished(1000, 0);
            $publishedTitles = array_column($publishedPosts, 'title');
            
            $unpublished = [];
            foreach ($this->contentTopics as $topic) {
                if (!in_array($topic['title'], $publishedTitles)) {
                    $unpublished[] = $topic;
                }
            }
            
            // Sort by priority
            usort($unpublished, function($a, $b) {
                return ($b['priority'] ?? 0) - ($a['priority'] ?? 0);
            });
            
            return $unpublished;
        } catch (\Exception $e) {
            return $this->contentTopics;
        }
    }
    
    /**
     * Generate next blog post automatically
     * @return array Result
     */
    public function generateNextPost() {
        $unpublishedTopics = $this->getUnpublishedTopics();
        
        if (empty($unpublishedTopics)) {
            return [
                'success' => false,
                'error' => 'Yayınlanmamış konu bulunamadı. Tüm konular yayınlandı.'
            ];
        }
        
        // Get highest priority topic
        $topic = $unpublishedTopics[0];
        
        return $this->generateBlogPost($topic);
    }
    
    /**
     * Get all content topics
     * @return array
     */
    public function getAllTopics() {
        return $this->contentTopics;
    }
    
    /**
     * Add custom topic
     * @param array $topic
     * @return void
     */
    public function addTopic($topic) {
        $this->contentTopics[] = $topic;
    }
}
