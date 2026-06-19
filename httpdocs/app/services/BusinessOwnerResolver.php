<?php
namespace App\Services;

/**
 * BusinessOwnerResolver
 *
 * Single source of truth for "who is the owner of a business?". Previously
 * the superadmin role-change endpoint and the subscription activation
 * service each ran their own bulk UPDATE against `users` filtered only by
 * `tenant_id`, which rewrote the role of every staff member in the
 * business. This resolver returns a single `user_id` so callers can scope
 * updates to the owner only.
 *
 * Resolution order (first non-empty wins):
 *   1. `customers.owner_user_id` (if the column exists)
 *   2. Oldest `users` row whose `name` looks like an email
 *      (registration flow stores the email in `name`)
 *   3. Oldest `users` row linked to the tenant by `tenant_id`
 */
class BusinessOwnerResolver
{
    /** @var \PDO */
    private $db;

    /** @var bool|null cached DbSchema::hasColumn('customers', 'owner_user_id') result */
    private $hasOwnerUserIdColumn = null;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Return the user_id of the business owner, or null if the business
     * has no linked users at all.
     */
    public function resolve(string $customerId): ?string
    {
        $customerId = trim($customerId);
        if ($customerId === '') {
            return null;
        }

        // 1) customers.owner_user_id (preferred when the schema has it)
        if ($this->customersHasOwnerColumn()) {
            try {
                $stmt = $this->db->prepare(
                    "SELECT owner_user_id FROM customers WHERE customer_id = ? LIMIT 1"
                );
                $stmt->execute([$customerId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $candidate = $row['owner_user_id'] ?? null;
                if (!empty($candidate) && $this->userExistsInTenant($candidate, $customerId)) {
                    return (string)$candidate;
                }
            } catch (\Throwable $e) {
                // fall through to heuristic lookups
            }
        }

        // 2) User whose `name` contains '@' (email stored as name at register time)
        try {
            $stmt = $this->db->prepare(
                "SELECT user_id FROM users
                 WHERE tenant_id = ? AND name LIKE '%@%'
                 ORDER BY created_at ASC, id ASC LIMIT 1"
            );
            $stmt->execute([$customerId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!empty($row['user_id'])) {
                return (string)$row['user_id'];
            }
        } catch (\Throwable $e) {
            // fall through
        }

        // 3) Oldest user tied to the tenant
        try {
            $stmt = $this->db->prepare(
                "SELECT user_id FROM users
                 WHERE tenant_id = ?
                 ORDER BY created_at ASC, id ASC LIMIT 1"
            );
            $stmt->execute([$customerId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return !empty($row['user_id']) ? (string)$row['user_id'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function customersHasOwnerColumn(): bool
    {
        if ($this->hasOwnerUserIdColumn !== null) {
            return $this->hasOwnerUserIdColumn;
        }
        $this->hasOwnerUserIdColumn = \App\Core\DbSchema::hasColumn('customers', 'owner_user_id');
        return $this->hasOwnerUserIdColumn;
    }

    private function userExistsInTenant(string $userId, string $customerId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM users WHERE user_id = ? AND tenant_id = ? LIMIT 1"
            );
            $stmt->execute([$userId, $customerId]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
