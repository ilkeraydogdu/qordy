<?php
namespace App\Services;

use App\Core\DependencyFactory;
use App\Core\Logger;

class LegalPageService {
    
    private $db;
    
    public function __construct() {
        $this->db = DependencyFactory::getDatabase();
    }
    
    public function getAll(bool $activeOnly = false): array {
        try {
            $sql = "SELECT * FROM legal_pages";
            if ($activeOnly) $sql .= " WHERE is_active = 1";
            $sql .= " ORDER BY display_order ASC, id ASC";
            return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            Logger::error('LegalPageService::getAll', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    public function getById(int $id): ?array {
        try {
            $stmt = $this->db->prepare("SELECT * FROM legal_pages WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function getBySlug(string $slug): ?array {
        try {
            $stmt = $this->db->prepare("SELECT * FROM legal_pages WHERE slug = ? AND is_active = 1");
            $stmt->execute([$slug]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function getByType(string $type): ?array {
        try {
            $stmt = $this->db->prepare("SELECT * FROM legal_pages WHERE page_type = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$type]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function getFooterPages(): array {
        try {
            $stmt = $this->db->query("SELECT id, slug, title, footer_group FROM legal_pages WHERE is_active = 1 AND show_in_footer = 1 ORDER BY display_order ASC");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function getRegisterPages(): array {
        try {
            $stmt = $this->db->query("SELECT id, slug, title, page_type FROM legal_pages WHERE is_active = 1 AND show_in_register = 1 ORDER BY display_order ASC");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function create(array $data): ?int {
        try {
            $slug = $this->generateSlug($data['title'] ?? '', $data['slug'] ?? '');
            
            $stmt = $this->db->prepare("INSERT INTO legal_pages 
                (slug, title, content, meta_description, page_type, is_active, show_in_footer, show_in_register, footer_group, display_order, last_updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $slug,
                $data['title'],
                $data['content'],
                $data['meta_description'] ?? null,
                $data['page_type'] ?? 'custom',
                (int)($data['is_active'] ?? 1),
                (int)($data['show_in_footer'] ?? 0),
                (int)($data['show_in_register'] ?? 0),
                $data['footer_group'] ?? 'legal',
                (int)($data['display_order'] ?? 0),
                $data['updated_by'] ?? null,
            ]);
            
            return (int)$this->db->lastInsertId();
        } catch (\Exception $e) {
            Logger::error('LegalPageService::create', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    public function update(int $id, array $data): bool {
        try {
            $sets = [];
            $params = [];
            
            $allowed = ['title', 'content', 'meta_description', 'page_type', 'is_active', 
                        'show_in_footer', 'show_in_register', 'footer_group', 'display_order'];
            
            foreach ($allowed as $key) {
                if (array_key_exists($key, $data)) {
                    $sets[] = "$key = ?";
                    $params[] = $data[$key];
                }
            }
            
            if (!empty($data['slug'])) {
                $sets[] = "slug = ?";
                $params[] = $this->generateSlug($data['title'] ?? '', $data['slug']);
            }
            
            $sets[] = "version = version + 1";
            
            if (!empty($data['updated_by'])) {
                $sets[] = "last_updated_by = ?";
                $params[] = $data['updated_by'];
            }
            
            $params[] = $id;
            
            $sql = "UPDATE legal_pages SET " . implode(', ', $sets) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (\Exception $e) {
            Logger::error('LegalPageService::update', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function delete(int $id): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM legal_pages WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (\Exception $e) {
            Logger::error('LegalPageService::delete', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function toggleActive(int $id): bool {
        try {
            $stmt = $this->db->prepare("UPDATE legal_pages SET is_active = NOT is_active WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function generateSlug(string $title, string $customSlug = ''): string {
        $slug = !empty($customSlug) ? $customSlug : $title;
        $slug = mb_strtolower($slug, 'UTF-8');
        $tr = ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
               'Ç' => 'c', 'Ğ' => 'g', 'İ' => 'i', 'Ö' => 'o', 'Ş' => 's', 'Ü' => 'u'];
        $slug = strtr($slug, $tr);
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
    
    public function getPageTypes(): array {
        return [
            'privacy' => 'Gizlilik Politikası',
            'terms' => 'Kullanım Koşulları',
            'distance_sales' => 'Mesafeli Satış Sözleşmesi',
            'kvkk' => 'KVKK Aydınlatma Metni',
            'cookie' => 'Çerez Politikası',
            'return_policy' => 'Teslimat ve İade Koşulları',
            'about' => 'Hakkımızda',
            'ssl' => 'SSL Güvenlik Sertifikası',
            'custom' => 'Özel Sayfa',
        ];
    }
}
