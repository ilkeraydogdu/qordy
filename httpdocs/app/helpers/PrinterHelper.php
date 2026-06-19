<?php
/**
 * Printer Helper - Sipariş -> Yazıcı Entegrasyonu
 * Kullanım: PrinterHelper::sendToPrinter($business_id, $order_data, 'cashier');
 */
class PrinterHelper {
    
    public static function sendToPrinter($business_id, $order_data, $location = 'cashier') {
        try {
            // Use centralized database connection instead of hardcoded credentials
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $db = \App\Core\DependencyFactory::getDatabase();
            
            // Get system settings
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $settings = $settingsService->getSettings();
            
            // Format receipt content (ESC/POS compatible)
            $content = self::formatReceipt($order_data, $settings);
            
            // Get printers for this location
            $stmt = $db->prepare("SELECT name FROM printers WHERE tenant_id = ? AND location_type = ? AND status = 'connected'");
            $stmt->execute([$business_id, $location]);
            $printers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($printers)) {
                error_log("QORDY: No printers found for location: $location");
                return false;
            }
            
            // Add to print queue for each printer
            $stmt = $db->prepare("INSERT INTO print_queue (tenant_id, printer_name, content, status, created_at, updated_at) VALUES (?, ?, ?, 'pending', NOW(), NOW())");
            
            foreach ($printers as $printer) {
                $stmt->execute([$business_id, $printer, $content]);
            }
            
            error_log("QORDY: Print job sent to " . count($printers) . " printer(s) at location: $location");
            return true;
            
        } catch (Exception $e) {
            error_log("QORDY PrintHelper Error: " . $e->getMessage());
            return false;
        }
    }
    
    private static function formatReceipt($order, $settings) {
        $lines = [];
        
        // Header
        if (!empty($settings['header_text'])) {
            $lines[] = $settings['header_text'];
            $lines[] = str_repeat('-', 42);
        }
        
        // Order details
        $lines[] = "Sipariş No: " . ($order['order_number'] ?? 'N/A');
        $lines[] = "Masa: " . ($order['table'] ?? 'N/A');
        $lines[] = "Tarih: " . date('d.m.Y H:i');
        $lines[] = str_repeat('-', 42);
        
        // Items
        if (!empty($order['items'])) {
            foreach ($order['items'] as $item) {
                $name = $item['name'] ?? 'Ürün';
                $qty = $item['quantity'] ?? 1;
                $price = number_format($item['price'] ?? 0, 2);
                $total = number_format(($item['quantity'] ?? 1) * ($item['price'] ?? 0), 2);
                
                $lines[] = sprintf("%-25s x%d", substr($name, 0, 25), $qty);
                $lines[] = sprintf("%30s %10s TL", $price, $total);
            }
        }
        
        $lines[] = str_repeat('-', 42);
        
        // Total
        $total = $order['total'] ?? 0;
        $lines[] = sprintf("%30s %10s TL", "TOPLAM:", number_format($total, 2));
        
        // Footer
        if (!empty($settings['footer_text'])) {
            $lines[] = str_repeat('-', 42);
            $lines[] = $settings['footer_text'];
        }
        
        return implode("\n", $lines);
    }
}
