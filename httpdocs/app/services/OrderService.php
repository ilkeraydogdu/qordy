<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\OrderRepository;
use App\Core\DependencyFactory;
use App\Services\Order\OrderCalculator;
use App\Services\Order\OrderValidator;
use App\Services\Order\OrderItemManager;

class OrderService extends BaseService {
    private $orderItemService;
    private $menuItemService;
    private $categoryService;
    private $tableService;
    private $settingsService;
    private $notificationService;
    
    // Refactored components
    private $orderCalculator;
    private $orderValidator;
    private $orderItemManager;
    
    public function __construct(OrderRepository $orderRepository) {
        parent::__construct($orderRepository);
        
        // Use services instead of models
        $this->orderItemService = DependencyFactory::getOrderItemService();
        $this->menuItemService = DependencyFactory::getMenuItemService();
        $this->categoryService = DependencyFactory::getCategoryService();
        $this->tableService = DependencyFactory::getTableService();
        $this->settingsService = DependencyFactory::getSystemSettingsService();
        $this->notificationService = DependencyFactory::getNotificationService();
        
        // Initialize refactored components
        $this->orderCalculator = new OrderCalculator();
        $this->orderValidator = new OrderValidator();
        $this->orderItemManager = new OrderItemManager();
    }

    /**
     * Service-seviyesi tenant filtresi. orders.tenant_id sütununu kullanır.
     * TenantContext çözülemediyse (super-admin global view) filtre boş döner.
     *
     * @param string $alias Tablo alias (örn 'o'); boş bırakılırsa sütun direkt kullanılır.
     * @return array{where:string,params:array<string,string>}
     */
    private function tenantFilter(string $alias = ''): array {
        try {
            $tenantId = \App\Core\TenantResolver::resolve();
        } catch (\Throwable $e) {
            $tenantId = null;
        }
        if (!$tenantId) {
            return ['where' => '', 'params' => []];
        }
        $col = $alias ? ($alias . '.tenant_id') : 'tenant_id';
        return [
            'where'  => $col . ' = :tenant_filter_id',
            'params' => ['tenant_filter_id' => $tenantId],
        ];
    }
    
