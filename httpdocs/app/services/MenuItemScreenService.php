<?php
namespace App\Services;

use App\Core\BaseService;

class MenuItemScreenService {
    
    public function __construct($repository = null) {
        $this->db = \App\Core\DependencyFactory::getDatabase();
    }
    
    public function assignItemToScreens(string $menuItemId, array $screenIds, string $businessId): bool {
        try {
            $this->removeItemFromAllScreens($menuItemId, $businessId);
            
            if (!empty($screenIds)) {
                $sql = "INSERT INTO menu_item_screens (menu_item_id, screen_id, tenant_id, priority) VALUES ";
                $values = [];
                $params = [];
                
                foreach ($screenIds as $index => $screenId) {
                    $values[] = "(?, ?, ?, ?)";
                    $params[] = $menuItemId;
                    $params[] = $screenId;
                    $params[] = $businessId;
                    $params[] = $index + 1;
                }
                
                $sql .= implode(', ', $values);
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("MenuItemScreenService::assignItemToScreens - Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getScreensForItem(string $menuItemId): array {
        try {
            // Öncelik 1: menu_item_screens tablosu (doğrudan ürün-ekran ataması)
            $sql = "SELECT mis.screen_id, mis.priority, ps.name as screen_name, ps.screen_type
                    FROM menu_item_screens mis
                    JOIN preparation_screens ps ON mis.screen_id = ps.screen_id
                    WHERE mis.menu_item_id = :menu_item_id
                    ORDER BY mis.priority ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['menu_item_id' => $menuItemId]);
            $screens = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (!empty($screens)) {
                return $screens;
            }
            
            // Öncelik 2: menu_items.preparation_screen_id (ürün üzerinde doğrudan ekran ataması)
            $stmtDirect = $this->db->prepare("
                SELECT mi.preparation_screen_id, mi.category_id
                FROM menu_items mi 
                WHERE mi.menu_item_id = ? LIMIT 1
            ");
            $stmtDirect->execute([$menuItemId]);
            $menuItem = $stmtDirect->fetch(\PDO::FETCH_ASSOC);
            
            if ($menuItem && !empty($menuItem['preparation_screen_id'])) {
                $prepScreenId = $menuItem['preparation_screen_id'];
                $stmtScreen = $this->db->prepare("
                    SELECT screen_id, name as screen_name, screen_type
                    FROM preparation_screens 
                    WHERE screen_id = ? AND is_active = 1 LIMIT 1
                ");
                $stmtScreen->execute([$prepScreenId]);
                $screen = $stmtScreen->fetch(\PDO::FETCH_ASSOC);
                if ($screen) {
                    $screen['priority'] = 1;
                    return [$screen];
                }
            }
            
            // Öncelik 3: preparation_screen_categories (kategori bazlı ekran ataması)
            $categoryId = $menuItem['category_id'] ?? null;
            if (!empty($categoryId)) {
                $stmtCat = $this->db->prepare("
                    SELECT psc.screen_id, ps.name as screen_name, ps.screen_type
                    FROM preparation_screen_categories psc
                    JOIN preparation_screens ps ON psc.screen_id = ps.screen_id
                    WHERE psc.category_id = ? AND ps.is_active = 1
                    LIMIT 1
                ");
                $stmtCat->execute([$categoryId]);
                $catScreen = $stmtCat->fetch(\PDO::FETCH_ASSOC);
                if ($catScreen) {
                    $catScreen['priority'] = 10;
                    return [$catScreen];
                }
            }
            
            // Hiçbir atama bulunamadı → boş döndür (getDefaultScreenForItem fallback'i çalışsın)
            return [];
        } catch (\Exception $e) {
            error_log("MenuItemScreenService::getScreensForItem - Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function groupOrderItemsByScreens(array $orderItems, string $businessId): array {
        try {
            $screens = [];
            
            foreach ($orderItems as $orderItem) {
                $menuItemId = $orderItem['menu_item_id'] ?? null;
                if (empty($menuItemId)) {
                    continue;
                }
                
                // Skip direct service products and items that don't need preparation
                try {
                    $stmtCheck = $this->db->prepare("SELECT production_point, is_direct_service FROM menu_items WHERE menu_item_id = ? LIMIT 1");
                    $stmtCheck->execute([$menuItemId]);
                    $menuItemCheck = $stmtCheck->fetch(\PDO::FETCH_ASSOC);
                    if ($menuItemCheck) {
                        $productionPoint = $menuItemCheck['production_point'] ?? null;
                        $isDirectService = !empty($menuItemCheck['is_direct_service']) && $menuItemCheck['is_direct_service'] == 1;
                        if ($productionPoint === 'NONE' || $isDirectService) {
                            continue; // Bu ürün hazırlık gerektirmiyor, ekrana gönderilmemeli
                        }
                    }
                } catch (\Exception $checkEx) {
                    // Hata durumunda devam et
                }
                
                $itemScreens = $this->getScreensForItem($menuItemId);
                
                if (empty($itemScreens)) {
                    $defaultScreen = $this->getDefaultScreenForItem($menuItemId);
                    if ($defaultScreen) {
                        $itemScreens = [$defaultScreen];
                    } else {
                        // Fallback to system kitchen screen to avoid invalid screen IDs
                        $itemScreens = [['screen_id' => 'kitchen_main', 'screen_name' => 'Mutfak', 'screen_type' => 'KITCHEN']];
                    }
                }
                
                foreach ($itemScreens as $screen) {
                    $screenId = $screen['screen_id'];
                    
                    if (!isset($screens[$screenId])) {
                        $screens[$screenId] = [
                            'screen_id' => $screenId,
                            'screen_name' => $screen['screen_name'] ?? 'Bilinmeyen',
                            'screen_type' => $screen['screen_type'] ?? 'KITCHEN',
                            'items' => []
                        ];
                    }
                    
                    $screens[$screenId]['items'][] = $orderItem;
                }
            }
            
            return $screens;
        } catch (\Exception $e) {
            error_log("MenuItemScreenService::groupOrderItemsByScreens - Error: " . $e->getMessage());
            return [];
        }
    }
    
    private function getDefaultScreenForItem(string $menuItemId): ?array {
        try {
            // Önce ürünün production_point'ini kontrol et
            $stmt2 = $this->db->prepare("SELECT production_point, category_id FROM menu_items WHERE menu_item_id = :menu_item_id LIMIT 1");
            $stmt2->execute(['menu_item_id' => $menuItemId]);
            $menuItem = $stmt2->fetch(\PDO::FETCH_ASSOC);
            $productionPoint = $menuItem['production_point'] ?? null;
            
            // production_point NULL veya boş ise → mutfağa gönder (NARGİLE'ye DEĞİL!)
            // Sadece KITCHEN veya BAR production_point'li ürünler dinamik ekran eşleşmesine gider
            if (empty($productionPoint) || strtoupper($productionPoint) === 'NONE') {
                // production_point belirlenmemiş → varsayılan olarak mutfağa gönder
                return ['screen_id' => 'kitchen_main', 'screen_name' => 'Mutfak', 'screen_type' => 'KITCHEN'];
            }
            
            // BAR production_point için bar tipli ekran ara
            if (strtoupper($productionPoint) === 'BAR') {
                $sql = "SELECT ps.screen_id, ps.name as screen_name, ps.screen_type
                        FROM preparation_screens ps
                        WHERE ps.screen_type = 'BAR' AND ps.is_active = 1
                        LIMIT 1";
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                $screen = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($screen) {
                    return $screen;
                }
            }
            
            // KITCHEN veya diğer production_point → mutfak
            return ['screen_id' => 'kitchen_main', 'screen_name' => 'Mutfak', 'screen_type' => 'KITCHEN'];
        } catch (\Exception $e) {
            return ['screen_id' => 'kitchen_main', 'screen_name' => 'Mutfak', 'screen_type' => 'KITCHEN'];
        }
    }
    
    private function removeItemFromAllScreens(string $menuItemId, string $businessId): bool {
        try {
            $sql = "DELETE FROM menu_item_screens WHERE menu_item_id = :menu_item_id AND tenant_id = :tenant_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['menu_item_id' => $menuItemId, 'tenant_id' => $businessId]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function getPrintersForScreen(string $screenId): array {
        try {
            $sql = "SELECT p.printer_id, p.printer_name
                    FROM preparation_screen_printers psp
                    JOIN printers p ON psp.printer_id = p.printer_id
                    WHERE psp.screen_id = :screen_id AND p.is_active = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['screen_id' => $screenId]);
            $printers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Sistem ekranları için yazıcı bulunamazsa:
            // TÜM yazıcılara göndermek YANLIŞ - nargile/bar yazıcısı mutfak fişi alır!
            // Bunun yerine boş dizi döndür, bridge'in kendi fallback mekanizmasını kullansın
            // Admin'in sistem ekranlarına doğru yazıcı ataması yapması gerekir
            if (empty($printers) && in_array($screenId, ['kitchen_main', 'waiter_main', 'cashier_main'])) {
                error_log("MenuItemScreenService: No printer assigned to system screen '{$screenId}'. Admin should assign a printer via desktop app.");
                // Boş döndür - bridge desktop uygulamasında screen_assignments ile yönetilir
                // Bridge uygulamasının kendi atama mekanizması var
            }
            
            return $printers;
        } catch (\Exception $e) {
            return [];
        }
    }
}
