<?php
namespace App\Services;

/**
 * NavigationPermissionSync
 *
 * Keeps system_permissions in sync with navigation_items.permission_key.
 *
 * Architecture:
 *   navigation_items.permission_key  →  system_permissions  →  package_permissions / role_permissions
 *
 * Rules:
 *  1. Every active navigation_item with a non-empty permission_key gets a matching
 *     system_permissions row (created if missing, updated if name changed).
 *  2. system_permissions rows whose permission_key no longer appears in ANY active
 *     navigation_item are considered "orphaned".
 *  3. Orphaned permissions that are NOT referenced by package_permissions or
 *     role_permissions are deleted automatically.
 *  4. Orphaned permissions that ARE still referenced (package / role) are flagged
 *     but kept — a human should review them.
 */
class NavigationPermissionSync
{
    private $db;

    /** Labels for well-known permission prefixes */
    private const PREFIX_LABELS = [
        'pos'                => 'Kasa',
        'waiter'             => 'Garson Paneli',
        'kitchen'            => 'Mutfak Paneli',
        'preparation-screens'=> 'Hazırlık Ekranları',
        'menu'               => 'Menü',
        'tables'             => 'Masalar',
        'orders'             => 'Siparişler',
        'reservations'       => 'Rezervasyon',
        'finance'            => 'Finans',
        'stock'              => 'Stok',
        'reports'            => 'Raporlar',
        'dashboard'          => 'Özet',
        'staff'              => 'Personel',
        'printers'           => 'Yazıcılar',
        'receipt'            => 'Fiş',
        'settings'           => 'Ayarlar',
        'roles'              => 'Roller',
        'permissions'        => 'İzinler',
        'system'             => 'Sistem',
        'error'              => 'Hata',
        'businesses'         => 'İşletmeler',
        'business_owners'    => 'İşletme Sahipleri',
        'payment'            => 'Ödeme',
        'billing'            => 'Faturalama',
        'company'            => 'Şirket',
        'account'            => 'Hesap',
        'profile'            => 'Profil',
        'packages'           => 'Paketler',
        'subscriptions'      => 'Abonelikler',
    ];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Full sync: create missing permissions, remove safe orphans, and clean up
     * stale per-business preparation-screen permissions.
     *
     * @return array{created: int, updated: int, orphaned: int, deleted: int, kept_orphans: array, prep_screen_deleted: int}
     */
    public function syncAll(): array
    {
        $stats = [
            'created'             => 0,
            'updated'             => 0,
            'orphaned'            => 0,
            'deleted'             => 0,
            'prep_screen_deleted' => 0,
            'kept_orphans'        => [],
            'errors'              => [],
        ];

        try {
            // --- Step 1: Upsert permissions for all active nav items ---
            $navItems = $this->getActiveNavItemsWithPermission();
            foreach ($navItems as $nav) {
                try {
                    $result = $this->upsertPermission(
                        $nav['permission_key'],
                        $nav['label_tr'] ?? '',
                        $nav['description'] ?? ''
                    );
                    if ($result === 'created') {
                        $stats['created']++;
                    } elseif ($result === 'updated') {
                        $stats['updated']++;
                    }
                } catch (\Exception $e) {
                    $stats['errors'][] = "Upsert failed for {$nav['permission_key']}: " . $e->getMessage();
                }
            }

            // --- Step 2: Collect all nav permission keys (active) ---
            $activeNavPermKeys = array_unique(array_column($navItems, 'permission_key'));

            // --- Step 3: Clean up stale preparation-screen dynamic permissions ---
            // Permissions like preparation-screen.{slug}.view / update_status / bar.print are
            // auto-created when a business creates a prep screen.  When the screen is deleted,
            // the permission should be removed too.
            $prepScreenDeleted = $this->cleanupStalePreparationScreenPermissions();
            $stats['prep_screen_deleted'] = $prepScreenDeleted;

            // --- Step 4: Find orphaned system_permissions ---
            $allSysPerms = $this->getAllSystemPermissions();
            foreach ($allSysPerms as $perm) {
                $key = $perm['permission_key'];

                // Skip if still present in navigation
                if (in_array($key, $activeNavPermKeys, true)) {
                    continue;
                }

                // Skip known sub-action permissions (fine-grained RBAC — intentionally not
                // backed by a nav item).
                if ($this->isKnownSubPermission($key)) {
                    continue;
                }

                $stats['orphaned']++;

                // Check if referenced by package_permissions or role_permissions
                if ($this->isPermissionReferenced($perm['permission_id'])) {
                    $stats['kept_orphans'][] = [
                        'permission_id'  => $perm['permission_id'],
                        'permission_key' => $key,
                        'reason'         => 'Still used in package or role permissions',
                    ];
                    continue;
                }

                // Safe to delete
                try {
                    $this->deletePermission($perm['permission_id']);
                    $stats['deleted']++;
                } catch (\Exception $e) {
                    $stats['errors'][] = "Delete failed for {$key}: " . $e->getMessage();
                }
            }

        } catch (\Exception $e) {
            $stats['errors'][] = 'Fatal sync error: ' . $e->getMessage();
        }

        return $stats;
    }

