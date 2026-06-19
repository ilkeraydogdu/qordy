<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class Table extends \App\Core\Model {
    protected $table = 'tables';
    
    public function getAll(): array {
        return $this->query()
            ->orderBy('zone_id')
            ->orderBy('floor')
            ->orderBy('section')
            ->orderBy('name')
            ->get();
    }
    
    public function getById(string $tableId) {
        return $this->query()
            ->where('table_id', $tableId)
            ->first();
    }
    
    /**
     * Get tables by zone name (backward compatibility)
     * @param string $zone Zone name
     * @return array Tables
     */
    public function getByZone(string $zone): array {
        return $this->query()
            ->where('zone', $zone)
            ->orderBy('name')
            ->get();
    }
    
    /**
     * Get tables by zone_id
     * @param string $zoneId Zone ID
     * @return array Tables
     */
    public function getByZoneId(string $zoneId): array {
        return $this->query()
            ->where('zone_id', $zoneId)
            ->orderBy('floor')
            ->orderBy('section')
            ->orderBy('name')
            ->get();
    }
    
    /**
     * Get tables by floor
     * @param string $floor Floor name
     * @return array Tables
     */
    public function getByFloor(string $floor): array {
        return $this->query()
            ->where('floor', $floor)
            ->orderBy('section')
            ->orderBy('name')
            ->get();
    }
    
    /**
     * Get tables by section
     * @param string $section Section name
     * @return array Tables
     */
    public function getBySection(string $section): array {
        return $this->query()
            ->where('section', $section)
            ->orderBy('name')
            ->get();
    }
    
    public function getByStatus(string $status): array {
        return $this->query()
            ->where('status', $status)
            ->orderBy('zone_id')
            ->orderBy('floor')
            ->orderBy('section')
            ->orderBy('name')
            ->get();
    }
    
    public function create(array $data) {
        // URL and QR code generation is handled by TableService
        // This method is only used if called directly (rare case)
        // TableRepository uses BaseRepository::create which doesn't call this
        return $this->query()
            ->insert($data);
    }

    public function updateTable(string $tableId, array $data) {
        // Auto-update URL and QR code if table_id changed
        if (isset($data['table_id']) && $data['table_id'] !== $tableId) {
            $data['url'] = $this->generateTableUrl($data['table_id']);
            $data['qr_code_url'] = $this->generateQRCodeUrl($data['url']);
        }
        
        return $this->query()
            ->where('table_id', $tableId)
            ->update($data);
    }

    public function updateStatus(string $tableId, string $status, ?string $sessionStartTime = null) {
        $data = ['status' => $status];
        if ($status === 'OCCUPIED' && $sessionStartTime === null) {
            $data['session_start_time'] = date('Y-m-d H:i:s');
        } elseif ($sessionStartTime !== null) {
            $data['session_start_time'] = $sessionStartTime;
        }
        return $this->query()
            ->where('table_id', $tableId)
            ->update($data);
    }

    public function deleteTable(string $tableId) {
        return $this->query()
            ->where('table_id', $tableId)
            ->delete();
    }
    
    /**
     * Generate table URL (SEO-friendly)
     * Uses centralized UrlService for consistency
     * @param string $tableId Table ID
     * @return string Table URL
     */
    public function generateTableUrl(string $tableId): string {
        // Use centralized UrlService for SEO-friendly URL generation
        try {
            $urlService = \App\Core\DependencyFactory::getUrlService();
            return $urlService->generateTableUrl($tableId, true);
        } catch (\Exception $e) {
            // Fallback to old format if service unavailable
            $baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost';
            return $baseUrl . '/t/' . $tableId;
        }
    }
    
    /**
     * Generate QR code URL
     * @param string $tableUrl Table URL
     * @return string QR code URL
     */
    public function generateQRCodeUrl(string $tableUrl): string {
        return rtrim(BASE_URL, '/') . '/qr?size=500&data=' . urlencode($tableUrl);
    }
    
    /**
     * Generate and save QR code for table
     * @param string $tableId Table ID
     * @return string|false QR code URL or false on failure
     */
    public function generateQRCodeForTable(string $tableId) {
        // Use TableService for SEO-friendly URL generation
        try {
            $tableService = \App\Core\DependencyFactory::getTableService();
            return $tableService->generateQRCodeForTable($tableId);
        } catch (\Exception $e) {
            // Fallback to old method if service unavailable
            $table = $this->getById($tableId);
            if (!$table) {
                return false;
            }
            
            $tableUrl = $table['url'] ?? $this->generateTableUrl($tableId);
            $qrCodeUrl = $this->generateQRCodeUrl($tableUrl);
            
            $this->updateTable($tableId, [
                'url' => $tableUrl,
                'qr_code_url' => $qrCodeUrl
            ]);
            
            return $qrCodeUrl;
        }
    }
    
    /**
     * Get orders for this table (hasMany relationship)
     * @param string $tableId
     * @return array
     */
    public function getOrders(string $tableId): array {
        require_once __DIR__ . '/Order.php';
        $orderModel = new \App\Models\Order();
        return $orderModel->getByTableId($tableId);
    }
    
    /**
     * Get reservations for this table (hasMany relationship)
     * @param string $tableId
     * @return array
     */
    public function getReservations(string $tableId): array {
        require_once __DIR__ . '/Reservation.php';
        $reservationModel = new \App\Models\Reservation();
        return $reservationModel->getByTable($tableId);
    }
    
    /**
     * Get notifications for this table (hasMany relationship)
     * @param string $tableId
     * @return array
     */
    public function getNotifications(string $tableId): array {
        require_once __DIR__ . '/Notification.php';
        $notificationModel = new \App\Models\Notification();
        return $notificationModel->getByTable($tableId);
    }
    
    public function getActiveTables(): array {
        return $this->query()
            ->whereIn('status', ['OCCUPIED', 'PAYMENT_PENDING'])
            ->orderBy('zone_id')
            ->orderBy('floor')
            ->orderBy('name')
            ->get();
    }
    
    public function getOccupiedCount(): int {
        return $this->query()
            ->where('status', 'OCCUPIED')
            ->count();
    }
    
    public function getPendingPaymentCount(): int {
        return $this->query()
            ->where('status', 'PAYMENT_PENDING')
            ->count();
    }
    
    /**
     * Get all zones (backward compatibility - uses zone_id now)
     * @return array Zone names
     */
    public function getAllZones(): array {
        $db = $this->getDbConnection();
        $sql = "SELECT DISTINCT z.name 
                FROM zones z 
                INNER JOIN tables t ON t.zone_id = z.zone_id 
                WHERE z.name IS NOT NULL AND z.name != '' 
                ORDER BY z.name";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_column($results, 'name');
    }
    
    /**
     * Get tables with zone information
     * @return array Tables with zone data
     */
    public function getAllWithZoneInfo(): array {
        $db = $this->getDbConnection();
        $sql = "SELECT t.*, z.name as zone_name, z.floor as zone_floor, z.description as zone_description
                FROM {$this->table} t
                LEFT JOIN zones z ON t.zone_id = z.zone_id
                ORDER BY z.floor, z.name, t.floor, t.section, t.name";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
