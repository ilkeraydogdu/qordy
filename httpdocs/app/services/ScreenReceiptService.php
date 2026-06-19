<?php
namespace App\Services;

class ScreenReceiptService {
    private $db;
    private $menuItemScreenService;
    private $printerService;
    private $orderItemRepository;
    
    public function __construct() {
        $this->db = \App\Core\DependencyFactory::getDatabase();
        $this->menuItemScreenService = new MenuItemScreenService();
        $this->printerService = \App\Core\DependencyFactory::getPrinterService();
        $this->orderItemRepository = new \App\Repositories\OrderItemRepository();
    }
    
    /**
     * Sipariş için ekran bazlı fişleri oluştur ve yazdırma kuyruğuna ekle
     */
    public function processOrderReceipts(string $orderId, string $businessId): array {
        try {
            // Sipariş bilgilerini al
            $orderInfo = $this->getOrderInfo($orderId);
            if (!$orderInfo) {
                throw new \Exception("Order not found: $orderId");
            }
            
            // GUARD: Reject cancelled/refunded orders
            $orderStatus = strtoupper($orderInfo['status'] ?? '');
            if (in_array($orderStatus, ['CANCELLED', 'REFUNDED'])) {
                error_log("ScreenReceiptService::processOrderReceipts - Order {$orderId} is {$orderStatus}, skipping");
                return ['success' => false, 'message' => "Order is {$orderStatus}"];
            }
            
            // Sipariş kalemlerini al
            $orderItems = $this->getOrderItems($orderId);
            if (empty($orderItems)) {
                return ['success' => false, 'message' => 'No items in order'];
            }
            
            // Filter out cancelled items
            $orderItems = array_filter($orderItems, function($item) {
                return strtoupper($item['status'] ?? '') !== 'CANCELLED' 
                    && strtoupper($item['preparation_status'] ?? '') !== 'CANCELLED';
            });
            $orderItems = array_values($orderItems);
            if (empty($orderItems)) {
                return ['success' => false, 'message' => 'All items cancelled'];
            }
            
            // Kalemleri ekran türlerine göre grupla
            $screenGroups = $this->menuItemScreenService->groupOrderItemsByScreens($orderItems, $businessId);
            
            $receiptsCreated = [];
            
            // Her ekran grubu için fiş oluştur
            foreach ($screenGroups as $screenId => $screenData) {
                $receiptContent = $this->generateReceiptForScreen(
                    $screenData['screen_type'],
                    $screenData['items'],
                    $orderInfo
                );
                
                // Yazıcıları bul
                $printers = $this->menuItemScreenService->getPrintersForScreen($screenId);
                
                if (empty($printers)) {
                    // Fallback: use default printer to ensure bridge receives the job
                    $fallbackPrinter = $this->printerService ? $this->printerService->getDefaultPrinter() : null;
                    if ($fallbackPrinter && !empty($fallbackPrinter['printer_id'])) {
                        $printers = [$fallbackPrinter];
                        error_log("No printers mapped for screen {$screenId}. Using fallback printer: " . ($fallbackPrinter['printer_id'] ?? 'unknown'));
                    } else {
                        error_log("No printers found for screen: $screenId");
                        continue;
                    }
                }
                
                // Her yazıcı için yazdırma kuyruğuna ekle
                foreach ($printers as $printer) {
                    $queueId = $this->addToPrintQueue(
                        $orderId,
                        $printer['printer_id'],
                        $screenId,
                        $screenData['screen_type'],
                        $receiptContent,
                        $orderInfo,
                        $businessId,
                        $screenData['items'] ?? [],
                        $screenData['screen_name'] ?? ''
                    );
                    
                    if ($queueId) {
                        $receiptsCreated[] = [
                            'queue_id' => $queueId,
                            'screen' => $screenData['screen_name'],
                            'printer' => $printer['printer_name']
                        ];
                    }
                }
            }
            
            return [
                'success' => true,
                'receipts_created' => count($receiptsCreated),
                'details' => $receiptsCreated
            ];
            
        } catch (\Exception $e) {
            error_log("ScreenReceiptService::processOrderReceipts - Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Ekran türüne göre fiş içeriği oluştur
     */
    private function generateReceiptForScreen(string $screenType, array $items, array $orderInfo): string {
        $businessName = $orderInfo['business_name'] ?? 'İşletme';
        $tableDisplay = $orderInfo['table_display'] ?? $orderInfo['table_name'] ?? 'Paket Servis';
        $waiterName = $orderInfo['waiter_name'] ?? 'Garson';
        $orderTime = date('H:i', strtotime($orderInfo['created_at'] ?? 'now'));
        $orderDate = date('d.m.Y', strtotime($orderInfo['created_at'] ?? 'now'));
        $orderNumber = $orderInfo['order_id'] ?? '#0000';
        
        $screenTitle = $this->getScreenTitle($screenType);
        
        // ESC/POS komutları ile fiş formatı - TÜM İÇERİK ORTALANMIŞ
        $receipt = "";
        $receipt .= $this->escCenter();
        $receipt .= $this->escBold(true);
        $receipt .= $this->escDoubleHeight(true);
        $receipt .= "$businessName\n";
        $receipt .= $this->escDoubleHeight(false);
        $receipt .= $this->escBold(false);
        $businessPhone = trim($orderInfo['business_phone'] ?? '');
        if ($businessPhone !== '') {
            $receipt .= $this->escCenter();
            $receipt .= "Tel: " . mb_substr($businessPhone, 0, 36) . "\n";
        }
        $receipt .= str_repeat("=", 42) . "\n";
        $receipt .= $this->escBold(true);
        $receipt .= "$screenTitle\n";
        $receipt .= $this->escBold(false);
        $receipt .= str_repeat("=", 42) . "\n\n";
        
        // Sipariş bilgileri - ORTALANMIŞ (bölge + masa: Bahçe Masa 32)
        $receipt .= $this->escBold(true);
        $receipt .= "Tarih: $orderDate  Saat: $orderTime\n";
        $receipt .= "Masa: $tableDisplay  Garson: $waiterName\n";
        $receipt .= "Siparis No: $orderNumber\n";
        $receipt .= $this->escBold(false);
        $receipt .= str_repeat("-", 42) . "\n\n";
        
        // Ürünler - SOLA YASLI, DİNAMİK UZUNLUK
        $receipt .= $this->escLeft(); // Sola yasla
        $totalItems = 0;
        $maxLineWidth = 42; // Termal yazıcı genişliği
        
        foreach ($items as $item) {
            $quantity = $item['quantity'] ?? 1;
            $name = $item['item_name'] ?? $item['name'] ?? 'Ürün';
            $price = floatval($item['price'] ?? 0);
            $totalItems += $quantity;
            
            // Uzun ürün adlarını dinamik olarak kırp
            $qtyText = $quantity . "x";
            $priceText = number_format($price, 2, ',', '.') . " TL";
            $maxNameLength = $maxLineWidth - mb_strlen($qtyText, 'UTF-8') - mb_strlen($priceText, 'UTF-8') - 4;
            
            if (mb_strlen($name, 'UTF-8') > $maxNameLength) {
                $name = mb_substr($name, 0, $maxNameLength - 3, 'UTF-8') . '...';
            }
            
            // Tek satırda: miktar + ürün adı + fiyat
            $receipt .= $this->escBold(true);
            $receipt .= sprintf("%-3s %-{$maxNameLength}s %10s\n", $qtyText, $name, $priceText);
            $receipt .= $this->escBold(false);
            
            // Varyantlar - girintili
            $variantName = $item['variant_name'] ?? '';
            if (!empty($variantName)) {
                $receipt .= "    Varyant: " . $variantName . "\n";
            } elseif (!empty($item['variants'])) {
                foreach ($item['variants'] as $variant) {
                    $variantText = $variant['option_name'] . ': ' . $variant['option_value'];
                    if (($variant['price_modifier'] ?? 0) > 0) {
                        $variantText .= ' (+' . number_format($variant['price_modifier'], 2, ',', '.') . ' TL)';
                    }
                    $variantMaxLength = $maxLineWidth - 4;
                    if (mb_strlen($variantText, 'UTF-8') > $variantMaxLength) {
                        $variantText = mb_substr($variantText, 0, $variantMaxLength - 3, 'UTF-8') . '...';
                    }
                    $receipt .= "    " . $variantText . "\n";
                }
            }
            
            // Özel notlar - girintili (tüm olası alan adlarını kontrol et)
            $itemNote = $item['item_note'] ?? $item['note'] ?? $item['notes'] ?? '';
            if (!empty($itemNote)) {
                $noteMaxLength = $maxLineWidth - 4;
                if (mb_strlen($itemNote, 'UTF-8') > $noteMaxLength) {
                    $itemNote = mb_substr($itemNote, 0, $noteMaxLength - 3, 'UTF-8') . '...';
                }
                $receipt .= "    Not: " . $itemNote . "\n";
            }
            
            // Çıkarılan malzemeler (tüm olası alan adlarını kontrol et)
            $excluded = $item['excluded_ingredients'] ?? $item['removed_ingredients'] ?? '';
            if (!empty($excluded)) {
                if (is_array($excluded)) {
                    $exNames = [];
                    foreach ($excluded as $ex) {
                        $exName = is_array($ex) ? ($ex['name'] ?? $ex['ingredient_name'] ?? '') : $ex;
                        if (!empty($exName)) $exNames[] = $exName;
                    }
                    $excluded = implode(', ', $exNames);
                }
                if (!empty($excluded)) {
                    $removeMaxLength = $maxLineWidth - 11;
                    if (mb_strlen($excluded, 'UTF-8') > $removeMaxLength) {
                        $excluded = mb_substr($excluded, 0, $removeMaxLength - 3, 'UTF-8') . '...';
                    }
                    $receipt .= "    Cikar: " . $excluded . "\n";
                }
            }
            
            // Eklenen malzemeler/ekstralar
            $extras = $item['selected_extras'] ?? $item['extra_ingredients'] ?? $item['added_ingredients'] ?? '';
            if (!empty($extras)) {
                if (is_array($extras)) {
                    $extNames = [];
                    foreach ($extras as $ext) {
                        $extName = is_array($ext) ? ($ext['name'] ?? $ext['extra_name'] ?? '') : $ext;
                        if (!empty($extName)) $extNames[] = $extName;
                    }
                    $extras = implode(', ', $extNames);
                }
                if (!empty($extras)) {
                    $extraMaxLength = $maxLineWidth - 12;
                    if (mb_strlen($extras, 'UTF-8') > $extraMaxLength) {
                        $extras = mb_substr($extras, 0, $extraMaxLength - 3, 'UTF-8') . '...';
                    }
                    $receipt .= "    Ekstra: " . $extras . "\n";
                }
            }
            
            $receipt .= "\n";
        }
        
        $receipt .= str_repeat("-", 42) . "\n";
        $receipt .= $this->escBold(true);
        $receipt .= $this->escDoubleHeight(true);
        $receipt .= $this->escCenter();
        $receipt .= "TOPLAM: $totalItems KALEM\n";
        $receipt .= $this->escDoubleHeight(false);
        $receipt .= $this->escBold(false);
        $receipt .= str_repeat("=", 42) . "\n\n";
        
        // Footer – işletme adı
        $receipt .= "\n";
        $receipt .= $this->escCenter();
        $receipt .= $businessName . "\n";
        $receipt .= "YONETIM SISTEMI\n";
        $receipt .= "www.qordy.com\n";
        $receipt .= str_repeat("=", 42) . "\n";
        
        // Kesme komutu
        $receipt .= $this->escCut();
        
        return $this->trToAscii($receipt);
    }
    
    private function getScreenTitle(string $screenType): string {
        $titles = [
            'KITCHEN' => 'MUTFAK SİPARİŞİ',
            'BAR' => 'BAR SİPARİŞİ',
            'PREPARATION' => 'HAZIRLIK SİPARİŞİ',
            'DESSERT' => 'TATLI SİPARİŞİ',
            'COLD' => 'SOĞUK HAZIRLIK'
        ];
        
        return $titles[$screenType] ?? 'SİPARİŞ';
    }
    
    /**
     * Yazdırma kuyruğuna ekle
     */
    private function addToPrintQueue(string $orderId, string $printerId, string $screenId,
                                     string $screenType, string $content, array $orderInfo, string $businessId,
                                     array $items = [], string $screenName = ''): ?string {
        try {
            require_once __DIR__ . '/../helpers/functions.php';
            
            // DEDUPLICATION: Prevent duplicate queue entries for same order+screen within 60 seconds
            try {
                $dupStmt = $this->db->prepare("
                    SELECT queue_id FROM receipt_print_queue
                    WHERE tenant_id = ? AND screen_id = ? 
                    AND status IN ('PENDING', 'PRINTING', 'PRINTED')
                    AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)
                    AND JSON_UNQUOTE(JSON_EXTRACT(print_data, '$.order_id')) = ?
                    LIMIT 1
                ");
                $dupStmt->execute([$businessId, $screenId, (string) $orderId]);
                if ($dupStmt->rowCount() > 0) {
                    $existing = $dupStmt->fetch(\PDO::FETCH_ASSOC);
                    \App\Core\Logger::info("ScreenReceiptService: Duplicate detected for order {$orderId} screen {$screenId}, skipping");
                    return $existing['queue_id'] ?? null;
                }
            } catch (\Exception $dupEx) {
                // Fail-open: proceed with insert
            }
            
            $queueId = generateId('pq');
            
            $sql = "INSERT INTO receipt_print_queue 
                    (queue_id, receipt_id, tenant_id, printer_id, screen_id, status, print_data, created_at)
                    VALUES (?, ?, ?, ?, ?, 'PENDING', ?, NOW())";
            
            // İşletme bilgileri: masaüstü uygulama fiş başlığı için
            $business = [
                'name' => trim($orderInfo['business_name'] ?? ''),
                'address' => '',
                'phone' => trim($orderInfo['business_phone'] ?? ''),
            ];
            // Sipariş içeriğinde bölge + masa: "Bahçe Masa 32"
            $tableDisplay = $orderInfo['table_display'] ?? ($orderInfo['table_name'] ?? '');
            
            // Items'ı ReceiptService gibi normalize et (line 897-951)
            // Kasiyer'de çalışan mantık: ReceiptService.php line 898
            $itemsArray = [];
            foreach ($items as $item) {
                // ReceiptService.php line 898 - TAM AYNI MANTIK
                $itemName = $item['item_name'] ?? $item['name'] ?? 'Ürün';
                $quantity = intval($item['quantity'] ?? 1);
                $price = floatval($item['price'] ?? $item['menu_price'] ?? 0);
                $total = $price * $quantity;
                
                // Get variant information
                $variantName = $item['variant_name'] ?? null;
                
                // Get excluded ingredients
                $excludedIngredientsRaw = $item['excluded_ingredients'] ?? '[]';
                $excludedIngredients = is_string($excludedIngredientsRaw) ? json_decode($excludedIngredientsRaw, true) : $excludedIngredientsRaw;
                $excludedIngredients = is_array($excludedIngredients) ? $excludedIngredients : [];
                
                // Get selected extras
                $selectedExtrasRaw = $item['selected_extras'] ?? '[]';
                $selectedExtras = is_string($selectedExtrasRaw) ? json_decode($selectedExtrasRaw, true) : $selectedExtrasRaw;
                $selectedExtras = is_array($selectedExtras) ? $selectedExtras : [];
                
                // Build options array for receipt
                $options = [];
                
                // Add variant
                if (!empty($variantName)) {
                    $options[] = 'Varyant: ' . $variantName;
                }
                
                // Add excluded ingredients
                foreach ($excludedIngredients as $ex) {
                    $ingName = is_array($ex) ? ($ex['name'] ?? $ex['ingredient_name'] ?? '') : $ex;
                    if (!empty($ingName)) {
                        $options[] = 'Çıkar: ' . $ingName;
                    }
                }
                
                // Add selected extras
                foreach ($selectedExtras as $ext) {
                    $extName = is_array($ext) ? ($ext['name'] ?? '') : $ext;
                    if (!empty($extName)) {
                        $options[] = 'Ekstra: ' . $extName;
                    }
                }
                
                // ReceiptService.php line 940-950 - TAM AYNI FORMAT
                // item_name alanını da ekle (Python tarafı öncelikli olarak item_name kontrol edecek)
                $itemsArray[] = [
                    'name' => $itemName,
                    'item_name' => $item['item_name'] ?? $itemName, // item_name'i de koru
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $total,
                    'notes' => $item['item_note'] ?? $item['note'] ?? '',
                    'options' => $options,
                    'variant_name' => $variantName,
                    'excluded_ingredients' => $excludedIngredients,
                    'selected_extras' => $selectedExtras
                ];
            }
            
            // Calculate totals (basit - hazırlık fişi için)
            $subtotal = 0;
            foreach ($itemsArray as $item) {
                $subtotal += floatval($item['total'] ?? 0);
            }
            $totalAmount = $subtotal;
            
            // Format dates
            $orderDate = date('d.m.Y H:i', strtotime($orderInfo['created_at'] ?? 'now'));
            
            // ReceiptService.php line 1226-1233 - receipt_data nested yapısını oluştur
            $receiptData = [
                'business' => $business,
                'receipt' => [
                    'receipt_number' => '',
                    'order_id' => $orderId,
                    'order_date' => $orderDate,
                    'payment_time' => null,
                    'payment_method' => 'PENDING'
                ],
                'order' => [
                    'order_id' => $orderId,
                    'table_name' => $tableDisplay,
                    'order_date' => $orderDate,
                    'waiter_name' => $orderInfo['waiter_name'] ?? '',
                    'customer_note' => $orderInfo['customer_note'] ?? ''
                ],
                'items' => $itemsArray,
                'totals' => [
                    'subtotal' => $subtotal,
                    'service_charge' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total' => $totalAmount
                ]
            ];
            
            // ReceiptService.php line 1213-1237 - print_data formatı
            $printData = [
                // Old format for backward compatibility
                'content' => $content,
                // Fiş tipi (desktop app template seçimi için)
                'receipt_type' => 'PREPARATION',
                // Bridge / masaüstü uygulaması için üst seviye alanlar
                'order_id' => $orderId,
                'table' => $orderInfo['table_name'] ?? '',
                'table_name' => $orderInfo['table_name'] ?? '',
                'table_display' => $tableDisplay,
                'zone_name' => $orderInfo['zone_name'] ?? '',
                'waiter_name' => $orderInfo['waiter_name'] ?? '',
                'screen_type' => $screenType,
                'screen_name' => $screenName,
                'customer_note' => $orderInfo['customer_note'] ?? '',
                // New format for dynamic rendering - ReceiptService gibi
                'receipt_data' => $receiptData,
                'format_version' => '2.0' // Version indicator for new format
            ];
            
            $printDataJson = json_encode($printData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $queueId,
                $orderId, // receipt_id olarak order_id kullanıyoruz
                $businessId,
                $printerId,
                $screenId,
                $printDataJson
            ]);
            
            return $queueId;
        } catch (\Exception $e) {
            error_log("ScreenReceiptService::addToPrintQueue - Error: " . $e->getMessage());
            return null;
        }
    }
    
    private function getOrderInfo(string $orderId): ?array {
        try {
            // NOTE: orders table uses tenant_id column (not business_id)
            // tables tablosunda masa adı: name; zones tablosunda bölge adı: name → "Bahçe Masa 32" için
            // İşletme: customers tablosunda kesin olan company_name, phone (address migration'da yok)
            $sql = "SELECT o.*,
                    t.name as table_name,
                    z.name as zone_name,
                    COALESCE(NULLIF(o.staff_name, ''), u.name, '') as waiter_name,
                    c.company_name as business_name,
                    c.phone as business_phone
                    FROM orders o
                    LEFT JOIN tables t ON o.table_id = t.table_id
                    LEFT JOIN zones z ON t.zone_id = z.zone_id
                    LEFT JOIN users u ON o.created_by = u.user_id
                    LEFT JOIN customers c ON o.tenant_id = c.customer_id
                    WHERE o.order_id = :order_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['order_id' => $orderId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            // Bölge + masa adı: "Bahçe Masa 32" formatı
            $zoneName = trim($row['zone_name'] ?? '');
            $tableName = trim($row['table_name'] ?? '');
            $row['table_display'] = $zoneName
                ? $zoneName . ' Masa ' . ($tableName ?: '-')
                : ($tableName ?: 'Paket Servis');
            
            if (empty(trim($row['waiter_name'] ?? ''))) {
                $createdBy = $row['created_by'] ?? '';
                $orderSource = strtoupper($row['order_source'] ?? '');
                if ($createdBy === 'customer' || $orderSource === 'QR') {
                    $row['waiter_name'] = 'QR Sipariş';
                } elseif (str_starts_with($createdBy, 'CUST_')) {
                    $row['waiter_name'] = 'Patron';
                }
            }
            return $row;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function getOrderItems(string $orderId): array {
        try {
            // Kasiyer'de çalışan OrderItemRepository'yi kullan (aynı SQL sorgusu)
            // Bu şekilde tutarlılık sağlanır ve item_name doğru gelir
            return $this->orderItemRepository->getByOrder($orderId);
        } catch (\Exception $e) {
            error_log("ScreenReceiptService::getOrderItems - Error: " . $e->getMessage());
            return [];
        }
    }
    
    // ESC/POS komutları
    private function escBold(bool $enable): string {
        return $enable ? "\x1B\x45\x01" : "\x1B\x45\x00";
    }
    
    private function escDoubleHeight(bool $enable): string {
        return $enable ? "\x1B\x21\x10" : "\x1B\x21\x00";
    }
    
    private function escCenter(): string {
        return "\x1B\x61\x01";
    }
    
    private function escLeft(): string {
        return "\x1B\x61\x00";
    }
    
    private function escCut(): string {
        return "\x1D\x56\x00";
    }

    private function trToAscii(string $text): string {
        $map = [
            'ş' => 's', 'Ş' => 'S', 'ğ' => 'g', 'Ğ' => 'G',
            'ü' => 'u', 'Ü' => 'U', 'ö' => 'o', 'Ö' => 'O',
            'ç' => 'c', 'Ç' => 'C', 'ı' => 'i', 'İ' => 'I',
            'â' => 'a', 'Â' => 'A', 'î' => 'i', 'Î' => 'I',
            'û' => 'u', 'Û' => 'U', '₺' => 'TL',
        ];
        return strtr($text, $map);
    }
}