    /**
     * Remove preparation-screen.{slug}.* and {slug}.print permissions for slugs
     * that no longer exist in the preparation_screens table.
     *
     * @return int  Number of permissions deleted
     */
    public function cleanupStalePreparationScreenPermissions(): int
    {
        $deleted = 0;

        try {
            // Collect all active preparation screen slugs
            $activeSlugs = [];
            try {
                $stmt = $this->db->query(
                    "SELECT DISTINCT slug FROM preparation_screens WHERE is_active = 1 AND slug IS NOT NULL"
                );
                $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                $activeSlugs = array_map('strtolower', $rows);
            } catch (\Exception $e) {
                // preparation_screens table might not exist — graceful skip
                return 0;
            }

            // Find all system_permissions with preparation-screen.* pattern
            $stmt = $this->db->query(
                "SELECT permission_id, permission_key
                 FROM system_permissions
                 WHERE permission_key LIKE 'preparation-screen.%'
                    OR permission_key REGEXP '^[a-z][a-z0-9_-]+\\.print$'"
            );
            $candidates = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($candidates as $perm) {
                $key  = $perm['permission_key'];
                $id   = $perm['permission_id'];
                $slug = null;

                if (preg_match('/^preparation-screen\.([^.]+)\.\w+$/', $key, $m)) {
                    $slug = strtolower($m[1]);
                } elseif (preg_match('/^([a-z][a-z0-9_-]+)\.print$/', $key, $m)) {
                    // e.g.  bar.print  — only treat as a prep-screen permission if the
                    // prefix exactly matches a former prep-screen slug pattern
                    $slug = strtolower($m[1]);
                    // Skip known non-prep-screen prefixes
                    $corePrefix = ['receipt', 'order', 'orders', 'kitchen', 'pos', 'waiter'];
                    if (in_array($slug, $corePrefix, true)) {
                        continue;
                    }
                }

                if ($slug === null) {
                    continue;
                }

                // If slug is still active, keep the permission
                if (in_array($slug, $activeSlugs, true)) {
                    continue;
                }

                // Remove from package_permissions and role_permissions first
                foreach (['package_permissions', 'role_permissions'] as $table) {
                    try {
                        $this->db->prepare("DELETE FROM {$table} WHERE permission_id = ?")
                                 ->execute([$id]);
                    } catch (\Exception $e) { /* ignore */ }
                }

                // Now safe to delete
                try {
                    $this->deletePermission($id);
                    $deleted++;
                } catch (\Exception $e) { /* ignore */ }
            }

        } catch (\Exception $e) {
            // Non-fatal
        }

        return $deleted;
    }

