<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\DependencyFactory;
use App\Core\Logger;

/**
 * Printer Bridge Controller v2.0 - FULL TENANT SUPPORT
 * Multi-tenant yapı için yazılmış, tam entegre printer bridge sistemi
 */
class PrinterBridgeController extends Controller {
    private $db;

    public function __construct() {
        parent::__construct();
        $this->db = DependencyFactory::getDatabase();
        Logger::info("PrinterBridgeController initialized");
    }
    
    /**
     * REGISTER: Config code ile bridge kaydı
     * İşletme ID'sini bulup bridge'i o işletmeye bağlar
     */
    public function register() {
        Logger::info("PrinterBridge register method called", [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown'
        ]);

        $data = $this->getJsonInput();
        $bridgeId = $data['bridge_id'] ?? null;
        $configCode = $data['config_code'] ?? null;
        $deviceName = $data['device_name'] ?? 'Unknown Device';
        $version = $data['version'] ?? '0.0.0';
        $os = $data['os'] ?? 'Unknown';

        // Debug logging
        Logger::info("PrinterBridge register called", [
            'bridge_id' => $bridgeId,
            'config_code_length' => strlen($configCode ?? ''),
            'device_name' => $deviceName
        ]);

        // Input validation - config_code is REQUIRED, bridge_id is optional
        if (!$configCode) {
            Logger::warning("Missing config_code", ['bridge_id' => $bridgeId]);
            $this->jsonResponse(['success' => false, 'error' => 'Config code required'], 400);
            return;
        }
        
        // Validate config code format (64 char hex)
        if (strlen($configCode) !== 64 || !ctype_xdigit($configCode)) {
            Logger::warning("Invalid config code format", ['length' => strlen($configCode)]);
            $this->jsonResponse(['success' => false, 'error' => 'Invalid config code format'], 400);
            return;
        }
        
        // Sanitize device name and version
        $deviceName = htmlspecialchars(substr($deviceName, 0, 200), ENT_QUOTES, 'UTF-8');
        $version = htmlspecialchars(substr($version, 0, 50), ENT_QUOTES, 'UTF-8');
        $os = htmlspecialchars(substr($os, 0, 100), ENT_QUOTES, 'UTF-8');
        
        try {
            // TEKİL SISTEM: Sadece printer_bridges.config_code kullanılır
            // Her köprü kendi config_code'una sahiptir
            $stmt = $this->db->prepare("
                SELECT bridge_id, tenant_id, api_key, bridge_name
                FROM printer_bridges
                WHERE config_code = ?
                LIMIT 1
            ");
            $stmt->execute([$configCode]);
            $bridge = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$bridge) {
                Logger::warning("Invalid config code attempted: " . substr($configCode, 0, 8));
                $this->jsonResponse(['success' => false, 'error' => 'Invalid configuration code'], 401);
                return;
            }
            
            // Bridge bulundu - bilgileri güncelle
            $businessId = $bridge['tenant_id'];
            $apiKey = $bridge['api_key'];
            
            // Eğer device_name boş ise mevcut bridge_name'i koru
            $stmt = $this->db->prepare("
                UPDATE printer_bridges 
                SET bridge_name = IF(? != '', ?, bridge_name),
                    version = ?,
                    os_info = ?,
                    status = 'ONLINE',
                    last_seen = NOW(),
                    last_heartbeat = NOW(),
                    updated_at = NOW()
                WHERE config_code = ?
            ");
            $stmt->execute([$deviceName, $deviceName, $version, $os, $configCode]);
            
            Logger::info("Bridge registered/updated", [
                'bridge_id' => $bridge['bridge_id'],
                'bridge_name' => $deviceName,
                'business_id' => $businessId
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'bridge_id' => $bridge['bridge_id'],
                'api_key' => $apiKey,
                'business_id' => $businessId
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Bridge Register Error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }
    
    /**
     * HEARTBEAT: Bridge aktif durumda
     * CRITICAL: API key validation to prevent unauthorized heartbeats
     */
    public function heartbeat() {
        $data = $this->getJsonInput();
        $bridgeId = $data['bridge_id'] ?? null;
        $apiKey = $data['api_key'] ?? null;
        
        if (!$bridgeId || !$apiKey) {
            $this->jsonResponse(['success' => false, 'error' => 'Missing bridge_id or api_key'], 400);
            return;
        }
        
        try {
            // CRITICAL: Validate API key before updating heartbeat
            $stmt = $this->db->prepare("
                UPDATE printer_bridges 
                SET last_heartbeat = NOW(), status = 'ONLINE', last_seen = NOW()
                WHERE bridge_id = ? AND api_key = ?
            ");
            $stmt->execute([$bridgeId, $apiKey]);
            
            $rowsAffected = $stmt->rowCount();
            
            if ($rowsAffected === 0) {
                $this->jsonResponse(['success' => false, 'error' => 'Invalid bridge or API key'], 401);
                return;
            }
            
            $this->jsonResponse(['success' => true]);
        } catch (\Exception $e) {
            Logger::error('Heartbeat Error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }
    
    /**
     * SYNC PRINTERS: Desktop'taki yazıcıları sunucuya gönder
     */
    public function syncPrinters() {
        $data = $this->getJsonInput();
        $bridgeId = $data['bridge_id'] ?? null;
        $apiKey = $data['api_key'] ?? null;
        $printers = $data['printers'] ?? [];
        $assignments = $data['assignments'] ?? []; // YENİ: Ekran atamaları
        
        if (!$bridgeId || !$apiKey) {
            $this->jsonResponse(['success' => false, 'error' => 'Missing bridge_id or api_key'], 400);
            return;
        }
        
        if (!is_array($printers)) {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid printers data'], 400);
            return;
        }
        
        if (count($printers) > 50) {
            $this->jsonResponse(['success' => false, 'error' => 'Too many printers (max 50)'], 400);
            return;
        }
        
        try {
            // CRITICAL: Validate API key before syncing printers
            $stmt = $this->db->prepare("
                SELECT tenant_id
                FROM printer_bridges
                WHERE bridge_id = ? AND api_key = ?
            ");
            $stmt->execute([$bridgeId, $apiKey]);
            $bridge = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$bridge) {
                $this->jsonResponse(['success' => false, 'error' => 'Invalid bridge or API key'], 401);
                return;
            }
            
            $businessId = $bridge['tenant_id'];
            $syncedCount = 0;
            
            $this->db->beginTransaction();
            
            try {
                $stmt = $this->db->prepare("
                    UPDATE printers 
                    SET status = 'OFFLINE' 
                    WHERE bridge_id = ? AND tenant_id = ?
                ");
                $stmt->execute([$bridgeId, $businessId]);
                
                // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert (single query per printer)
                $upsertStmt = $this->db->prepare("
                    INSERT INTO printers 
                    (printer_id, tenant_id, printer_name, connection_type, port, status, bridge_id, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 'ONLINE', ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        printer_name = VALUES(printer_name),
                        connection_type = VALUES(connection_type),
                        port = VALUES(port),
                        status = 'ONLINE',
                        bridge_id = VALUES(bridge_id),
                        updated_at = NOW()
                ");
                
                foreach ($printers as $p) {
                    if (!isset($p['id']) || !isset($p['name'])) {
                        continue;
                    }
                    
                    $printerId = $p['id'];
                    $printerName = htmlspecialchars(substr($p['name'], 0, 200), ENT_QUOTES, 'UTF-8');
                    
                    $upsertStmt->execute([
                        $printerId,
                        $businessId,
                        $p['name'],
                        $p['connection'] ?? 'usb',
                        $p['port'] ?? '',
                        $bridgeId
                    ]);
                    $syncedCount++;
                }
                
                // Ekran atamalarını kaydet
                $assignmentCount = 0;
                foreach ($assignments as $screenId => $printerName) {
                    try {
                        // printer_name'den printer_id bul
                        $pStmt = $this->db->prepare("
                            SELECT printer_id FROM printers 
                            WHERE printer_name = ? AND tenant_id = ?
                            LIMIT 1
                        ");
                        $pStmt->execute([$printerName, $businessId]);
                        $printerRow = $pStmt->fetch(\PDO::FETCH_ASSOC);
                        
                        if ($printerRow) {
                            $printerId = $printerRow['printer_id'];
                            
                            // Eski atamayı sil (aynı ekran için) - unique key (screen_id, printer_id, business_id) sorununu önler
                            $delStmt = $this->db->prepare("
                                DELETE FROM preparation_screen_printers 
                                WHERE screen_id = ? AND tenant_id = ?
                            ");
                            $delStmt->execute([$screenId, $businessId]);
                            
                            // Yeni atama ekle
                            $insStmt = $this->db->prepare("
                                INSERT INTO preparation_screen_printers 
                                (screen_id, printer_id, tenant_id, priority, is_active, created_at, updated_at) 
                                VALUES (?, ?, ?, 1, 1, NOW(), NOW())
                            ");
                            $insStmt->execute([$screenId, $printerId, $businessId]);
                            $assignmentCount++;
                        }
                    } catch (\Exception $e) {
                        Logger::warning("Assignment save error: " . $e->getMessage(), [
                            'screen_id' => $screenId,
                            'printer' => $printerName
                        ]);
                    }
                }
                
                $this->db->commit();
                Logger::info("Synced $syncedCount printers and $assignmentCount assignments for business: $businessId");
                
                $this->jsonResponse([
                    'success' => true, 
                    'data' => ['synced_count' => $syncedCount]
                ]);
                
            } catch (\Exception $e) {
                $this->db->rollBack();
                Logger::error('Sync Printers Error: ' . $e->getMessage());
                $this->jsonResponse(['success' => false, 'error' => 'Database error'], 500);
            }
            
        } catch (\Exception $e) {
            Logger::error('Sync Printers Error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }
    
    /**
     * GET QUEUE: Yazdirma islerini cek - ORDER DATA ILE
     * Enhanced: Returns order details (table, waiter, items) for receipt generation
     */
    /**
     * GET QUEUE: Yazdırma kuyruğundaki bekleyen işleri döndür
     * Masaüstü uygulaması bu endpoint'i periyodik olarak çağırır
     * Yazıcı seçimi masaüstü uygulamasında yapılır (screen_id -> printer_name mapping)
     */
    public function getQueue() {
        $data = $this->getJsonInput();
        $bridgeId = $data['bridge_id'] ?? null;
        $apiKey = $data['api_key'] ?? null;
        $limit = (int)($data['limit'] ?? 50);
        
        $limit = max(1, min(200, $limit));
        
        if (!$bridgeId || !$apiKey) {
            $this->jsonResponse(['success' => false, 'error' => 'Missing bridge_id or api_key'], 400);
            return;
        }
        
        try {
            // Bridge doğrula
            $stmt = $this->db->prepare("
                SELECT tenant_id
                FROM printer_bridges
                WHERE bridge_id = ? AND api_key = ?
            ");
            $stmt->execute([$bridgeId, $apiKey]);
            $bridge = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$bridge) {
                $this->jsonResponse(['success' => false, 'error' => 'Invalid bridge or API key'], 401);
                return;
            }
            
            $businessId = $bridge['tenant_id'];

            // Update bridge heartbeat on queue polling (keeps status accurate)
            try {
                $hbStmt = $this->db->prepare("
                    UPDATE printer_bridges 
                    SET last_seen = NOW(), last_heartbeat = NOW(), status = 'ONLINE'
                    WHERE bridge_id = ?
                ");
                $hbStmt->execute([$bridgeId]);
            } catch (\Exception $e) {
                Logger::warning('PrinterBridge getQueue: Failed to update heartbeat', [
                    'bridge_id' => $bridgeId,
                    'error' => $e->getMessage()
                ]);
            }

            // AGGRESSIVE CLEANUP: Cancel queue items whose orders are cancelled/served/refunded
            // This is the PRIMARY defense against printing cancelled orders
            try {
                $cancelStmt = $this->db->prepare("
                    UPDATE receipt_print_queue q
                    INNER JOIN (
                        SELECT DISTINCT rpq.queue_id 
                        FROM receipt_print_queue rpq
                        LEFT JOIN receipts r ON rpq.receipt_id = r.receipt_id
                        LEFT JOIN orders o ON r.order_id = o.order_id
                        WHERE rpq.tenant_id = ?
                          AND rpq.status IN ('PENDING', 'PRINTING')
                          AND o.order_id IS NOT NULL
                          AND o.status IN ('CANCELLED', 'SERVED', 'REFUNDED')
                    ) cancelled ON q.queue_id = cancelled.queue_id
                    SET q.status = 'EXPIRED',
                        q.error_message = CONCAT(COALESCE(q.error_message, ''), ' | Auto-cancelled: order status changed')
                ");
                $cancelStmt->execute([$businessId]);
                $cancelledCount = $cancelStmt->rowCount();
                if ($cancelledCount > 0) {
                    Logger::info("PrinterBridge getQueue: Cancelled $cancelledCount jobs for cancelled/served orders", [
                        'business_id' => $businessId
                    ]);
                }
            } catch (\Exception $e) {
                Logger::warning('PrinterBridge getQueue: Failed to cancel jobs for cancelled orders', [
                    'error' => $e->getMessage()
                ]);
            }
            
            // Also cancel preparation queue items whose order_id (stored in print_data) belongs to cancelled orders
            try {
                $cancelPrepStmt = $this->db->prepare("
                    UPDATE receipt_print_queue q
                    INNER JOIN orders o ON JSON_UNQUOTE(JSON_EXTRACT(q.print_data, '$.order_id')) = o.order_id
                    SET q.status = 'EXPIRED',
                        q.error_message = CONCAT(COALESCE(q.error_message, ''), ' | Auto-cancelled: order cancelled')
                    WHERE q.tenant_id = ?
                      AND q.status IN ('PENDING', 'PRINTING')
                      AND o.status IN ('CANCELLED', 'REFUNDED')
                ");
                $cancelPrepStmt->execute([$businessId]);
                $cancelledPrepCount = $cancelPrepStmt->rowCount();
                if ($cancelledPrepCount > 0) {
                    Logger::info("PrinterBridge getQueue: Cancelled $cancelledPrepCount prep jobs for cancelled orders", [
                        'business_id' => $businessId
                    ]);
                }
            } catch (\Exception $e) {
                // Non-critical - the receipt_id join above should catch most cases
            }

            // Requeue stale PRINTING jobs (bridge crashed or lost connection)
            // TIGHTENED: Only requeue items within 15 minutes (matching the fetch window)
            // and max 2 retries to prevent infinite reprint loops
            try {
                $staleStmt = $this->db->prepare("
                    UPDATE receipt_print_queue
                    SET status = 'PENDING',
                        processing_bridge_id = NULL,
                        processing_started_at = NULL,
                        retry_count = COALESCE(retry_count, 0) + 1,
                        error_message = CONCAT(COALESCE(error_message, ''), ' | Requeued at ', NOW())
                    WHERE tenant_id = ?
                      AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                      AND COALESCE(retry_count, 0) < 2
                      AND status = 'PRINTING' 
                      AND processing_started_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ");
                $staleStmt->execute([$businessId]);
                $requeuedCount = $staleStmt->rowCount();
                if ($requeuedCount > 0) {
                    Logger::info("PrinterBridge getQueue: Requeued $requeuedCount stale PRINTING jobs", [
                        'business_id' => $businessId
                    ]);
                }
            } catch (\Exception $e) {
                Logger::warning('PrinterBridge getQueue: Failed to requeue stale jobs', [
                    'business_id' => $businessId,
                    'error' => $e->getMessage()
                ]);
            }
            
            // REMOVED: FAILED job auto-retry. Failed jobs should NOT be automatically retried.
            // If a print fails, the user can manually trigger a reprint. Automatic retries
            // were causing duplicate prints when the original actually succeeded but status update failed.
            
            // Expire old PENDING/FAILED items aggressively (30 minutes instead of 2 hours)
            try {
                $expireStmt = $this->db->prepare("
                    UPDATE receipt_print_queue
                    SET status = 'EXPIRED'
                    WHERE tenant_id = ?
                      AND status IN ('PENDING', 'FAILED')
                      AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                ");
                $expireStmt->execute([$businessId]);
            } catch (\Exception $e) {
                // Non-critical, just log
            }
            
            $this->db->beginTransaction();
            
            try {
                // SADECE kuyruk ve ekran bilgisi - yazıcı JOIN'i YOK
                // Yazıcı seçimi masaüstü uygulamasında yapılır
                // Sadece anlık fişler (son 15 dk) - eski fişler yazdırılmaz
                $stmt = $this->db->prepare("
                    SELECT 
                        q.queue_id,
                        q.screen_id,
                        q.print_data,
                        q.created_at,
                        ps.name as screen_name,
                        ps.screen_type
                    FROM receipt_print_queue q
                    LEFT JOIN preparation_screens ps ON q.screen_id = ps.screen_id
                    WHERE q.tenant_id = ? 
                    AND q.status = 'PENDING'
                    AND q.created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                    ORDER BY q.created_at ASC
                    LIMIT ?
                    FOR UPDATE SKIP LOCKED
                ");
                $stmt->execute([$businessId, $limit]);
                $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                if (empty($jobs)) {
                    // No pending jobs - commit and return immediately
                    $this->db->commit();
                    $this->jsonResponse([
                        'success' => true,
                        'jobs' => [],
                        'count' => 0
                    ]);
                    return;
                }
                
                // İşleri formatla - tüm fiş verilerini desktop uygulamasına gönder
                foreach ($jobs as &$job) {
                    $printData = json_decode($job['print_data'] ?? '{}', true);
                    if (!is_array($printData)) {
                        $printData = [];
                    }
                    
                    // ESC/POS fiş içeriği (eski format uyumluluğu)
                    $job['content'] = $printData['content'] ?? '';
                    
                    // Fiş tipi: ADISYON, FULL, preparation
                    $job['receipt_type'] = $printData['receipt_type'] ?? $printData['type'] ?? '';
                    
                    // Sipariş bilgileri
                    $job['order_id'] = $printData['order_id'] ?? $job['queue_id'];
                    $job['table_name'] = $printData['table'] ?? $printData['table_name'] ?? '';
                    $job['zone_name'] = $printData['zone_name'] ?? '';
                    $job['table_display'] = $printData['table_display'] ?? '';
                    if ($job['table_display'] === '') {
                        $job['table_display'] = $job['zone_name'] !== ''
                            ? trim($job['zone_name'] . ' - ' . $job['table_name'])
                            : $job['table_name'];
                    }
                    $job['waiter_name'] = $printData['waiter'] ?? $printData['waiter_name'] ?? $printData['staff_name'] ?? '';
                    $job['customer_note'] = $printData['customer_note'] ?? '';
                    $job['receipt_number'] = $printData['receipt_number'] 
                        ?? ($printData['receipt_data']['receipt']['receipt_number'] ?? '');
                    
                    // === RECEIPT DATA (Adisyon/Kasiye fişleri için tam veri) ===
                    // receipt_data nested yapı: business, receipt, order, items, totals
                    if (!empty($printData['receipt_data']) && is_array($printData['receipt_data'])) {
                        $job['receipt_data'] = $printData['receipt_data'];
                    }
                    
                    // === ITEMS (Hazırlık fişleri için ürün listesi) ===
                    if (!empty($printData['items']) && is_array($printData['items'])) {
                        $job['items'] = $printData['items'];
                    } elseif (!empty($printData['receipt_data']['items']) && is_array($printData['receipt_data']['items'])) {
                        $job['items'] = $printData['receipt_data']['items'];
                    }
                    
                    // === REPORT DATA (product_sales_report, z_report vb.) ===
                    $reportFields = ['products', 'categories', 'summary', 'report_no', 
                                    'date_label', 'report_time', 'tax_number'];
                    foreach ($reportFields as $rf) {
                        if (isset($printData[$rf])) {
                            $job[$rf] = $printData[$rf];
                        }
                    }
                    
                    // === CUSTOMIZATIONS (Hazırlık fişleri - ürün özelleştirmeleri) ===
                    if (!empty($printData['customizations']) && is_array($printData['customizations'])) {
                        $job['customizations'] = $printData['customizations'];
                    }
                    
                    // === Z RAPORU: Tüm Z rapor alanlarını doğrudan aktar ===
                    $jobReceiptType = $printData['receipt_type'] ?? '';
                    if (strtolower($jobReceiptType) === 'z_report') {
                        $zReportFields = ['z_number', 'date', 'report_time', 'totals', 
                                         'payment_breakdown', 'discount_total', 'service_charge_total',
                                         'tip_total', 'order_lines', 'product_breakdown', 'category_breakdown',
                                         'business_name', 'tax_number', 'address', 'phone', 'receipt_type_override'];
                        foreach ($zReportFields as $field) {
                            if (isset($printData[$field])) {
                                $job[$field] = $printData[$field];
                            }
                        }
                    }
                    
                    // İşletme bilgileri (fiş başlığı: ad, adres, telefon)
                    // receipt_data içindeki business öncelikli
                    if (!empty($printData['receipt_data']['business'])) {
                        $job['business'] = $printData['receipt_data']['business'];
                    } elseif (!empty($printData['business'])) {
                        $job['business'] = $printData['business'];
                    } else {
                        $job['business'] = [];
                    }
                    
                    // Ekran bilgisi (fallback - sistem ekranları için)
                    if (empty($job['screen_name'])) {
                        // Önce print_data'dan, yoksa sistem ekranı adları
                        $systemScreenNames = [
                            'kitchen_main' => 'Mutfak',
                            'waiter_main' => 'Garson',
                            'cashier_main' => 'Kasiyer',
                        ];
                        $job['screen_name'] = $printData['screen_name'] 
                            ?? $systemScreenNames[$job['screen_id'] ?? ''] 
                            ?? $job['screen_id'] 
                            ?? 'Unknown';
                    }
                    if (empty($job['screen_type'])) {
                        $systemScreenTypes = [
                            'kitchen_main' => 'KITCHEN',
                            'waiter_main' => 'WAITER',
                            'cashier_main' => 'CASHIER',
                        ];
                        $job['screen_type'] = $printData['screen_type'] 
                            ?? $systemScreenTypes[$job['screen_id'] ?? ''] 
                            ?? 'KITCHEN';
                    }
                    
                    // print_data artık gerekli değil (parse edildi)
                    unset($job['print_data']);
                }
                unset($job);
                
                // Yanıtta işletme bilgisi (masaüstü uygulama fiş için - tek seferlik)
                $businessInfo = null;
                if (!empty($jobs)) {
                    $firstJob = $jobs[0];
                    if (!empty($firstJob['business']) && !empty($firstJob['business']['name'])) {
                        $businessInfo = $firstJob['business'];
                    } else {
                        $businessInfo = $this->getBusinessInfoForBridge($businessId);
                    }
                }
                
                // İşleri PRINTING olarak işaretle
                if ($jobs) {
                    $ids = array_column($jobs, 'queue_id');
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $updateStmt = $this->db->prepare("
                        UPDATE receipt_print_queue 
                        SET status = 'PRINTING', 
                            processing_bridge_id = ?,
                            processing_started_at = NOW()
                        WHERE queue_id IN ($placeholders)
                    ");
                    $params = array_merge([$bridgeId], $ids);
                    $updateStmt->execute($params);
                }
                
                $this->db->commit();
                
                $response = [
                    'success' => true,
                    'jobs' => $jobs,
                    'count' => count($jobs)
                ];
                if ($businessInfo !== null) {
                    $response['business'] = $businessInfo;
                }
                $this->jsonResponse($response);
                
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            Logger::error('Get Queue Error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }
    
    /**
     * İşletme bilgisi (ad, adres, telefon) - masaüstü fiş başlığı için
     * printer_bridges.tenant_id = customers.customer_id
     * Not: customers tablosunda sadece company_name ve phone kesin var (migration); address varsa * ile dönen getById'den gelir
     */
    private function getBusinessInfoForBridge(string $businessId): array {
        try {
            $stmt = $this->db->prepare("
                SELECT company_name, phone
                FROM customers
                WHERE customer_id = ?
                LIMIT 1
            ");
            $stmt->execute([$businessId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return ['name' => '', 'address' => '', 'phone' => ''];
            }
            return [
                'name' => trim($row['company_name'] ?? ''),
                'address' => '', // customers tablosunda address sütunu migration'larda yok; ileride eklenirse buraya eklenebilir
                'phone' => trim($row['phone'] ?? ''),
            ];
        } catch (\Exception $e) {
            return ['name' => '', 'address' => '', 'phone' => ''];
        }
    }
    
    public function updateStatus() {
        $data = $this->getJsonInput();
        $bridgeId = $data['bridge_id'] ?? null;
        $apiKey = $data['api_key'] ?? null;
        $queueId = $data['queue_id'] ?? null;
        $status = $data['status'] ?? null;
        $errorMsg = $data['error_message'] ?? null;
        
        if (!$bridgeId || !$apiKey || !$queueId || !$status) {
            $this->jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
            return;
        }
        
        // Validate status whitelist
        $allowedStatuses = ['PENDING', 'PRINTING', 'PRINTED', 'FAILED'];
        $dbStatus = strtoupper($status);
        
        // Map common variations
        if (in_array($status, ['printed', 'success'])) {
            $dbStatus = 'PRINTED';
        } elseif ($status == 'failed') {
            $dbStatus = 'FAILED';
        } elseif (in_array($status, ['printing', 'processing'])) {
            $dbStatus = 'PRINTING';
        }
        
        if (!in_array($dbStatus, $allowedStatuses)) {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid status'], 400);
            return;
        }
        
        try {
            // CRITICAL: Verify bridge owns this job
            $stmt = $this->db->prepare("
                SELECT tenant_id
                FROM printer_bridges
                WHERE bridge_id = ? AND api_key = ?
            ");
            $stmt->execute([$bridgeId, $apiKey]);
            $bridge = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$bridge) {
                $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
                return;
            }
            
            $businessId = $bridge['tenant_id'];
            
            // For PRINTED: relax ownership check - stale recovery may have cleared processing_bridge_id
            // but the job was already printed. Business_id check is sufficient for security.
            // For other statuses (FAILED etc): keep strict ownership to prevent cross-bridge interference.
            if ($dbStatus === 'PRINTED') {
                $stmt = $this->db->prepare("
                    UPDATE receipt_print_queue 
                    SET status = ?, error_message = ?, printed_at = NOW(),
                        processing_bridge_id = ?
                    WHERE queue_id = ?
                    AND tenant_id = ?
                    AND status IN ('PRINTING', 'PENDING')
                ");
                $stmt->execute([$dbStatus, $errorMsg, $bridgeId, $queueId, $businessId]);
            } else {
                $stmt = $this->db->prepare("
                    UPDATE receipt_print_queue 
                    SET status = ?, error_message = ?
                    WHERE queue_id = ?
                    AND tenant_id = ?
                    AND (processing_bridge_id = ? OR processing_bridge_id IS NULL)
                ");
                $stmt->execute([$dbStatus, $errorMsg, $queueId, $businessId, $bridgeId]);
            }
            
            $rowsAffected = $stmt->rowCount();
            
            if ($rowsAffected === 0) {
                Logger::warning('Status update failed - job not found or already terminal', [
                    'queue_id' => $queueId,
                    'bridge_id' => $bridgeId,
                    'business_id' => $businessId,
                    'target_status' => $dbStatus
                ]);
                $this->jsonResponse(['success' => false, 'error' => 'Job not found or already in terminal state'], 404);
                return;
            }
            
            Logger::info("Job status updated", [
                'queue_id' => $queueId,
                'status' => $dbStatus
            ]);
            
            $this->jsonResponse(['success' => true]);
        } catch (\Exception $e) {
            Logger::error('Update Status Error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }
    
    /**
     * GET PRINTER ROLES: Yazıcı-ekran atamalarını çek
     */
    public function getPrinterRoles() {
        $bridgeId = $_GET['bridge_id'] ?? null;
        $apiKey = $_GET['api_key'] ?? null;
        
        if (!$bridgeId) {
            $this->jsonResponse(['success' => false, 'error' => 'Missing bridge_id'], 400);
            return;
        }
        
        try {
            // CRITICAL: printer_bridges.business_id stores customer_id directly
            // This is consistent with other methods in this controller
            $stmt = $this->db->prepare("
                SELECT tenant_id
                FROM printer_bridges
                WHERE bridge_id = ?
            ");
            $stmt->execute([$bridgeId]);
            $bridge = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$bridge) {
                $this->jsonResponse(['success' => false, 'error' => 'Bridge not found'], 404);
                return;
            }
            
            // Use business_id directly (already stores customer_id)
            $businessId = $bridge['tenant_id'];
            
            // Yazıcı-ekran atamalarını çek
            $stmt = $this->db->prepare("
                SELECT 
                    ps.slug as role,
                    ps.name as role_name,
                    p.printer_id
                FROM preparation_screen_printers psp
                JOIN preparation_screens ps ON psp.screen_id = ps.screen_id
                JOIN printers p ON psp.printer_id = p.printer_id
                WHERE psp.tenant_id = ? AND ps.tenant_id = ? AND psp.is_active = 1
            ");
            $stmt->execute([$businessId, $businessId]);
            $assignments = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $roles = [];
            foreach ($assignments as $a) {
                $roleSlug = $a['role'] ?? 'unknown';
                if (!isset($roles[$roleSlug])) {
                    $roles[$roleSlug] = [];
                }
                $roles[$roleSlug][] = $a['printer_id'];
            }
            
            $this->jsonResponse(['success' => true, 'roles' => $roles]);
            
        } catch (\Exception $e) {
            Logger::error('Get Roles Error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }
    
    /**
     * GET SCREENS: İşletmenin hazırlık ekranlarını listele
     */
    public function getScreens() {
        $bridgeId = $_GET['bridge_id'] ?? null;
        $apiKey = $_GET['api_key'] ?? null;
        
        if (!$bridgeId) {
            $this->jsonResponse(['success' => false, 'error' => 'Missing bridge_id'], 400);
            return;
        }
        
        try {
            // CRITICAL: printer_bridges.business_id stores customer_id directly
            $stmt = $this->db->prepare("
                SELECT tenant_id, bridge_name, config_code
                FROM printer_bridges
                WHERE bridge_id = ?
            ");
            $stmt->execute([$bridgeId]);
            $bridge = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$bridge) {
                // Try to find by api_key as fallback
                if ($apiKey) {
                    $stmt = $this->db->prepare("SELECT bridge_id, tenant_id FROM printer_bridges WHERE api_key = ?");
                    $stmt->execute([$apiKey]);
                    $bridge = $stmt->fetch(\PDO::FETCH_ASSOC);
                    
                    if ($bridge) {
                        $bridgeId = $bridge['bridge_id']; // Use correct bridge_id
                        Logger::debug("Found bridge by api_key", ['bridge_id' => $bridgeId]);
                    }
                }
                
                if (!$bridge) {
                    $this->jsonResponse(['success' => false, 'error' => 'Bridge not found'], 404);
                    return;
                }
            }
            
            $businessId = $bridge['tenant_id'];
            
            $allScreens = [];
            
            // 1. HAZIRLIK EKRANLARI (preparation_screens tablosu)
            $stmt = $this->db->prepare("
                SELECT 
                    screen_id,
                    name,
                    slug,
                    description,
                    production_point,
                    screen_type,
                    is_active,
                    display_order,
                    'preparation' as source_type
                FROM preparation_screens 
                WHERE tenant_id = ?
                ORDER BY display_order, name
            ");
            $stmt->execute([$businessId]);
            $prepScreens = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $allScreens = array_merge($allScreens, $prepScreens);
            
            // 2. MUTFAK EKRANI (Kitchen) - Sabit sistem ekranı
            $allScreens[] = [
                'screen_id' => 'kitchen_main',
                'name' => 'Mutfak',
                'slug' => 'kitchen',
                'description' => 'Ana mutfak ekranı',
                'production_point' => null,
                'screen_type' => 'kitchen',
                'is_active' => 1,
                'display_order' => 1000,
                'source_type' => 'system'
            ];
            
            // 3. GARSON EKRANI (Waiter) - Sabit sistem ekranı
            $allScreens[] = [
                'screen_id' => 'waiter_main',
                'name' => 'Garson',
                'slug' => 'waiter',
                'description' => 'Garson ekranı',
                'production_point' => null,
                'screen_type' => 'waiter',
                'is_active' => 1,
                'display_order' => 1001,
                'source_type' => 'system'
            ];
            
            // 4. KASİYER EKRANI (Cashier) - Sabit sistem ekranı
            $allScreens[] = [
                'screen_id' => 'cashier_main',
                'name' => 'Kasiyer',
                'slug' => 'cashier',
                'description' => 'Kasa ekranı',
                'production_point' => null,
                'screen_type' => 'cashier',
                'is_active' => 1,
                'display_order' => 1002,
                'source_type' => 'system'
            ];
            
            Logger::debug("Screens fetched", [
                'business_id' => $businessId,
                'total_screens' => count($allScreens),
                'prep_screens' => count($prepScreens)
            ]);
            
            $this->jsonResponse(['success' => true, 'screens' => $allScreens]);
            
        } catch (\Exception $e) {
            Logger::error('Get Screens Error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }
    
    /**
     * ASSIGN PRINTER: Ekran-yazıcı ataması kaydet
     * CRITICAL: Screen ownership validation to prevent cross-tenant assignments
     */
    public function assignPrinter() {
        $data = $this->getJsonInput();
        $bridgeId = $data['bridge_id'] ?? null;
        $apiKey = $data['api_key'] ?? null;
        $screenId = $data['screen_id'] ?? null;
        $printerName = $data['printer_name'] ?? null;
        
        if (!$bridgeId || !$apiKey || !$screenId || !$printerName) {
            $this->jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
            return;
        }
        
        try {
            // Bridge ve API key doğrula
            $stmt = $this->db->prepare("
                SELECT tenant_id
                FROM printer_bridges
                WHERE bridge_id = ? AND api_key = ?
            ");
            $stmt->execute([$bridgeId, $apiKey]);
            $bridge = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$bridge) {
                $this->jsonResponse(['success' => false, 'error' => 'Invalid bridge or API key'], 401);
                return;
            }
            
            $businessId = $bridge['tenant_id'];
            
            // CRITICAL: Verify screen belongs to this business
            $stmt = $this->db->prepare("
                SELECT screen_id FROM preparation_screens
                WHERE screen_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$screenId, $businessId]);
            $screen = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$screen) {
                Logger::warning('Screen assignment failed - ownership check', [
                    'screen_id' => $screenId,
                    'bridge_id' => $bridgeId,
                    'business_id' => $businessId
                ]);
                $this->jsonResponse(['success' => false, 'error' => 'Screen not found or not owned by this business'], 404);
                return;
            }
            
            // Yazıcıyı bul veya oluştur
            $stmt = $this->db->prepare("
                SELECT printer_id FROM printers 
                WHERE tenant_id = ? AND printer_name = ?
                LIMIT 1
            ");
            $stmt->execute([$businessId, $printerName]);
            $printer = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$printer) {
                // Yazıcı yoksa oluştur
                $printerId = 'PRINTER_' . uniqid();
                $stmt = $this->db->prepare("
                    INSERT INTO printers 
                    (printer_id, tenant_id, bridge_id, printer_name, connection_type, is_active, printer_type, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, 'USB', 1, 'PREPARATION', 'ONLINE', NOW(), NOW())
                ");
                $stmt->execute([$printerId, $businessId, $bridgeId, $printerName]);
            } else {
                $printerId = $printer['printer_id'];
            }
            
            // Eski atamaları sil (sadece bu business için)
            $stmt = $this->db->prepare("
                DELETE FROM preparation_screen_printers 
                WHERE screen_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$screenId, $businessId]);
            
            // Yeni atama ekle
            $stmt = $this->db->prepare("
                INSERT INTO preparation_screen_printers 
                (screen_id, printer_id, tenant_id, priority, is_active, created_at, updated_at)
                VALUES (?, ?, ?, 1, 1, NOW(), NOW())
            ");
            $stmt->execute([$screenId, $printerId, $businessId]);
            
            Logger::info("Printer assigned", [
                'screen_id' => $screenId,
                'printer_name' => $printerName,
                'business_id' => $businessId
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Printer assigned successfully'
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Assign Printer Error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }

    /**
     * GENERATE TOKEN: Yeni bir bağlantı token'ı oluştur
     * GET /api/printer-bridge/generate-token
     */
    public function generateToken() {
        // Bu endpoint session-based auth gerektirir (web panelden çağrılır)
        $businessId = \App\Core\TenantResolver::resolve();
        
        if (!$businessId) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            return;
        }
        
        try {
            // Yeni bir tek kullanımlık token oluştur
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Token'ı geçici tabloya kaydet veya cache'le
            $stmt = $this->db->prepare("
                INSERT INTO printer_bridge_tokens 
                (token, tenant_id, expires_at, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)
            ");
            $stmt->execute([$token, $businessId, $expiresAt]);
            
            $this->jsonResponse([
                'success' => true,
                'token' => $token,
                'expires_at' => $expiresAt
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Generate Token Error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }
    
    /**
     * GET CONFIG BY TOKEN: Token ile config bilgilerini al
     * GET /api/printer-bridge/config/{token}
     */
    public function getConfigByToken($token = null) {
        $token = $token ?? $_GET['token'] ?? null;
        
        if (!$token) {
            $this->jsonResponse(['success' => false, 'error' => 'Token required'], 400);
            return;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT tenant_id, expires_at
                FROM printer_bridge_tokens
                WHERE token = ? AND expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $tokenData = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$tokenData) {
                $this->jsonResponse(['success' => false, 'error' => 'Invalid or expired token'], 401);
                return;
            }
            
            $businessId = $tokenData['tenant_id'];
            
            // İşletme bilgilerini al
            $stmt = $this->db->prepare("
                SELECT customer_id, name, slug
                FROM customers
                WHERE customer_id = ?
            ");
            $stmt->execute([$businessId]);
            $business = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $this->jsonResponse([
                'success' => true,
                'business_id' => $businessId,
                'business_name' => $business['name'] ?? 'Unknown',
                'api_url' => rtrim(BASE_URL, '/') . '/pb'
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Get Config By Token Error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }
    
    /**
     * LIST: İşletmenin tüm bridge'lerini listele
     * GET /api/printer-bridge/list
     */
    public function list() {
        $businessId = \App\Core\TenantResolver::resolve();
        
        // GET parametresi ile de alınabilir (API key ile)
        $apiKey = $_GET['api_key'] ?? null;
        $bridgeId = $_GET['bridge_id'] ?? null;
        
        if (!$businessId && $bridgeId && $apiKey) {
            // API key ile business_id bul
            try {
                $stmt = $this->db->prepare("
                    SELECT tenant_id FROM printer_bridges
                    WHERE bridge_id = ? AND api_key = ?
                ");
                $stmt->execute([$bridgeId, $apiKey]);
                $bridge = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($bridge) {
                    $businessId = $bridge['tenant_id'];
                }
            } catch (\Exception $e) {
                Logger::error('List bridges auth error: ' . $e->getMessage());
            }
        }
        
        if (!$businessId) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            return;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    bridge_id,
                    bridge_name,
                    status,
                    last_seen,
                    last_heartbeat,
                    version,
                    os_info,
                    created_at
                FROM printer_bridges
                WHERE tenant_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$businessId]);
            $bridges = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->jsonResponse([
                'success' => true,
                'bridges' => $bridges
            ]);
            
        } catch (\Exception $e) {
            Logger::error('List Bridges Error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }
    
    /**
     * GET PRINTERS: İşletmenin yazıcılarını listele
     * GET /api/printer-bridge/printers
     */
    public function getPrinters() {
        $bridgeId = $_GET['bridge_id'] ?? null;
        $apiKey = $_GET['api_key'] ?? null;
        
        if (!$bridgeId) {
            $this->jsonResponse(['success' => false, 'error' => 'Missing bridge_id'], 400);
            return;
        }
        
        try {
            // Bridge'i doğrula
            $stmt = $this->db->prepare("
                SELECT tenant_id
                FROM printer_bridges
                WHERE bridge_id = ?
            ");
            $stmt->execute([$bridgeId]);
            $bridge = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$bridge) {
                $this->jsonResponse(['success' => false, 'error' => 'Bridge not found'], 404);
                return;
            }
            
            $businessId = $bridge['tenant_id'];
            
            // Bu bridge'e bağlı yazıcıları getir
            $stmt = $this->db->prepare("
                SELECT 
                    p.printer_id,
                    p.printer_name,
                    p.connection_type,
                    p.port,
                    p.status,
                    p.is_active,
                    p.created_at,
                    p.updated_at
                FROM printers p
                WHERE p.tenant_id = ? AND p.bridge_id = ?
                ORDER BY p.printer_name
            ");
            $stmt->execute([$businessId, $bridgeId]);
            $printers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Her yazıcı için ekran atamalarını da ekle
            foreach ($printers as &$printer) {
                $stmt = $this->db->prepare("
                    SELECT ps.screen_id, ps.name as screen_name, ps.screen_type
                    FROM preparation_screen_printers psp
                    JOIN preparation_screens ps ON psp.screen_id = ps.screen_id
                    WHERE psp.printer_id = ? AND psp.tenant_id = ?
                ");
                $stmt->execute([$printer['printer_id'], $businessId]);
                $printer['assigned_screens'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
            unset($printer);
            
            $this->jsonResponse([
                'success' => true,
                'printers' => $printers
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Get Printers Error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }
    
    /**
     * GET DETECTED PRINTERS: Bridge'in tespit ettiği yazıcıları getir
     * GET /api/printer-bridge/detected-printers
     */
    public function getDetectedPrinters() {
        $bridgeId = $_GET['bridge_id'] ?? null;
        $apiKey = $_GET['api_key'] ?? null;
        
        if (!$bridgeId) {
            $this->jsonResponse(['success' => false, 'error' => 'Missing bridge_id'], 400);
            return;
        }
        
        try {
            // Bridge'i doğrula
            $stmt = $this->db->prepare("
                SELECT tenant_id
                FROM printer_bridges
                WHERE bridge_id = ?
            ");
            $stmt->execute([$bridgeId]);
            $bridge = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$bridge) {
                $this->jsonResponse(['success' => false, 'error' => 'Bridge not found'], 404);
                return;
            }
            
            $businessId = $bridge['tenant_id'];
            
            // Bu bridge'in tespit ettiği yazıcıları getir (ONLINE olanlar)
            $stmt = $this->db->prepare("
                SELECT 
                    printer_id,
                    printer_name,
                    connection_type,
                    port,
                    status,
                    updated_at
                FROM printers
                WHERE tenant_id = ? AND bridge_id = ? AND status = 'ONLINE'
                ORDER BY printer_name
            ");
            $stmt->execute([$businessId, $bridgeId]);
            $printers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->jsonResponse([
                'success' => true,
                'detected_printers' => $printers
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Get Detected Printers Error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }

    /**
     * Debug endpoint to test API connectivity
     */
    public function debug() {
        try {
            // Basit debug - herhangi bir dependency olmadan
            header('Content-Type: text/plain');
            echo "Printer Bridge Debug: OK\n";
            echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
            echo "Server: " . ($_SERVER['SERVER_NAME'] ?? 'unknown') . "\n";
            exit;
        } catch (\Exception $e) {
            header('Content-Type: text/plain');
            http_response_code(500);
            echo "Error: " . $e->getMessage() . "\n";
            exit;
        }
    }

    private function getJsonInput() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            Logger::warning('Invalid JSON input', [
                'error' => json_last_error_msg(),
                'input_length' => strlen($input)
            ]);
            return [];
        }
        
        return $data ?? [];
    }

    protected function jsonResponse(array $data, int $statusCode = 200): void {
        // CORS headers: Wildcard `*` yerine qordy.com ve alt-alan adlarını
        // allowlist ile serbest bırak. Bu endpoint'ler cihaz API anahtarı
        // dönebildiği için cross-origin okunmasını sıkıca sınırlıyoruz.
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowOrigin = null;
        if ($origin !== '') {
            $host = parse_url($origin, PHP_URL_HOST) ?: '';
            if ($host === 'qordy.com' || (strlen($host) > 10 && substr($host, -10) === '.qordy.com')) {
                $allowOrigin = $origin;
            }
        }
        if ($allowOrigin !== null) {
            header('Access-Control-Allow-Origin: ' . $allowOrigin);
            header('Vary: Origin');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
        }

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code($allowOrigin === null ? 403 : 200);
            exit;
        }
        
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        // Ensure valid JSON encoding
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            Logger::error('JSON encode error', ['error' => json_last_error_msg()]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'JSON encoding error']);
            exit;
        }
        
        echo $json;
        exit;
    }
}
