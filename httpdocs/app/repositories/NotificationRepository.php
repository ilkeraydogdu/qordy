<?php

namespace App\Repositories;

use App\Core\BaseRepository;

class NotificationRepository extends BaseRepository {

 protected $table = 'notifications';

 protected $primaryKey = 'notification_id';

 public function __construct($database) {
 parent::__construct($database);
 }

 public function getUnread(): array {
 try {
 $tenantId = \App\Core\TenantResolver::resolve();
 if (!$tenantId) {
 return [];
 }

 $sql = "
 SELECT n.*,
 t.name as table_name,
 z.name as zone_name
 FROM {$this->table} n
 INNER JOIN tables t ON n.table_id = t.table_id
 LEFT JOIN zones z ON t.zone_id = z.zone_id
 WHERE t.tenant_id IS NOT NULL
 AND t.tenant_id = :tenant_id
 AND n AND n.is_read = 0
 ORDER BY COALESCE(n.created_at, n.timestamp) DESC
 ";

 return $this->fetchAll($sql, ['tenant_id' => $tenantId]);
 } catch (\PDOException $e) {
 error_log("PDO Error in getUnread: " . $e->getMessage() . " | SQLState: " . ($e->errorInfo[0] ?? 'unknown') . " | SQL: " . ($sql ?? 'N/A'));
 return [];
 } catch (\Exception $e) {
 error_log("Error in getUnread: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
 return [];
 } catch (\Throwable $e) {
 error_log("Fatal Error in getUnread: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
 return [];
 }
 }

 public function getByType(string $type): array {
 try {
 $tenantId = \App\Core\TenantResolver::resolve();
 if (!$tenantId) {
 return [];
 }
 $sql = "SELECT n.* FROM {$this->table} n
 INNER JOIN tables t ON n.table_id = t.table_id
 WHERE n.type = :type
 AND t.tenant_id = :tenant_id
 ORDER BY COALESCE(n.created_at, n.timestamp) DESC";
 return $this->fetchAll($sql, ['type' => $type, 'tenant_id' => $tenantId]);
 } catch (\PDOException $e) {
 error_log("PDO Error in getByType: " . $e->getMessage() . " | SQLState: " . ($e->errorInfo[0] ?? 'unknown'));
 return [];
 } catch (\Exception $e) {
 error_log("Error in getByType: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
 return [];
 }
 }

 public function markAsRead(string $notificationId): bool {
 $sql = "UPDATE {$this->table} SET is_read = 1 WHERE {$this->primaryKey} = :notification_id";
 return $this->execute($sql, ['notification_id' => $notificationId]);
 }

 public function markAsReadByApprovalId(string $approvalId): bool {
 try {
 $likePattern = '%"approval_id":"' . str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $approvalId) . '"%';
 $sql = "UPDATE {$this->table} SET is_read = 1
 WHERE type IN ('EDIT_APPROVAL', 'ORDER_EDIT_APPROVAL')
 AND (JSON_UNQUOTE(JSON_EXTRACT(data, '$.approval_id')) = :approval_id
 OR data LIKE :approval_id_like)";
 return $this->execute($sql, [
 'approval_id' => $approvalId,
 'approval_id_like' => $likePattern
 ]);
 } catch (\Throwable $e) {
 try {
 $likePattern = '%"approval_id":"' . str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $approvalId) . '"%';
 $sql = "UPDATE {$this->table} SET is_read = 1
 WHERE type IN ('EDIT_APPROVAL', 'ORDER_EDIT_APPROVAL') AND data LIKE :approval_id_like";
 return $this->execute($sql, ['approval_id_like' => $likePattern]);
 } catch (\Throwable $e2) {
 error_log("NotificationRepository::markAsReadByApprovalId error: " . $e2->getMessage());
 return false;
 }
 }
 }

 public function markAllAsRead(): bool {
 try {
 $tenantId = \App\Core\TenantResolver::resolve();
 if (!$tenantId) {
 return false;
 }
 $sql = "UPDATE {$this->table} n
 INNER JOIN tables t ON n.table_id = t.table_id
 SET n.is_read = 1
 WHERE n AND n.is_read = 0 AND t.tenant_id = :tenant_id";
 return $this->execute($sql, ['tenant_id' => $tenantId]);
 } catch (\Exception $e) {
 error_log("Error in markAllAsRead: " . $e->getMessage());
 return false;
 }
 }

 public function markAllAsReadForBusinessDay(string $startDatetime, string $endDatetime): bool {
 try {
 $tenantId = \App\Core\TenantResolver::resolve();
 if (!$tenantId) return false;
 $sql = "UPDATE {$this->table} n
 INNER JOIN tables t ON n.table_id = t.table_id
 SET n.is_read = 1
 WHERE n AND n.is_read = 0 AND t.tenant_id = :tenant_id
 AND COALESCE(n.created_at, n.timestamp) BETWEEN :start_dt AND :end_dt";
 return $this->execute($sql, [
 'tenant_id' => $tenantId,
 'start_dt' => $startDatetime,
 'end_dt' => $endDatetime,
 ]);
 } catch (\Exception $e) {
 error_log("Error in markAllAsReadForBusinessDay: " . $e->getMessage());
 return false;
 }
 }

 public function getUnreadCount(): int {
 try {
 $tenantId = \App\Core\TenantResolver::resolve();
 if (!$tenantId) {
 return 0;
 }
 $sql = "SELECT COUNT(*) as count
 FROM {$this->table} n
 INNER JOIN tables t ON n.table_id = t.table_id
 WHERE t.tenant_id = :tenant_id AND n.is_read = 0";
 $result = $this->fetchOne($sql, ['tenant_id' => $tenantId]);
 return (int)($result['count'] ?? 0);
 } catch (\Exception $e) {
 error_log("Error in getUnreadCount: " . $e->getMessage());
 return 0;
 }
 }

 public function getByTable(string $tableId): array {
 try {
 $tenantId = \App\Core\TenantResolver::resolve();
 if (!$tenantId) {
 return [];
 }
 $sql = "SELECT n.* FROM {$this->table} n
 INNER JOIN tables t ON n.table_id = t.table_id
 WHERE n.table_id = :table_id AND t.tenant_id = :tenant_id
 ORDER BY COALESCE(n.created_at, n.timestamp) DESC";
 return $this->fetchAll($sql, ['table_id' => $tableId, 'tenant_id' => $tenantId]);
 } catch (\Exception $e) {
 error_log("Error in getByTable: " . $e->getMessage());
 return [];
 }
 }

 public function updateTableId(string $fromTableId, string $toTableId): bool {
 try {
 $sql = "UPDATE {$this->table} SET table_id = :to_table_id WHERE table_id = :from_table_id";
 return $this->execute($sql, ['from_table_id' => $fromTableId, 'to_table_id' => $toTableId]);
 } catch (\Throwable $e) {
 error_log("NotificationRepository::updateTableId error: " . $e->getMessage());
 return false;
 }
 }

 public function getAll(int $limit = null): array {
 try {
 $tenantId = \App\Core\TenantResolver::resolve();
 if (!$tenantId) {
 return [];
 }
 $sql = "SELECT n.*, t.name as table_name, z.name as zone_name
 FROM {$this->table} n
 INNER JOIN tables t ON n.table_id = t.table_id
 LEFT JOIN zones z ON t.zone_id = z.zone_id
 WHERE t.tenant_id = :tenant_id
 ORDER BY COALESCE(n.created_at, n.timestamp) DESC";
 if ($limit) {
 $sql .= " LIMIT " . (int)$limit;
 }
 return $this->fetchAll($sql, ['tenant_id' => $tenantId]);
 } catch (\Exception $e) {
 error_log("Error in getAll: " . $e->getMessage());
 return [];
 }
 }

 public function delete(string $id): bool {
 try {
 $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :notification_id";
 return $this->execute($sql, ['notification_id' => $id]);
 } catch (\PDOException $e) {
 error_log("PDO Error deleting notification: " . $e->getMessage() . " | SQLState: " . ($e->errorInfo[0] ?? 'unknown') . " | notification_id: " . $id);
 return false;
 } catch (\Exception $e) {
 error_log("Failed to delete notification: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine() . " | notification_id: " . $id);
 return false;
 }
 }

 /**
 * Purge notifications older than $days days.
 * Designed for end-of-day housekeeping cron — keeps the table lean
 * and prevents stale rows from showing up in the dashboard months later.
 *
 * @param int $days Minimum age in days. Default 1 = purge anything not from today.
 * @return int Number of rows deleted.
 */
 public function purgeOlderThan(int $days = 1): int {
 try {
 $sql = "DELETE FROM {$this->table}
 WHERE COALESCE(created_at, timestamp) < (NOW() - INTERVAL :days DAY)";
 $stmt = $this->db->prepare($sql);
 $stmt->bindValue(':days', max(0, $days), \PDO::PARAM_INT);
 $stmt->execute();
 return (int)$stmt->rowCount();
 } catch (\Exception $e) {
 error_log("purgeOlderThan failed: " . $e->getMessage());
 return 0;
 }
 }

 /**
 * Get notifications for current business day (by business working hours)
 * @param string $startDatetime e.g. '2025-02-28 09:00:00'
 * @param string $endDatetime e.g. '2025-02-28 23:59:59' or next day for overnight
 * @param int|null $limit
 * @return array
 */
 public function getForBusinessDay(string $startDatetime, string $endDatetime, ?int $limit = 100): array {
 try {
 $tenantId = \App\Core\TenantResolver::resolve();
 if (!$tenantId) {
 return [];
 }
 $sql = "SELECT n.*, t.name as table_name, z.name as zone_name
 FROM {$this->table} n
 INNER JOIN tables t ON n.table_id = t.table_id
 LEFT JOIN zones z ON t.zone_id = z.zone_id
 WHERE t.tenant_id = :tenant_id
 AND COALESCE(n.created_at, n.timestamp) BETWEEN :start_dt AND :end_dt
 ORDER BY COALESCE(n.created_at, n.timestamp) DESC";
 if ($limit) {
 $sql .= " LIMIT " . (int)$limit;
 }
 return $this->fetchAll($sql, [
 'tenant_id' => $tenantId,
 'start_dt' => $startDatetime,
 'end_dt' => $endDatetime,
 ]);
 } catch (\Exception $e) {
 error_log("NotificationRepository::getForBusinessDay error: " . $e->getMessage());
 return [];
 }
 }

 /**
 * Get unread count for current business day
 */
 public function getUnreadCountForBusinessDay(string $startDatetime, string $endDatetime): int {
 try {
 $tenantId = \App\Core\TenantResolver::resolve();
 if (!$tenantId) {
 return 0;
 }
 $sql = "SELECT COUNT(*) as count
 FROM {$this->table} n
 INNER JOIN tables t ON n.table_id = t.table_id
 WHERE t.tenant_id = :tenant_id
 AND n.is_read = 0
 AND COALESCE(n.created_at, n.timestamp) BETWEEN :start_dt AND :end_dt";
 $result = $this->fetchOne($sql, [
 'tenant_id' => $tenantId,
 'start_dt' => $startDatetime,
 'end_dt' => $endDatetime,
 ]);
 return (int)($result['count'] ?? 0);
 } catch (\Exception $e) {
 error_log("NotificationRepository::getUnreadCountForBusinessDay error: " . $e->getMessage());
 return 0;
 }
 }

 public function getNotificationsSince($timestamp) {
 try {
 $tenantId = \App\Core\TenantResolver::resolve();
 if (!$tenantId) {
 return [];
 }
 $sql = "SELECT n.*, t.name as table_name, z.name as zone_name
 FROM {$this->table} n
 INNER JOIN tables t ON n.table_id = t.table_id
 LEFT JOIN zones z ON t.zone_id = z.zone_id
 WHERE t.tenant_id = :tenant_id
 AND COALESCE(n.created_at, n.timestamp) > FROM_UNIXTIME(:timestamp)
 AND n.is_read = 0
 ORDER BY COALESCE(n.created_at, n.timestamp) DESC
 LIMIT 50";
 return $this->fetchAll($sql, ['tenant_id' => $tenantId, 'timestamp' => $timestamp]);
 } catch (\Exception $e) {
 error_log("Error in getNotificationsSince: " . $e->getMessage());
 return [];
 }
 }

}