    /**
     * Sync permissions from an explicit list of navigation items.
     * Useful when called from the seed script.
     *
     * @param  array[] $navItems  Each item must have 'permission_key' and optionally 'label_tr'.
     * @return array{created: int, updated: int}
     */
    public function syncFromNavItemsList(array $navItems): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'errors' => []];

        foreach ($navItems as $item) {
            $key = $item['permission_key'] ?? '';
            if (empty($key) || $key === '-') {
                continue;
            }
            try {
                $result = $this->upsertPermission(
                    $key,
                    $item['label_tr'] ?? '',
                    ''
                );
                if ($result === 'created') {
                    $stats['created']++;
                } elseif ($result === 'updated') {
                    $stats['updated']++;
                }
            } catch (\Exception $e) {
                $stats['errors'][] = "Upsert failed for {$key}: " . $e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * Return a list of orphaned system_permissions (no matching active nav item).
     */
    public function getOrphanedPermissions(): array
    {
        $navItems  = $this->getActiveNavItemsWithPermission();
        $activeKeys = array_unique(array_column($navItems, 'permission_key'));
        $allSys    = $this->getAllSystemPermissions();

        $orphans = [];
        foreach ($allSys as $perm) {
            if (!in_array($perm['permission_key'], $activeKeys, true)) {
                $perm['is_referenced'] = $this->isPermissionReferenced($perm['permission_id']);
                $orphans[] = $perm;
            }
        }
        return $orphans;
    }

    // ------------------------------------------------------------------ //
    //  Private helpers                                                     //
    // ------------------------------------------------------------------ //

    /**
     * True for well-known sub-action permissions that are intentionally NOT backed
     * by a navigation item but are still used for fine-grained RBAC.
     * These should never be treated as "orphaned" and auto-deleted.
     */
    private function isKnownSubPermission(string $key): bool
    {
        // Pattern: preparation-screen.{slug}.* is handled separately
        if (strpos($key, 'preparation-screen.') === 0) {
            return true;
        }

        // Known sub-permissions for each module that extend the .view nav permission
        $knownSubPatterns = [
            // Orders
            'orders.create', 'orders.edit', 'orders.delete', 'orders.complete',
            'orders.process', 'orders.print', 'orders.update', 'orders.approve',
            // Menu
            'menu.create', 'menu.edit', 'menu.delete',
            // Tables
            'tables.manage', 'tables.transfer', 'table.history', 'tables.create',
            'tables.edit', 'tables.delete',
            // Kitchen
            'kitchen.dashboard', 'kitchen.print', 'kitchen.update_status',
            // Waiter
            'waiter.dashboard',
            // POS / Cashier
            'pos.process_payment', 'pos.refund', 'pos.dashboard',
            'cashier.view', 'cashier.count_cash', 'cashier.shift', 'cashier.z_report',
            // Finance
            'finance.shifts',
            // Stock
            'stock.edit', 'stock.movements', 'stock.transfer', 'stock.adjust',
            // Preparation screens (config)
            'preparation-screens.create', 'preparation-screens.edit',
            'preparation-screens.delete',
            // Staff
            'staff.create', 'staff.edit', 'staff.delete',
            // Roles
            'roles.create', 'roles.edit', 'roles.delete', 'roles.view',
            // Permissions
            'permissions.view', 'permissions.manage',
            // Printers
            'printers.create', 'printers.edit', 'printers.delete', 'printers.test',
            // Reservations
            'reservations.create', 'reservations.edit', 'reservations.delete',
            // Reports
            'reports.export',
            // Receipts
            'receipt.print', 'receipt.refund', 'receipt.void', 'receipts.print',
            // Settings
            'settings.edit', 'settings.reset',
            'system.settings.view', 'system.settings.edit',
            // Dashboard
            'dashboard.view', 'dashboard.analytics',
            // Profile / Account
            'profile.view', 'profile.edit', 'profile.update',
            'account.view', 'account.edit', 'account.update', 'account.delete',
            // Company / Billing
            'company.view', 'company.edit', 'company.update',
            'payment.methods.view', 'payment.methods.add', 'payment.methods.delete',
            'billing.view', 'billing.download',
            'invoices.view',
            // Packages / Subscriptions
            'packages.view', 'packages.purchase',
            'customer.packages', 'customer.packages.view',
            'subscriptions.view', 'subscriptions.manage',
            // Old order approval nav-keys (kept for backwards compat)
            'order_approvals.view', 'order_approval_history.view',
        ];

        return in_array($key, $knownSubPatterns, true);
    }

    private function getActiveNavItemsWithPermission(): array
    {
        $stmt = $this->db->query(
            "SELECT nav_id, nav_key, label_tr, permission_key
             FROM   navigation_items
             WHERE  is_active = 1
               AND  permission_key IS NOT NULL
               AND  permission_key != ''
               AND  permission_key != '-'
             ORDER  BY nav_key"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getAllSystemPermissions(): array
    {
        $stmt = $this->db->query(
            "SELECT permission_id, permission_key, permission_name
             FROM   system_permissions
             ORDER  BY permission_key"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Insert or update a system_permission row.
     *
     * @return string  'created' | 'updated' | 'noop'
     */
    private function upsertPermission(string $key, string $label, string $description): string
    {
        $permName = $this->buildPermissionName($key, $label);
        $permId   = $key; // convention: permission_id === permission_key

        $existing = $this->db->prepare(
            "SELECT permission_id, permission_name FROM system_permissions WHERE permission_key = ?"
        );
        $existing->execute([$key]);
        $row = $existing->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            $stmt = $this->db->prepare(
                "INSERT INTO system_permissions
                     (permission_id, permission_key, permission_name, description)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                     permission_name = VALUES(permission_name),
                     description = VALUES(description)"
            );
            $stmt->execute([$permId, $key, $permName, $description]);
            return 'created';
        }

        if ($row['permission_name'] !== $permName) {
            $stmt = $this->db->prepare(
                "UPDATE system_permissions
                 SET permission_name = ?, description = ?
                 WHERE permission_key = ?"
            );
            $stmt->execute([$permName, $description, $key]);
            return 'updated';
        }

        return 'noop';
    }

    /**
     * Build a human-readable permission name from key + optional nav label.
     * Examples:  pos.view → "Kasa - Görüntüle"
     *            menu.categories → "Menü - Kategoriler"
     *            preparation-screens.view → "Hazırlık Ekranları - Görüntüle"
     */
    private function buildPermissionName(string $key, string $navLabel = ''): string
    {
        $actionLabels = [
            'view'          => 'Görüntüle',
            'create'        => 'Oluştur',
            'edit'          => 'Düzenle',
            'update'        => 'Güncelle',
            'delete'        => 'Sil',
            'manage'        => 'Yönet',
            'export'        => 'Dışa Aktar',
            'print'         => 'Yazdır',
            'dashboard'     => 'Dashboard',
            'analytics'     => 'Analitik',
            'expenses'      => 'Giderler',
            'invoices'      => 'Faturalar',
            'suppliers'     => 'Tedarikçiler',
            'waste'         => 'Fire',
            'shifts'        => 'Vardiyalar',
            'categories'    => 'Kategoriler',
            'movements'     => 'Hareketler',
            'transfer'      => 'Transfer',
            'adjust'        => 'Düzeltme',
            'settings'      => 'Ayarlar',
            'templates'     => 'Şablonlar',
            'process_payment' => 'Ödeme İşle',
            'update_status' => 'Durum Güncelle',
            'complete'      => 'Tamamla',
            'history'       => 'Geçmiş',
            'test'          => 'Test',
            'download'      => 'İndir',
            'purchase'      => 'Satın Al',
            'list'          => 'Listele',
        ];

        $parts  = explode('.', $key, 2);
        $prefix = $parts[0] ?? $key;
        $action = $parts[1] ?? '';

        $prefixLabel = self::PREFIX_LABELS[$prefix] ?? ucfirst(str_replace(['-', '_'], ' ', $prefix));

        if (!empty($action)) {
            // action can itself be multi-segment, e.g.  receipt.templates.view → take last word
            $actionParts = explode('.', $action);
            $lastAction  = end($actionParts);
            $actionLabel = $actionLabels[$lastAction] ?? ucfirst(str_replace(['-', '_'], ' ', $action));
            return $prefixLabel . ' - ' . $actionLabel;
        }

        if (!empty($navLabel)) {
            return $navLabel;
        }

        return $prefixLabel;
    }

    private function isPermissionReferenced(string $permissionId): bool
    {
        $tables = ['package_permissions', 'role_permissions'];
        foreach ($tables as $table) {
            try {
                $stmt = $this->db->prepare(
                    "SELECT 1 FROM {$table} WHERE permission_id = ? LIMIT 1"
                );
                $stmt->execute([$permissionId]);
                if ($stmt->fetch()) {
                    return true;
                }
            } catch (\Exception $e) {
                // Table might not exist — ignore
            }
        }
        return false;
    }

    private function deletePermission(string $permissionId): void
    {
        $this->db->prepare("DELETE FROM system_permissions WHERE permission_id = ?")
                 ->execute([$permissionId]);
    }
}