    /**
     * Place a new order OR add items to existing active order
     * If customer already has an active unpaid order for this table/session,
     * items are added to that order instead of creating a new one.
     * 
     * @param array $orderData Order data including table_id, items, customer_note, etc.
     * @return array ['success' => bool, 'order_id' => string|null, 'errors' => array]
     */
    public function placeOrder(array $orderData) {
        $tableId = $orderData['table_id'] ?? '';
        $items = $orderData['items'] ?? [];
        $customerNote = $orderData['customer_note'] ?? '';
        $isDelivery = $orderData['is_delivery'] ?? false;
        $orderSource = $orderData['order_source'] ?? 'QR';
        $customerSessionId = $orderData['customer_session_id'] ?? null;
        
        if (empty($tableId) && !$isDelivery) {
            return ['success' => false, 'errors' => ['Masa ID gerekli (teslimat harici)']];
        }
        
        if (empty($items) || !is_array($items)) {
            return ['success' => false, 'errors' => ['En az bir ürün gerekli']];
        }
        
        // Validate order data
        $validation = $this->orderValidator->validateOrder($orderData);
        if (!$validation['valid']) {
            \App\Core\Logger::warning('OrderService: Order validation failed', [
                'errors' => $validation['errors'],
            ]);
            return ['success' => false, 'errors' => $validation['errors']];
        }

        // --- Stock / availability guard ------------------------------------
        // Reject the order if any line-item is out of stock or currently marked
        // unavailable. This prevents the QR/POS flow from committing a sale for
        // a product whose inventory dropped to 0 since the menu was rendered.
        $stockErrors = [];
        $stockAggregate = []; // sum quantities across duplicate lines
        foreach ($items as $i) {
            $mid = $i['menu_item_id'] ?? '';
            if ($mid === '') continue;
            $stockAggregate[$mid] = ($stockAggregate[$mid] ?? 0) + (int)($i['quantity'] ?? 1);
        }
        foreach ($stockAggregate as $mid => $qtyRequested) {
            try {
                $mi = $this->menuItemService->getMenuItemById($mid);
            } catch (\Throwable $e) {
                $mi = null;
            }
            if (!$mi) continue;
            if ((int)($mi['is_available'] ?? 1) === 0) {
                $stockErrors[] = ($mi['name'] ?? $mid) . ' şu anda satışta değil';
                continue;
            }
            if ((int)($mi['track_stock'] ?? 0) === 1) {
                $available = (int)($mi['stock'] ?? 0);
                if ($available <= 0) {
                    $stockErrors[] = ($mi['name'] ?? $mid) . ' stokta yok';
                } elseif ($available < $qtyRequested) {
                    $stockErrors[] = ($mi['name'] ?? $mid) . ' için yeterli stok yok (mevcut: ' . $available . ')';
                }
            }
        }
        if (!empty($stockErrors)) {
            return ['success' => false, 'errors' => $stockErrors, 'code' => 'OUT_OF_STOCK'];
        }

        // Normalize items: ensure each has a valid price (from menu when client omits price)
        $items = $this->normalizeOrderItemsPrices($items);
        
        // Calculate total amount for new items
        $subtotal = $this->orderCalculator->calculateOrderTotal($items);
        
        // Get table info if not delivery
        $table = null;
        if (!$isDelivery && !empty($tableId)) {
            $table = $this->tableService->getTableById($tableId);
            if (!$table) {
                return ['success' => false, 'errors' => ['Masa bulunamadı']];
            }
        }
        
        // CRITICAL: Get tenant_id from table for tenant isolation (support business_id, tenant_id, customer_id)
        $tenantId = null;
        if ($table) {
            $tenantId = $table['tenant_id'] ?? $table['customer_id'] ?? null;
        }
        
        // If no tenant_id from table, try to get from session/context (for manual orders)
        if (!$tenantId) {
            $tenantId = \App\Core\TenantResolver::resolve();
            if (!$tenantId && class_exists('\\App\\Core\\TenantContext')) {
                try {
                    $tenantId = \App\Core\TenantContext::getId();
                } catch (\Exception $e) {
                    // TenantContext not available
                }
            }
        }
        
        // Calculate final total with service charge
        $finalTotal = $this->orderCalculator->calculateFinalTotal($subtotal);
        $newItemsTotal = $finalTotal['total'];
        
        // Check if order requires kitchen preparation
        $requiresKitchen = $this->orderValidator->checkIfOrderRequiresKitchen($items);
        
        // ============================================================
        // CRITICAL: Check for existing active (unpaid) order for this customer session
        // If found, ADD items to existing order instead of creating new
        // ============================================================
        $existingOrder = null;
        $isAddingToExisting = false;
        
        if (!$isDelivery && !empty($tableId) && $orderSource !== 'POS') {
            $existingOrder = $this->findActiveOrderForSession($tableId, $customerSessionId);
        }
        
        if ($existingOrder) {
            // ADD items to existing order
            $isAddingToExisting = true;
            $orderId = $existingOrder['order_id'];
            $existingTotal = floatval($existingOrder['total_amount'] ?? 0);
            $newTotalAmount = $existingTotal + $newItemsTotal;
            
            \App\Core\Logger::info('OrderService: Adding items to existing order', [
                'order_id' => $orderId,
                'existing_total' => $existingTotal,
                'new_items_total' => $newItemsTotal,
                'new_total' => $newTotalAmount,
                'customer_session_id' => $customerSessionId
            ]);
            
            $db = $this->repository->getDbConnection();
            $db->beginTransaction();
            
            try {
                // Count existing order items before adding new ones
                $preCountStmt = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ?");
                $preCountStmt->execute([$orderId]);
                $preCount = (int)$preCountStmt->fetchColumn();

                // Add new items to existing order
                $orderItems = $this->orderItemManager->batchCreateOrderItems($orderId, $items);
                
                if (empty($orderItems)) {
                    throw new \App\Exceptions\BusinessRuleException('Sipariş kalemleri oluşturulamadı');
                }
                
                // Atomic integrity check: count expected items vs actual saved items in database
                $expectedCount = $preCount + count($items);
                $actualCountStmt = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ?");
                $actualCountStmt->execute([$orderId]);
                $dbCount = (int)$actualCountStmt->fetchColumn();
                
                if ($dbCount !== $expectedCount) {
                    throw new \RuntimeException("Bütünlük Hatası: Beklenen sipariş kalemi sayısı {$expectedCount}, veritabanında bulunan {$dbCount}. Sipariş ekleme işlemi iptal ediliyor.");
                }
                
                // Update order total amount
                $this->repository->update($orderId, [
                    'total_amount' => $newTotalAmount,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
                // If order was READY or SERVED, reset to PENDING since new items need preparation
                $currentStatus = strtoupper($existingOrder['status'] ?? 'PENDING');
                if (in_array($currentStatus, ['READY', 'SERVED']) && $requiresKitchen) {
                    $this->repository->update($orderId, ['status' => 'PENDING']);
                }
                
                // Append customer note if provided
                if (!empty($customerNote)) {
                    $existingNote = trim($existingOrder['customer_note'] ?? '');
                    $combinedNote = $existingNote 
                        ? $existingNote . ' | ' . $customerNote 
                        : $customerNote;
                    $this->repository->update($orderId, ['customer_note' => $combinedNote]);
                }
                
                // Process ingredient customizations for new items
                if (!empty($orderData['customizations'])) {
                    try {
                        $customizationService = \App\Core\DependencyFactory::getIngredientCustomizationService();
                        foreach ($orderItems as $orderItem) {
                            $orderItemId = $orderItem['order_item_id'] ?? null;
                            $menuItemId = $orderItem['menu_item_id'] ?? null;
                            if ($orderItemId && isset($orderData['customizations'][$menuItemId])) {
                                $customizationService->createCustomizations($orderItemId, $orderData['customizations'][$menuItemId]);
                            }
                        }
                    } catch (\Exception $e) {
                        \App\Core\Logger::error('OrderService: Failed to create customizations for added items', [
                            'order_id' => $orderId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                $db->commit();
                $totalAmount = $newTotalAmount;
                
            } catch (\PDOException $e) {
                $db->rollBack();
                \App\Core\Logger::error('OrderService: PDOException adding items to existing order', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage()
                ]);
                return ['success' => false, 'errors' => [$e->getMessage()]];
            } catch (\Exception $e) {
                $db->rollBack();
                \App\Core\Logger::error('OrderService: Exception adding items to existing order', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage()
                ]);
                return ['success' => false, 'errors' => [$e->getMessage()]];
            }
        } else {
            // CREATE new order (original flow)
            $totalAmount = $newItemsTotal;
            
            // Determine initial order status based on kitchen requirement
            $initialStatus = $requiresKitchen ? 'PENDING' : 'READY';
            
            // Create order with configurable ID format
            $formattingService = DependencyFactory::getFormattingService();
            $orderId = $formattingService->generateOrderId();
            $orderRecord = [
                'order_id' => $orderId,
                'table_id' => $isDelivery ? null : $tableId,
                'table_name' => $isDelivery ? ($orderData['delivery_address'] ?? 'Teslimat') : ($table['name'] ?? ''),
                'status' => $initialStatus,
                'total_amount' => $totalAmount,
                'customer_note' => $customerNote,
                'order_source' => $orderSource,
                'is_delivery' => $isDelivery ? 1 : 0,
                'created_by' => $orderData['created_by'] ?? 'customer'
            ];
            
            // CRITICAL: Set tenant_id and business_id for tenant isolation
            if ($tenantId) {
                $orderRecord['tenant_id'] = $tenantId;
                $orderRecord['business_id'] = $tenantId;
            }
            
            // Set customer_session_id for per-customer tracking
            if ($customerSessionId) {
                $orderRecord['customer_session_id'] = $customerSessionId;
            }
            
            // Add delivery-specific fields
            if ($isDelivery) {
                $orderRecord['delivery_address'] = $orderData['delivery_address'] ?? '';
                $orderRecord['customer_phone'] = $orderData['customer_phone'] ?? '';
                if (isset($orderData['delivery_location_lat']) && isset($orderData['delivery_location_lng'])) {
                    $orderRecord['delivery_location_lat'] = floatval($orderData['delivery_location_lat']);
                    $orderRecord['delivery_location_lng'] = floatval($orderData['delivery_location_lng']);
                }
            }
            
            // CRITICAL: Use database transaction to ensure atomicity
            $db = $this->repository->getDbConnection();
            $db->beginTransaction();
            
            try {
                $orderResult = $this->repository->create($orderRecord);
                
                if (!$orderResult) {
                    throw new \App\Exceptions\BusinessRuleException('Sipariş kaydedilemedi');
                }
                
                // Batch create order items (optimize multiple inserts)
                $orderItems = $this->orderItemManager->batchCreateOrderItems($orderId, $items);
                
                if (empty($orderItems)) {
                    throw new \App\Exceptions\BusinessRuleException('Sipariş kalemleri oluşturulamadı');
                }
                
                // Atomic integrity check: count expected items vs actual saved items in database
                $expectedCount = count($items);
                $actualCountStmt = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ?");
                $actualCountStmt->execute([$orderId]);
                $dbCount = (int)$actualCountStmt->fetchColumn();
                
                if ($dbCount !== $expectedCount) {
                    throw new \RuntimeException("Bütünlük Hatası: Beklenen sipariş kalemi sayısı {$expectedCount}, veritabanında bulunan {$dbCount}. Sipariş iptal ediliyor.");
                }
                
                // Process ingredient customizations if provided (within transaction)
                if (!empty($orderData['customizations'])) {
                    try {
                        require_once __DIR__ . '/../core/DependencyFactory.php';
                        $customizationService = \App\Core\DependencyFactory::getIngredientCustomizationService();
                        
                        foreach ($orderItems as $orderItem) {
                            $orderItemId = $orderItem['order_item_id'] ?? null;
                            $menuItemId = $orderItem['menu_item_id'] ?? null;
                            
                            if ($orderItemId && isset($orderData['customizations'][$menuItemId])) {
                                $customizations = $orderData['customizations'][$menuItemId];
                                $customizationService->createCustomizations($orderItemId, $customizations);
                            }
                        }
                    } catch (\Exception $e) {
                        \App\Core\Logger::error('OrderService: Failed to create customizations', [
                            'order_id' => $orderId,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }
                
                $db->commit();
                
            } catch (\PDOException $e) {
                $db->rollBack();
                \App\Core\Logger::error('OrderService: PDOException creating order', [
                    'order_id' => $orderId ?? 'unknown',
                    'items' => $items,
                    'error' => $e->getMessage(),
                    'sql_state' => $e->errorInfo[0] ?? 'unknown',
                    'error_code' => $e->errorInfo[1] ?? 'unknown',
                    'error_message' => $e->errorInfo[2] ?? 'unknown',
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                \App\Core\Logger::error('OrderService: PDOException during placeOrder', [
                    'message'   => $e->getMessage(),
                    'sqlstate'  => $e->errorInfo[0] ?? 'unknown',
                    'driver_msg'=> $e->errorInfo[2] ?? 'unknown',
                ]);
                return ['success' => false, 'errors' => [$e->getMessage()]];
            } catch (\Exception $e) {
                $db->rollBack();
                \App\Core\Logger::error('OrderService: Exception creating order', [
                    'order_id' => $orderId ?? 'unknown',
                    'items' => $items,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                \App\Core\Logger::error('OrderService: Exception during placeOrder', [
                    'error' => $e->getMessage(),
                ]);
                return ['success' => false, 'errors' => [$e->getMessage()]];
            }
        }
        
        // Transaction committed successfully, continue with post-order operations
        // (These operations are outside transaction as they are not critical for order creation)
        
        // CRITICAL: Update table status if not delivery - ALWAYS update to OCCUPIED when order is created
                if (!$isDelivery && $tableId) {
                    try {
                        // Always set to OCCUPIED when order is created (regardless of current status)
                        // This ensures waiter dashboard shows correct status immediately
                        $this->tableService->updateTableStatus($tableId, 'OCCUPIED');
                        \App\Core\Logger::info('OrderService: Updated table status to OCCUPIED', [
                            'table_id' => $tableId,
                            'order_id' => $orderId
                        ]);
                    } catch (\Exception $e) {
                        \App\Core\Logger::error('OrderService: Failed to update table status', [
                            'table_id' => $tableId,
                            'order_id' => $orderId,
                            'error' => $e->getMessage()
                        ]);
                        // Continue - table status update is not critical for order creation
                    }
                }
                
                // AFTER COMMIT: Generate preparation receipts automatically (köprü masaüstü uygulamasına gönderilir)
                try {
                    $receiptService = DependencyFactory::getReceiptService();
                    $preparationScreenService = DependencyFactory::getPreparationScreenService();
                    
                    // Re-fetch items from DB with enrichment (excluded_ingredients, selected_extras)
                    // so preparation receipts include customization data
                    try {
                        $enrichedItems = $this->orderItemService->getOrderItemsByOrder($orderId);
                        if (!empty($enrichedItems)) {
                            $orderItems = $enrichedItems;
                        }
                    } catch (\Exception $enrichEx) {
                        \App\Core\Logger::warning('OrderService: Failed to re-fetch enriched items, using originals', [
                            'order_id' => $orderId,
                            'error' => $enrichEx->getMessage()
                        ]);
                    }
                    
                    // business_id for print queue MUST match printer_bridges.business_id so bridge receives jobs
                    $businessIdForReceipts = $tenantId;
                    if (!$businessIdForReceipts) {
                        $orderRow = $this->repository->findById($orderId);
                        $businessIdForReceipts = $orderRow['tenant_id'] ?? null;
                    }
                    if (!$businessIdForReceipts) {
                        $businessIdForReceipts = \App\Core\TenantResolver::resolve();
                    }
                    if (!$businessIdForReceipts) {
                        \App\Core\Logger::warning('OrderService: No business_id for preparation receipts – köprü yazdırma atlanıyor', [
                            'order_id' => $orderId,
                            'table_id' => $tableId ?? null
                        ]);
                        // Skip receipt generation; bridge will not receive jobs without business_id
                    } else {
                    \App\Core\Logger::info('OrderService: preparation receipt business_id (köprü kuyruğu ile eşleşmeli)', [
                        'order_id' => $orderId,
                        'business_id' => $businessIdForReceipts
                    ]);
                    // Ensure tenant context so getScreenBySlug / getAllScreens return tenant's screens
                    if (class_exists('\App\Core\TenantContext')) {
                        \App\Core\TenantContext::setId($businessIdForReceipts);
                    }
                    
                    // Kitchen always uses 'kitchen_main' screen (handled by KitchenController)
                    // Preparation screens are ONLY for non-kitchen screens (Nargile, Bar, etc.)
                    $kitchenScreenId = 'kitchen_main';
                    
                    // Group items by screen (using both direct assignment and category-based mapping)
                    $itemsByScreen = [];
                    
                    foreach ($orderItems as $orderItem) {
                        $menuItemId = $orderItem['menu_item_id'] ?? null;
                        
                        if (!$menuItemId) {
                            continue;
                        }
                        
                        // Get menu item to find preparation_screen_id
 $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
                        if (!$menuItem) {
                            continue;
                        }
                        
                        // Skip direct service products
                        $productionPoint = $menuItem['production_point'] ?? null;
                        $isDirectService = !empty($menuItem['is_direct_service']) && $menuItem['is_direct_service'] == 1;
                        if ($productionPoint === 'NONE' || $isDirectService) {
                            continue;
                        }
                        
                        $screenId = null;
                        
                        // Priority 1: Check if menu item is directly assigned to a screen
                        $itemPreparationScreenId = $menuItem['preparation_screen_id'] ?? null;
                        if (!empty($itemPreparationScreenId)) {
                            $screenId = $itemPreparationScreenId;
                        } else {
                            // Priority 2: Fallback to category-based assignment
                            $itemCategoryId = $menuItem['category_id'] ?? '';
                            if (!empty($itemCategoryId)) {
                                // Find screens that have this category assigned
                                $allScreens = $preparationScreenService->getAllScreens();
                                foreach ($allScreens as $screen) {
                                    $screenCategoryIds = $preparationScreenService->getScreenCategoryIds($screen['screen_id']);
                                    if (in_array($itemCategoryId, $screenCategoryIds)) {
                                        $screenId = $screen['screen_id'];
                                        break; // Use first matching screen
                                    }
                                }
                                
                                // Priority 2b: Nargile keyword fallback (tutarlılık - getOrdersForScreen ile aynı mantık)
                                // Eğer kategori "nargile/hookah/shisha" içeriyorsa ve bir nargile ekranı varsa
                                if (empty($screenId)) {
                                    foreach ($allScreens as $screen) {
                                        $sName = strtolower(trim($screen['name'] ?? ''));
                                        $sSlug = strtolower(trim($screen['slug'] ?? ''));
                                        $isNargile = (strpos($sName, 'nargile') !== false || strpos($sSlug, 'nargile') !== false ||
                                                     strpos($sName, 'hookah') !== false || strpos($sSlug, 'hookah') !== false);
                                        if ($isNargile) {
                                            // Kategori adını kontrol et
                                            try {
                                                $catService = DependencyFactory::getCategoryService();
                                                $cat = $catService->getCategoryById($itemCategoryId);
                                                if ($cat) {
                                                    $catName = strtolower(trim($cat['name'] ?? ''));
                                                    if (strpos($catName, 'nargile') !== false || strpos($catName, 'hookah') !== false || strpos($catName, 'shisha') !== false) {
                                                        $screenId = $screen['screen_id'];
                                                        break;
                                                    }
                                                }
                                            } catch (\Exception $ncEx) { /* fallback yok */ }
                                        }
                                    }
                                }
                            }
                            
                            // Priority 3: Default to kitchen if no specific screen found
                            if (empty($screenId)) {
                                if ($productionPoint === 'KITCHEN' || 
                                    (isset($menuItem['requires_kitchen']) && intval($menuItem['requires_kitchen']) == 1)) {
                                    $screenId = $kitchenScreenId ?? 'KITCHEN';
                                } else if ($productionPoint !== 'NONE') {
                                    // Default: if no specific screen and needs preparation, send to kitchen
                                    $screenId = $kitchenScreenId ?? 'KITCHEN';
                                }
                            }
                        }
                        
                        // Use resolved kitchen screen_id when we have literal 'KITCHEN'
                        if ($screenId === 'KITCHEN' && $kitchenScreenId) {
                            $screenId = $kitchenScreenId;
                        }
                        
                        // Group items by screen
                        if ($screenId) {
                            if (!isset($itemsByScreen[$screenId])) {
                                $itemsByScreen[$screenId] = [];
                            }
                            // CRITICAL: Add item_name from menu item to prevent 'Urun' on receipts
                            $orderItem['item_name'] = $menuItem['name'] ?? $menuItem['item_name'] ?? 'Ürün';
                            $itemsByScreen[$screenId][] = $orderItem;
                        }
                    }
                    
                    // Generate ONE preparation receipt per screen with ALL items for that screen
                    foreach ($itemsByScreen as $screenId => $screenItems) {
                        // Collect all customizations for items on this screen
                        $screenCustomizations = [];
                        foreach ($screenItems as $orderItem) {
                            $menuItemId = $orderItem['menu_item_id'] ?? null;
                            if ($menuItemId && !empty($orderData['customizations'][$menuItemId])) {
                                $screenCustomizations[$menuItemId] = $orderData['customizations'][$menuItemId];
                            }
                        }
                        
                        // Generate ONE preparation receipt for this screen with ALL items (queue = bridge alır)
                        $receiptService->generatePreparationReceipt([
                            'order_id' => $orderId,
                            'screen_id' => $screenId,
                            'business_id' => $businessIdForReceipts,
                            'items' => $screenItems, // All items for this screen
                            'customizations' => $screenCustomizations
                        ]);
                        
                        \App\Core\Logger::info('OrderService: Generated preparation receipt', [
                            'order_id' => $orderId,
                            'screen_id' => $screenId,
                            'item_count' => count($screenItems)
                        ]);
                    }
                    } // end else (businessIdForReceipts set)
                } catch (\Exception $e) {
                    \App\Core\Logger::error('OrderService: Failed to generate preparation receipts', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Don't fail the order if receipt generation fails
                }
                
                // Create notification - but NOT for orders created by staff/waiter (POS orders)
                // Only notify for customer orders (QR orders) to avoid notifying waiter about their own orders
                if ($this->notificationService && $orderSource !== 'POS') {
                    try {
                        $tableName = $isDelivery ? 'Teslimat' : ($table['name'] ?? '');
                        if ($isAddingToExisting) {
                            // Notify about items added to existing order
                            $this->notificationService->notifyNewOrder($tableId ?? 'delivery', $tableName . ' (ek sipariş)', $orderId);
                        } else {
                            $this->notificationService->notifyNewOrder($tableId ?? 'delivery', $tableName, $orderId);
                        }
                    } catch (\Exception $e) {
                        \App\Core\Logger::warning('OrderService: Failed to create notification', [
                            'order_id' => $orderId,
                            'error' => $e->getMessage()
                        ]);
                        // Continue - notification is not critical
                    }
                } else if ($orderSource === 'POS') {
                    \App\Core\Logger::info('OrderService: Skipping notification for POS order (waiter/staff created)', [
                        'order_id' => $orderId,
                        'order_source' => $orderSource
                    ]);
                }
                
                // Broadcast order created event via WebSocket
                try {
                    require_once __DIR__ . '/WebSocketBroadcaster.php';
                    $order = $this->repository->findById($orderId);
                    if ($order) {
                        \App\Services\WebSocketBroadcaster::broadcastOrder('created', $order);
                    }
                } catch (\Exception $e) {
                    \App\Core\Logger::warning("WebSocket broadcast failed", ['error' => $e->getMessage()]);
                    // Continue - WebSocket broadcast is not critical
                }
                
                // CRITICAL: Clear order cache after creating new order
                try {
                    $cache = \App\Core\DependencyFactory::getCacheService();
                    
                    // Clear all order-related caches
                    $cache->delete('orders:all');
                    $cache->delete('orders:active');
                    $cache->delete('orders:pending');
                    
                    // Clear table-specific order cache
                    if (!$isDelivery && $tableId) {
                        $cache->delete("orders:table:{$tableId}");
                        $cache->delete("table:{$tableId}:orders");
                    }
                    
                    // Clear tenant-specific order cache
                    if ($tenantId) {
                        $cache->delete("orders:tenant:{$tenantId}");
                        $cache->delete("orders:business:{$tenantId}");
                    }
                    
                    \App\Core\Logger::debug('OrderService: Order cache cleared after placeOrder', [
                        'order_id' => $orderId,
                        'table_id' => $tableId,
                        'tenant_id' => $tenantId
                    ]);
                } catch (\Exception $e) {
                    \App\Core\Logger::warning('OrderService: Cache clear failed after placeOrder', [
                        'error' => $e->getMessage()
                    ]);
                    // Cache error is not critical, continue
                }
                
        return ['success' => true, 'order_id' => $orderId, 'total_amount' => $totalAmount];
    }
    
    /**
     * Find active (unpaid, not cancelled/served) order for a customer session at a table
     * Used for order consolidation - so repeated orders go to the same order_id
     * 
     * @param string $tableId Table ID
     * @param string|null $customerSessionId Customer session ID (if null, uses table-level matching)
     * @return array|null Existing active order or null
     */
    public function findActiveOrderForSession(string $tableId, ?string $customerSessionId = null): ?array {
        try {
            $db = $this->repository->getDbConnection();

            // Tenant isolation: eğer TenantContext çözüldüyse bunu zorunlu filtre
            // olarak ekleyelim. Çözülemediyse (public QR flow'ları) geri düşüp
            // eski davranışı sürdürüyoruz — orders.table_id global unique olsa
            // bile, tenant_id filtresi savunma derinliğidir.
            $tenantId = null;
            if (class_exists('\App\Core\TenantContext')) {
                try { $tenantId = \App\Core\TenantContext::getId(); } catch (\Throwable $e) { $tenantId = null; }
            }

            $tenantClause = $tenantId ? ' AND tenant_id = :tenant_id' : '';

            if ($customerSessionId) {
                $sql = "
                    SELECT * FROM orders 
                    WHERE table_id = :table_id 
                    AND customer_session_id = :customer_session_id
                    AND is_paid = 0 
                    AND status NOT IN ('SERVED', 'CANCELLED')
                    {$tenantClause}
                    ORDER BY created_at DESC 
                    LIMIT 1
                ";
                $stmt = $db->prepare($sql);
                $params = [
                    'table_id' => $tableId,
                    'customer_session_id' => $customerSessionId,
                ];
                if ($tenantId) { $params['tenant_id'] = $tenantId; }
                $stmt->execute($params);
                $order = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($order) {
                    \App\Core\Logger::info('OrderService: Found active order by customer_session_id', [
                        'order_id' => $order['order_id'],
                        'customer_session_id' => $customerSessionId,
                        'table_id' => $tableId,
                        'tenant_id' => $tenantId,
                    ]);
                    return $order;
                }
            }
            
            // Fallback: eski QR akışı (customer_session_id yok).
            if (!$customerSessionId) {
                $sql = "
                    SELECT * FROM orders 
                    WHERE table_id = :table_id 
                    AND (customer_session_id IS NULL OR customer_session_id = '')
                    AND is_paid = 0 
                    AND status NOT IN ('SERVED', 'CANCELLED')
                    AND order_source = 'QR'
                    {$tenantClause}
                    ORDER BY created_at DESC 
                    LIMIT 1
                ";
                $stmt = $db->prepare($sql);
                $params = ['table_id' => $tableId];
                if ($tenantId) { $params['tenant_id'] = $tenantId; }
                $stmt->execute($params);
                $order = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($order) {
                    return $order;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            \App\Core\Logger::error('OrderService: Error finding active order for session', [
                'table_id' => $tableId,
                'customer_session_id' => $customerSessionId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get active order for a customer session (public method for API use)
     * @param string $tableId Table ID
     * @param string|null $customerSessionId Customer session ID
     * @return array|null Active order or null
     */
    public function getActiveOrderForCustomer(string $tableId, ?string $customerSessionId = null): ?array {
        return $this->findActiveOrderForSession($tableId, $customerSessionId);
    }
    
    /**
     * End all customer sessions for a table after payment
     * Called when order is marked as paid
     * 
     * @param string $tableId Table ID
     * @return void
     */
    public function endTableSessionsAfterPayment(string $tableId): void {
        try {
            $customerSessionService = DependencyFactory::getCustomerSessionService();
            $customerSessionService->clearSessionsByTable($tableId);
            
            \App\Core\Logger::info('OrderService: Ended customer sessions after payment', [
                'table_id' => $tableId
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::warning('OrderService: Failed to end customer sessions after payment', [
                'table_id' => $tableId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Normalize order items: set price from menu when client omits or sends invalid price.
     * Ensures QR/mobile clients can send only menu_item_id + quantity without price.
     * @param array $items Order items from request
     * @return array Items with price set (from menu + variant modifier if any)
     */
    private function normalizeOrderItemsPrices(array $items): array {
        $normalized = [];
        foreach ($items as $item) {
            $menuItemId = $item['menu_item_id'] ?? '';
            if (empty($menuItemId)) {
                $normalized[] = $item;
                continue;
            }
            $hasValidPrice = isset($item['price']) && is_numeric($item['price']) && floatval($item['price']) > 0;
            if ($hasValidPrice) {
                $normalized[] = $item;
                continue;
            }
            $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
            if (!$menuItem) {
                $normalized[] = $item;
                continue;
            }
            $price = floatval($menuItem['price'] ?? 0);
            $variantId = $item['variant_id'] ?? null;
            if ($variantId && !empty($menuItem['has_variants']) && $menuItem['has_variants'] == 1) {
                try {
                    $productVariantService = DependencyFactory::getProductVariantService();
                    $variants = $productVariantService->getVariantsByProduct($menuItemId);
                    foreach ($variants as $v) {
                        if (($v['variant_id'] ?? '') === $variantId) {
                            $price += floatval($v['price_modifier'] ?? 0);
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    // ignore
                }
            }
            $item['price'] = $price;
            $normalized[] = $item;
        }
        return $normalized;
    }
    
    /**
     * Calculate total amount for order items
     * @param array $items Order items
     * @return float Total amount
     */
    private function calculateOrderTotal(array $items): float {
        $totalAmount = 0;
        
        foreach ($items as $item) {
            $menuItem = $this->menuItemService->getMenuItemById($item['menu_item_id'] ?? '');
            if ($menuItem) {
                $itemPrice = floatval($menuItem['price'] ?? 0);
                $extrasPrice = 0;
                
                if (isset($item['extras']) && is_array($item['extras'])) {
                    foreach ($item['extras'] as $extra) {
                        $extrasPrice += floatval($extra['price'] ?? 0);
                    }
                }
                
                $quantity = intval($item['quantity'] ?? 1);
                $totalAmount += ($itemPrice + $extrasPrice) * $quantity;
            }
        }
        
        return $totalAmount;
    }
    
    /**
     * Create an order item
     * @param string $orderId Order ID
     * @param array $item Item data
     * @return bool Success
     */
    private function createOrderItem(string $orderId, array $item): bool {
        $orderItemData = [
            'order_item_id' => generateId('oi'),
            'order_id' => $orderId,
            'menu_item_id' => $item['menu_item_id'] ?? '',
            'quantity' => intval($item['quantity'] ?? 1),
            'price' => floatval($item['price'] ?? 0),
            'note' => $item['note'] ?? ''
        ];
        
        $result = $this->orderItemService->createOrderItem($orderItemData);
        
        // Update stock and record stock movement
        if ($result && !empty($item['menu_item_id'])) {
            $this->menuItemService->updateStock($item['menu_item_id'], intval($item['quantity'] ?? 1));

            // Record stock movement for the menu item
            try {
                $stockMovementService = \App\Core\DependencyFactory::getStockMovementService();
                $movementData = [
                    'item_type' => 'MENU_ITEM',
                    'item_id' => $item['menu_item_id'],
                    'movement_type' => 'OUT',
                    'quantity' => floatval($item['quantity'] ?? 1),
                    'reference_type' => 'ORDER',
                    'reference_id' => $orderId,
                    'description' => 'Sipariş #' . $orderId . ' için stok çıkışı',
                    'created_by' => $item['created_by'] ?? 'system'
                ];

                $stockMovementService->recordMovement($movementData);
            } catch (\Exception $e) {
                // Log error but don't fail the order creation
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('Failed to record stock movement for menu item', [
                        'error' => $e->getMessage(),
                        'menu_item_id' => $item['menu_item_id'] ?? null,
                        'order_id' => $orderId,
                        'quantity' => $item['quantity'] ?? 1
                    ]);
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Check if order requires kitchen preparation (delegated to OrderValidator)
     * @deprecated Use $this->orderValidator->checkIfOrderRequiresKitchen() instead
     */
    private function checkIfOrderRequiresKitchen(array $items): bool {
        return $this->orderValidator->checkIfOrderRequiresKitchen($items);
    }
    
    /**
     * Batch create order items (delegated to OrderItemManager)
     * @deprecated Use $this->orderItemManager->batchCreateOrderItems() instead
     */
    private function batchCreateOrderItems(string $orderId, array $items): bool {
        return $this->orderItemManager->batchCreateOrderItems($orderId, $items);
    }
    
    /**
     * Update order status
     * @param string $orderId Order ID
     * @param string $status New status
     * @return bool Success
     */
    public function updateOrderStatus(string $orderId, string $status): bool {
        $validStatuses = [
            'PENDING', 'PREPARING', 'READY', 'SERVED', 'CANCELLED', 'ISSUE',
            'ON_DELIVERY', 'DELIVERED'
        ];
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }
        
        $result = $this->repository->update($orderId, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result) {
            $statusServed = defined('ORDER_STATUS_SERVED') ? ORDER_STATUS_SERVED : 'SERVED';
            if ($status === $statusServed) {
                try {
                    $items = $this->orderItemService->getOrderItemsByOrder($orderId);
                    $ids = array_column($items, 'order_item_id');
                    if (!empty($ids)) {
                        $this->orderItemService->updatePreparationStatusByIds($ids, 'SERVED');
                    }
                } catch (\Exception $e) {
                    \App\Core\Logger::warning('OrderService: Failed to set item preparation_status SERVED', ['order_id' => $orderId, 'error' => $e->getMessage()]);
                }
            }
            // CRITICAL: If order is SERVED or paid, end customer sessions
            $statusServedCheck = defined('ORDER_STATUS_SERVED') ? ORDER_STATUS_SERVED : 'SERVED';
            if ($status === $statusServedCheck) {
                $order = $this->repository->findById($orderId);
                if ($order && !empty($order['table_id']) && !empty($order['is_paid']) && $order['is_paid'] != '0') {
                    // Check if there are other unpaid orders for this table
                    $otherActiveOrders = $this->getActiveOrdersByTable($order['table_id']);
                    $hasOtherActive = false;
                    foreach ($otherActiveOrders as $otherOrder) {
                        if ($otherOrder['order_id'] !== $orderId) {
                            $hasOtherActive = true;
                            break;
                        }
                    }
                    if (!$hasOtherActive) {
                        $this->endTableSessionsAfterPayment($order['table_id']);
                    }
                }
            }
            
            // Check if status is READY using constant (dynamic, not hardcoded)
            $statusReady = defined('ORDER_STATUS_READY') ? ORDER_STATUS_READY : 'READY';
            if ($status === $statusReady && $this->notificationService) {
                $order = $this->repository->findById($orderId);
                if ($order) {
                    $this->notificationService->notifyOrderReady(
                        $order['table_id'] ?? '',
                        $order['table_name'] ?? '',
                        $orderId
                    );
                }
            }
            
            // Broadcast order updated event via WebSocket
            try {
                require_once __DIR__ . '/WebSocketBroadcaster.php';
                $order = $this->repository->findById($orderId);
                if ($order) {
                    \App\Services\WebSocketBroadcaster::broadcastOrder('updated', $order);
                }
            } catch (\Exception $e) {
                \App\Core\Logger::warning("WebSocket broadcast failed", ['error' => $e->getMessage()]);
            }
            
            // CRITICAL: Clear order cache after status update
            try {
                $cache = \App\Core\DependencyFactory::getCacheService();
                $order = $this->repository->findById($orderId);
                
                // Clear all order-related caches
                $cache->delete('orders:all');
                $cache->delete('orders:active');
                $cache->delete('orders:pending');
                $cache->delete("order:{$orderId}");
                
                // Clear table-specific order cache
                if ($order && isset($order['table_id'])) {
                    $cache->delete("orders:table:{$order['table_id']}");
                    $cache->delete("table:{$order['table_id']}:orders");
                }
                
                // Clear tenant-specific order cache
                if ($order && isset($order['tenant_id'])) {
                    $cache->delete("orders:tenant:{$order['tenant_id']}");
                    $cache->delete("orders:business:{$order['tenant_id']}");
                }
                
                \App\Core\Logger::debug('OrderService: Order cache cleared after updateOrderStatus', [
                    'order_id' => $orderId,
                    'new_status' => $status
                ]);
            } catch (\Exception $e) {
                \App\Core\Logger::warning('OrderService: Cache clear failed after updateOrderStatus', [
                    'error' => $e->getMessage()
                ]);
                // Cache error is not critical, continue
            }
        }
        
        return $result;
    }
    
    /**
     * Get orders by status
     * @param string $status Order status
     * @return array Orders
     */
    public function getOrdersByStatus(string $status): array {
        return $this->repository->getByStatus($status);
    }

    /**
     * Get delivery orders by status (only orders where is_delivery = 1)
     * @param string $status Order status
     * @return array Delivery orders
     */
    public function getDeliveryOrdersByStatus(string $status): array {
        return $this->repository->getDeliveryOrdersByStatus($status);
    }
    
    /**
     * Get active orders (PENDING, PREPARING, READY)
     * @param bool $kitchenOnly If true, only return orders that require kitchen preparation
     * @return array Active orders
     */
    public function getActiveOrders(bool $kitchenOnly = false, string $productionPointFilter = null): array {
        $orders = $this->repository->getActiveOrders();

        if ($kitchenOnly || $productionPointFilter) {
            // Determine which production point to filter by
            $targetProductionPoint = $productionPointFilter ?: 'KITCHEN';

            // Filter orders and their items based on production point
            $filteredOrders = [];

            // === N+1 FIX: Preload all order items + menu items in batch ===
            // Collect order IDs and menu_item_ids to avoid per-item queries below
            $orderIds = array_filter(array_map(fn($o) => $o['order_id'] ?? '', $orders));
            $allItemsByOrder = [];
 $flatItems = $this->orderItemService->getOrderItemsByOrderIds($orderIds);
 foreach ($flatItems as $it) {
 $oid = $it['order_id'] ?? '';
 if ($oid !== '') {
 $allItemsByOrder[$oid][] = $it;
 }
 }
            $allMenuItemIds = [];
            foreach ($allItemsByOrder as $oid => $items) {
                foreach ($items as $it) {
                    if (!empty($it['menu_item_id'])) {
                        $allMenuItemIds[] = $it['menu_item_id'];
                    }
                }
            }
            $menuItemsMap = $this->menuItemService->getMenuItemsByIds($allMenuItemIds);
            // ============================================================

            foreach ($orders as $order) {
                $orderId = $order['order_id'] ?? '';
                if (empty($orderId)) {
                    continue;
                }

                // Get order items (from preloaded batch)
                $orderItems = $allItemsByOrder[$orderId] ?? [];
                $matchingItems = [];

                foreach ($orderItems as $item) {
                    $menuItemId = $item['menu_item_id'] ?? '';
                    if (empty($menuItemId)) {
                        continue;
                    }

 $menuItem = $menuItemsMap[$menuItemId] ?? null;
                    if (!$menuItem) {
                        continue;
                    }

                    // Get production_point from menu item or category default
                    $productionPoint = null;

                    // First check menu item's production_point
                    if (!empty($menuItem['production_point'])) {
                        $productionPoint = $menuItem['production_point'];
                    } else {
                        // If not set, check category's default_production_point
                        if (!empty($menuItem['category_id'])) {
                            $category = $this->categoryService->getCategoryById($menuItem['category_id']);
                            if ($category && !empty($category['default_production_point'])) {
                                $productionPoint = $category['default_production_point'];
                            }
                        }
                    }

                    // Check if production_point matches target
                    $itemMatches = false;
                    if ($productionPoint === $targetProductionPoint) {
                        $itemMatches = true;
                    }

                    // NEW LOGIC: If no production_point is set for menu item or category, default to KITCHEN
                    // This ensures that items without a specific preparation screen go to the kitchen screen
                    if (empty($productionPoint) && $targetProductionPoint === 'KITCHEN') {
                        $itemMatches = true;
                    }

                    // Fallback: Check legacy requires_kitchen field for backward compatibility (only for KITCHEN filter)
                    if ($targetProductionPoint === 'KITCHEN' && !$itemMatches) {
                        if (isset($menuItem['requires_kitchen']) && intval($menuItem['requires_kitchen']) == 1) {
                            $itemMatches = true;
                        }

                        if (!$itemMatches && !empty($menuItem['category_id'])) {
                            $category = $this->categoryService->getCategoryById($menuItem['category_id']);
                            if ($category && isset($category['requires_kitchen']) && intval($category['requires_kitchen']) == 1) {
                                $itemMatches = true;
                            }
                        }
                    }

                    // Skip items that are marked as direct service (no preparation needed)
                    $isDirectService = !empty($menuItem['is_direct_service']) && $menuItem['is_direct_service'] == 1;
                    if ($isDirectService) {
                        $itemMatches = false; // Don't send to any preparation screen
                    }

                    // When showing main kitchen only: exclude items assigned to another preparation screen (bar, nargile, etc.)
                    // Items go to a specific screen if:
                    //   1) preparation_screen_id is set (direct assignment)
                    //   2) Item's category is assigned to a preparation screen (category-based)
                    // These items should NOT appear on kitchen_main to avoid duplicates
                    if ($itemMatches && $targetProductionPoint === 'KITCHEN') {
                        $itemPreparationScreenId = $menuItem['preparation_screen_id'] ?? null;
                        if (!empty($itemPreparationScreenId)) {
                            $itemMatches = false; // Direct assignment → hazırlık ekranına gider, mutfağa değil
                        } else {
                            // Category-based check: if item's category is assigned to ANY preparation screen,
                            // it should go there, not to kitchen_main
                            $itemCategoryId = $menuItem['category_id'] ?? '';
                            if (!empty($itemCategoryId)) {
                                try {
                                    $prepScreenService = DependencyFactory::getPreparationScreenService();
                                    $allPrepScreens = $prepScreenService->getAllScreens();
                                    foreach ($allPrepScreens as $prepScreen) {
                                        $screenCatIds = $prepScreenService->getScreenCategoryIds($prepScreen['screen_id']);
                                        if (in_array($itemCategoryId, $screenCatIds)) {
                                            $itemMatches = false; // Category assigned to a prep screen → not kitchen
                                            break;
                                        }
                                    }
                                } catch (\Exception $catEx) {
                                    // If service unavailable, keep item in kitchen (safe fallback)
                                }
                            }
                        }
                    }

                    // Add item to matching items if it belongs to this production point
                    if ($itemMatches) {
                        $matchingItems[] = $item;
                    }
                }

                // Only include order if it has at least one item for this production point
                if (!empty($matchingItems)) {
                    // Clone order and set filtered items
                    $filteredOrder = $order;
                    $filteredOrder['items'] = $matchingItems;
                    $filteredOrders[] = $filteredOrder;
                }
            }

            return $filteredOrders;
        }

        return $orders;
    }
    
    /**
     * Check if an order has any items that require kitchen preparation.
     * Used for filtering notifications - kitchen staff should only get notified for kitchen-relevant orders.
     * @param string $orderId Order ID
     * @return bool True if order has at least one kitchen item
     */
    public function orderHasKitchenItems(string $orderId): bool {
        if (empty($orderId)) {
            return false;
        }
        try {
            $kitchenOrders = $this->getActiveOrders(true, 'KITCHEN');
            foreach ($kitchenOrders as $order) {
                if (($order['order_id'] ?? '') === $orderId) {
                    return !empty($order['items'] ?? []);
                }
            }
            // Order might not be in active list (e.g. just created) - check items directly
            $orderItems = $this->orderItemService->getOrderItemsByOrder($orderId);
            foreach ($orderItems as $item) {
                $menuItemId = $item['menu_item_id'] ?? '';
                if (empty($menuItemId)) continue;
                $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
                if (!$menuItem) continue;
                // Skip direct-service items (no preparation)
                if (!empty($menuItem['is_direct_service']) && (int)$menuItem['is_direct_service'] === 1) continue;
                // Item assigned to a preparation screen (bar, nargile) → not kitchen
                if (!empty($menuItem['preparation_screen_id'])) continue;
                // Category assigned to a preparation screen → not kitchen
                if (!empty($menuItem['category_id'])) {
                    try {
                        $prepScreenService = DependencyFactory::getPreparationScreenService();
                        foreach ($prepScreenService->getAllScreens() as $screen) {
                            $screenCatIds = $prepScreenService->getScreenCategoryIds($screen['screen_id']);
                            if (in_array($menuItem['category_id'], $screenCatIds)) continue 2; // skip this item
                        }
                    } catch (\Exception $e) {}
                }
                $productionPoint = $menuItem['production_point'] ?? null;
                if (empty($productionPoint) && !empty($menuItem['category_id'])) {
                    $category = $this->categoryService->getCategoryById($menuItem['category_id']);
                    $productionPoint = $category['default_production_point'] ?? null;
                }
                if ($productionPoint === 'KITCHEN') return true;
                if (empty($productionPoint)) return true; // Default to kitchen
                if (isset($menuItem['requires_kitchen']) && (int)$menuItem['requires_kitchen'] === 1) return true;
                if (!empty($menuItem['category_id'])) {
                    $category = $this->categoryService->getCategoryById($menuItem['category_id']);
                    if ($category && isset($category['requires_kitchen']) && (int)$category['requires_kitchen'] === 1) return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            \App\Core\Logger::warning('OrderService::orderHasKitchenItems error', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get active order statuses for kitchen display
     * @return array Active status codes
     */
    public function getActiveOrderStatuses(): array {
        return [
            defined('ORDER_STATUS_PENDING') ? ORDER_STATUS_PENDING : 'PENDING',
            defined('ORDER_STATUS_PREPARING') ? ORDER_STATUS_PREPARING : 'PREPARING',
            defined('ORDER_STATUS_READY') ? ORDER_STATUS_READY : 'READY'
        ];
    }
    
    /**
     * Get completed/inactive order statuses
     * @return array Inactive status codes
     */
    public function getInactiveOrderStatuses(): array {
        return [
            defined('ORDER_STATUS_SERVED') ? ORDER_STATUS_SERVED : 'SERVED',
            defined('ORDER_STATUS_CANCELLED') ? ORDER_STATUS_CANCELLED : 'CANCELLED'
        ];
    }
    
    /**
     * Get valid statuses for kitchen operations
     * @return array Valid status codes
     */
    public function getKitchenValidStatuses(): array {
        return [
            defined('ORDER_STATUS_PENDING') ? ORDER_STATUS_PENDING : 'PENDING',
            defined('ORDER_STATUS_PREPARING') ? ORDER_STATUS_PREPARING : 'PREPARING',
            defined('ORDER_STATUS_READY') ? ORDER_STATUS_READY : 'READY',
            defined('ORDER_STATUS_SERVED') ? ORDER_STATUS_SERVED : 'SERVED',
            defined('ORDER_STATUS_CANCELLED') ? ORDER_STATUS_CANCELLED : 'CANCELLED',
            defined('ORDER_STATUS_ISSUE') ? ORDER_STATUS_ISSUE : 'ISSUE'
        ];
    }
    
    /**
     * Get orders with their items (optimized to prevent N+1)
     * @param array $orderIds Array of order IDs
     * @return array Orders with items grouped
     */
    public function getOrdersWithItems(array $orderIds): array {
        return $this->repository->getOrdersWithItems($orderIds);
    }
    
    /**
     * Get orders by table ID
     * @param string $tableId Table ID
     * @return array Orders
     */
    public function getOrdersByTable(string $tableId): array {
        return $this->repository->getByTableId($tableId);
    }
    
    /**
     * Get active orders by table ID (excludes SERVED and CANCELLED)
     * @param string $tableId Table ID
     * @return array Active orders
     */
    public function getActiveOrdersByTable(string $tableId): array {
        return $this->repository->getActiveOrdersByTable($tableId);
    }
    
    /**
     * Get active orders by multiple table IDs (batch operation for performance)
     * @param array $tableIds Array of table IDs
     * @return array Orders grouped by table_id
     */
    public function getActiveOrdersByTableIds(array $tableIds): array {
        return $this->repository->getActiveOrdersByTableIds($tableIds);
    }
    
    /**
     * Get orders by date range
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Orders
     */
    public function getOrdersByDateRange(string $startDate, string $endDate): array {
        return $this->repository->getByDateRange($startDate, $endDate);
    }
    
    /**
     * Get orders by datetime range (for overnight working hours support)
     * @param string $startDatetime Start datetime (Y-m-d H:i:s)
     * @param string $endDatetime End datetime (Y-m-d H:i:s)
     * @return array Orders
     */
    public function getOrdersByDatetimeRange(string $startDatetime, string $endDatetime): array {
        return $this->repository->getByDatetimeRange($startDatetime, $endDatetime);
    }

   /**
 * Get hourly heatmap dataset for the last N days (anchored to a reference date).
 * Single SQL query covers the entire window so the heatmap cannot suffer
 * from per-day tenant-filter drift or N+1 query bursts. Callers bucket rows
 * into a days×24 grid in PHP.
 *
 * @param string $referenceDate Anchor day (Y-m-d) — typically the business 'today'.
 * @param int $days Number of days (default 7, includes $referenceDate).
 * @return array<int,array<string,mixed>>
 */

 public function getOrdersForHeatmap(string $referenceDate, int $days = 7): array {
 $days = max(1, $days);
 $startDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days', strtotime($referenceDate)));
 $endDate = $referenceDate;
 return $this->repository->getByDateRange($startDate, $endDate);
 }

 /**
 * Get daily revenue
     * @param string $date Date (Y-m-d)
     * @return float Revenue
     */
    public function getDailyRevenue(string $date): float {
        return $this->repository->getTotalAmountByDateRange($date, $date);
    }
    
    /**
     * Get daily revenue using datetime range (supports overnight working hours)
     * @param string $startDatetime Start datetime (Y-m-d H:i:s)
     * @param string $endDatetime End datetime (Y-m-d H:i:s)
     * @return float Revenue
     */
    public function getDailyRevenueByDatetimeRange(string $startDatetime, string $endDatetime): float {
        return $this->repository->getTotalAmountByDatetimeRange($startDatetime, $endDatetime);
    }
    
    public function getActualRevenueByDatetimeRange(string $startDatetime, string $endDatetime): float {
        return $this->repository->getActualRevenueByDatetimeRange($startDatetime, $endDatetime);
    }
    
    public function getEstimatedRevenueByDatetimeRange(string $startDatetime, string $endDatetime): float {
        return $this->repository->getEstimatedRevenueByDatetimeRange($startDatetime, $endDatetime);
    }
    
    /**
     * Get order by ID
     * @param string $orderId Order ID
     * @return array|null Order data or null
     */
    public function getOrderById(string $orderId): ?array {
        return $this->repository->findById($orderId);
    }
    
    /**
     * Get all orders
     * WARNING: This method loads ALL orders without limit - use with caution!
     * For dashboard/recent orders, use getRecentOrders() instead
     * @return array All orders
     */
    public function getAllOrders(): array {
        return $this->repository->findAll();
    }
    
    /**
     * Get recent orders with limit (performance optimized)
     * @param int $limit Number of recent orders to return
     * @return array Recent orders
     */
    public function getRecentOrders(int $limit = 10, bool $todayOnly = true, bool $excludeCancelled = true): array {
        return $this->repository->getRecent($limit, $todayOnly, $excludeCancelled);
    }

    /**
     * Get orders grouped by table sessions
     * @param string|null $tableId Optional table ID to filter by
     * @return array Orders grouped by table and sessions
     */
    public function getOrdersGroupedByTableSessions(?string $tableId = null): array {
        return $this->repository->getOrdersGroupedByTableSessions($tableId);
    }

    /**
     * Get table order sessions for a specific table
     * @param string $tableId Table ID
     * @return array|null Table sessions data or null
     */
    public function getTableOrderSessions(string $tableId): ?array {
        return $this->repository->getTableOrderSessions($tableId);
    }
    
    /**
     * Get top selling items
     * @param int $limit Limit
     * @return array Top selling items
     */
    public function getTopSellingItems(int $limit = 5, ?string $startDatetime = null, ?string $endDatetime = null): array {
        $tf = $this->tenantFilter('o');
        $tenantClause = $tf['where'] ? ' AND ' . $tf['where'] : '';
        $dateClause = '';
        $dateParams = [];
        if ($startDatetime && $endDatetime) {
            $dateClause = ' AND o.created_at >= :top_start AND o.created_at <= :top_end';
            $dateParams = ['top_start' => $startDatetime, 'top_end' => $endDatetime];
        }
        $sql = "SELECT mi.name, mi.menu_item_id, mi.image_url, SUM(oi.quantity) as count, SUM(oi.price * oi.quantity) as revenue
                FROM order_items oi
                JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.status != 'CANCELLED'
                    AND (o.is_paid = 1 OR o.status = 'SERVED')
                    {$tenantClause}
                    {$dateClause}
                GROUP BY mi.menu_item_id, mi.name, mi.image_url
                ORDER BY count DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        foreach ($tf['params'] as $k => $v) { $stmt->bindValue(':' . $k, $v); }
        foreach ($dateParams as $k => $v) { $stmt->bindValue(':' . $k, $v); }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    
    /**
     * Get orders for kitchen display
     * @return array Kitchen orders
     */
    public function getForKitchen(): array {
        $tf = $this->tenantFilter('o');
        $tenantClause = $tf['where'] ? ' AND ' . $tf['where'] : '';
        $sql = "SELECT DISTINCT o.*
                FROM orders o
                JOIN order_items oi ON o.order_id = oi.order_id
                JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id
                LEFT JOIN categories c ON mi.category_id = c.category_id
                WHERE o.status IN ('PENDING', 'PREPARING')
                AND (
                    mi.production_point = 'KITCHEN' 
                    OR (mi.production_point IS NULL AND c.requires_kitchen = 1)
                )
                {$tenantClause}
                ORDER BY o.created_at ASC";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($tf['params']);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            \App\Core\Logger::error('OrderService::getForKitchen failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Calculate total revenue for date range
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return float Total revenue
     */
    public function calculateTotalRevenue(string $startDate, string $endDate): float {
        return $this->repository->getTotalAmountByDateRange($startDate, $endDate);
    }
    
    /**
     * Get total sales by shift
     * @param string $shiftId Shift ID
     * @return float Total sales
     */
    public function getTotalSalesByShift(string $shiftId): float {
        $tf = $this->tenantFilter();
        $tenantClause = $tf['where'] ? ' AND ' . $tf['where'] : '';
        $sql = "SELECT SUM(total_amount) as total FROM {$this->repository->getTableName()}
                WHERE shift_id = :shift_id AND status != 'CANCELLED' AND is_paid = 1 {$tenantClause}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge(['shift_id' => $shiftId], $tf['params']));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (float)($result['total'] ?? 0);
    }
    
    /**
     * Update order total amount
     * @param string $orderId
     * @param float $totalAmount
     * @return bool
     */
    public function updateOrderTotal(string $orderId, float $totalAmount): bool {
        return $this->repository->update($orderId, ['total_amount' => $totalAmount]);
    }
    
    /**
     * Accept Y-m-d or Y-m-d H:i:s bounds without double-appending time suffixes.
     *
     * @return array{0:string,1:string}
     */
    private function normalizeDatetimeRangeBounds(string $start, string $end): array
    {
        $start = trim($start);
        $end = trim($end);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            $start .= ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $end .= ' 23:59:59';
        }
        return [$start, $end];
    }

    /**
     * Get revenue by category for date range
     * @param string $startDate Start date (Y-m-d or Y-m-d H:i:s)
     * @param string $endDate End date (Y-m-d or Y-m-d H:i:s)
     * @return array Revenue by category [['category_name' => string, 'revenue' => float], ...]
     */
    public function getRevenueByCategory(string $startDate, string $endDate): array {
        $translationService = DependencyFactory::getTranslationService();
        $noCategoryLabel = $translationService->translate('labels.no_category', null, []);
        $tf = $this->tenantFilter('o');
        $tenantClause = $tf['where'] ? ' AND ' . $tf['where'] : '';
        [$startBound, $endBound] = $this->normalizeDatetimeRangeBounds($startDate, $endDate);
        $sql = "SELECT 
                    COALESCE(c.name, :no_category) as category_name, 
                    COALESCE(SUM(oi.quantity), 0) as quantity,
                    COALESCE(SUM(oi.price * oi.quantity), 0) as revenue
                FROM order_items oi
                INNER JOIN orders o ON oi.order_id = o.order_id
                INNER JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id
                LEFT JOIN categories c ON mi.category_id = c.category_id
                WHERE o.created_at BETWEEN :start_date AND :end_date
                    AND o.status != 'CANCELLED'
                    AND (o.is_paid = 1 OR o.status = 'SERVED')
                    {$tenantClause}
                GROUP BY c.category_id, c.name
                ORDER BY revenue DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([
            'start_date' => $startBound,
            'end_date' => $endBound,
            'no_category' => $noCategoryLabel
        ], $tf['params']));
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Ensure revenue is float and handle null categories
        foreach ($results as &$result) {
            $result['revenue'] = floatval($result['revenue'] ?? 0);
            $result['quantity'] = intval($result['quantity'] ?? 0);
            $result['category_name'] = $result['category_name'] ?? $noCategoryLabel;
        }
        
        return $results;
    }
    
    /**
     * Get hourly sales for date range
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Hourly sales [['hour' => int, 'order_count' => int, 'revenue' => float], ...]
     */
    public function getHourlySales(string $startDate, string $endDate): array {
        $tf = $this->tenantFilter();
        $tenantClause = $tf['where'] ? ' AND ' . $tf['where'] : '';
        [$startBound, $endBound] = $this->normalizeDatetimeRangeBounds($startDate, $endDate);
        $sql = "SELECT 
                    HOUR(created_at) as hour, 
                    COUNT(*) as order_count, 
                    COALESCE(SUM(total_amount), 0) as revenue
                FROM orders
                WHERE created_at BETWEEN :start_date AND :end_date
                    AND status != 'CANCELLED'
                    AND (is_paid = 1 OR status = 'SERVED')
                    {$tenantClause}
                GROUP BY HOUR(created_at)
                ORDER BY hour ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([
            'start_date' => $startBound,
            'end_date' => $endBound
        ], $tf['params']));
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Ensure proper types
        foreach ($results as &$result) {
            $result['hour'] = intval($result['hour'] ?? 0);
            $result['order_count'] = intval($result['order_count'] ?? 0);
            $result['revenue'] = floatval($result['revenue'] ?? 0);
        }
        
        return $results;
    }
    
    /**
     * Get daily revenue series for a date range (grouped by day).
     *
     * Uses the SAME canonical revenue predicate as getHourlySales /
     * getTotalAmountByDatetimeRange — non-cancelled AND (paid OR served) — so
     * the daily chart, hourly chart and the headline total all agree. This is
     * the single source of truth for the analytics "Gelir Grafiği".
     *
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array<int,array{date:string,revenue:float,order_count:int}>
     */
    public function getDailyRevenueSeries(string $startDate, string $endDate): array {
        $tf = $this->tenantFilter();
        $tenantClause = $tf['where'] ? ' AND ' . $tf['where'] : '';
        $sql = "SELECT
                    DATE(created_at) as date,
                    COALESCE(SUM(total_amount), 0) as revenue,
                    COUNT(*) as order_count
                FROM orders
                WHERE created_at BETWEEN :start_date AND :end_date
                    AND status != 'CANCELLED'
                    AND (is_paid = 1 OR status = 'SERVED')
                    {$tenantClause}
                GROUP BY DATE(created_at)
                ORDER BY date ASC";

        [$startBound, $endBound] = $this->normalizeDatetimeRangeBounds($startDate, $endDate);
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([
            'start_date' => $startBound,
            'end_date' => $endBound
        ], $tf['params']));

        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($results as &$row) {
            $row['date'] = (string)($row['date'] ?? '');
            $row['revenue'] = floatval($row['revenue'] ?? 0);
            $row['order_count'] = intval($row['order_count'] ?? 0);
        }
        unset($row);

        return $results;
    }

    /**
     * Calculate average order value for date range
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return float Average order value
     */
    public function calculateAvgOrderValue(string $startDate, string $endDate): float {
        $tf = $this->tenantFilter();
        $tenantClause = $tf['where'] ? ' AND ' . $tf['where'] : '';
        [$startBound, $endBound] = $this->normalizeDatetimeRangeBounds($startDate, $endDate);
        $sql = "SELECT 
                    COALESCE(AVG(total_amount), 0) as avg_value
                FROM orders
                WHERE created_at BETWEEN :start_date AND :end_date
                    AND status != 'CANCELLED'
                    AND (is_paid = 1 OR status = 'SERVED')
                    {$tenantClause}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([
            'start_date' => $startBound,
            'end_date' => $endBound
        ], $tf['params']));
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return floatval($result['avg_value'] ?? 0);
    }
}

