<?php
namespace App\Core;

/**
 * Single source of truth for resolving the canonical tenant ID.
 *
 * ARCHITECTURAL NOTE
 * ------------------
 * Historically the codebase referred to the same concept with three different
 * names:
 *
 *   - customer_id (stored on customers table PK)
 *   - business_id (stored on businesses table PK + subdomains/subscriptions FK)
 *   - tenant_id   (stored on every operational table: orders, menu_items, ...)
 *
 * All three hold the SAME value (e.g. CUST_69653cfccb839). The canonical name
 * going forward is `tenant_id`. This class normalizes any of the legacy names
 * to the canonical one so callers never have to think about it.
 *
 * Resolution priority:
 *   1. Session key `tenant_id`
 *   2. Session key `business_id` (legacy)
 *   3. Session key `customer_id` (legacy)
 *   4. TenantContext (set by middleware from subdomain / bootstrap)
 *   5. null  ->  callers MUST treat null as "no data allowed"
 */
class TenantResolver
{
    /**
     * Canonical session/context keys that may hold the tenant id.
     * Ordered by preference.
     */
    public const TENANT_KEYS = ['tenant_id', 'business_id', 'customer_id'];

    /**
     * Resolve the current tenant id from session or TenantContext.
     */
    public static function resolve(): ?string
    {
        SessionManager::ensureSession();

        foreach (self::TENANT_KEYS as $key) {
            if (!empty($_SESSION[$key])) {
                return (string) $_SESSION[$key];
            }
        }

        if (TenantContext::isSet()) {
            return (string) TenantContext::getId();
        }

        // Fallback: logged-in staff/owner sessions may only carry user_id.
        // Without a tenant key every repository query becomes `1=0` and the
        // dashboard poller returns empty KPIs even though orders exist.
        $userId = $_SESSION['user_id'] ?? null;
        if (!empty($userId) && is_string($userId)) {
            if (str_starts_with($userId, 'CUST_')) {
                SessionManager::setTenantSession($userId);
                return $userId;
            }
            try {
                $userService = \App\Core\DependencyFactory::getUserService();
                if (method_exists($userService, 'findByUserId')) {
                    $user = $userService->findByUserId($userId);
                    $tenantFromUser = self::fromArray($user ?? []);
                    if ($tenantFromUser !== null && $tenantFromUser !== '') {
                        SessionManager::setTenantSession($tenantFromUser);
                        return $tenantFromUser;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore lookup failures — caller treats null as no tenant.
            }
        }

        return null;
    }

    /**
     * Extract a canonical tenant id from an arbitrary input array (DB row,
     * request payload, etc.) by checking every legacy key.
     */
    public static function fromArray(array $row): ?string
    {
        foreach (self::TENANT_KEYS as $key) {
            if (isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null) {
                return (string) $row[$key];
            }
        }
        return null;
    }

    /**
     * Ensure a write payload carries the canonical `tenant_id` column (and,
     * for transitional compatibility, the legacy `business_id` mirror).
     *
     * Usage:
     *   $data = TenantResolver::stampTenant($data);
     *
     * If the payload already carries a tenant id under any legacy name, the
     * canonical copy is synced. Otherwise the resolver pulls the id from
     * session/TenantContext. No-op if no tenant can be resolved (super-admin
     * flows that legitimately create system-wide rows must pass an explicit
     * `tenant_id` themselves).
     */
    public static function stampTenant(array $data): array
    {
        $tenantId = self::fromArray($data) ?? self::resolve();

        if ($tenantId === null || $tenantId === '') {
            return $data;
        }

        // Canonical column — always set.
        $data['tenant_id'] = $tenantId;

        // Legacy mirror — BaseRepository strips unknown columns at insert
        // time, so this is safe even for tables that don't carry business_id.
        // Keeps any existing consumer that still reads business_id working
        // during the migration period.
        if (!array_key_exists('business_id', $data)) {
            $data['business_id'] = $tenantId;
        }

        return $data;
    }

    /**
     * Compare a row's tenant identifier against the current tenant context.
     * Returns true when the row belongs to the current tenant (or no tenant
     * is enforced on the row, i.e. legacy data).
     */
    public static function rowBelongsToCurrentTenant(array $row): bool
    {
        $rowTenant = self::fromArray($row);
        if ($rowTenant === null) {
            // Legacy row without any tenant column — treated as accessible
            // because repository-level filtering already constrained it.
            return true;
        }
        $current = self::resolve();
        if ($current === null) {
            return false;
        }
        return (string) $rowTenant === (string) $current;
    }

    /**
     * Tables that are system-wide and must never be tenant-filtered.
     * Used by BaseRepository and any other code that needs the canonical list.
     */
    public static function getExcludedTables(): array
    {
        return [
            'users',
            'customers',
            'system_permissions',
            'roles',
            'role_permissions',
            'subdomains',
            'admins',
            'packages',
            'subscriptions',
            'migrations',
            'settings',
            'system_settings',
            'system_constants',
            'system_labels',
            'leave_types',
            'bank_accounts',
            'bank_transfer_payments',
            'legal_pages',
            'contact_forms',
            'businesses',
        ];
    }
}
