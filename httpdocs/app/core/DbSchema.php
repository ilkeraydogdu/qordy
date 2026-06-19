<?php
namespace App\Core;

/**
 * Centralized database-schema introspection helper.
 *
 * Historically the codebase asked "does this table have column X?" in many
 * different ways:
 *   - Repositories had their own `hasColumn()` + `SHOW COLUMNS` cache.
 *   - Services hand-rolled closures doing `SHOW COLUMNS FROM x LIKE 'y'`.
 *   - `SubscriptionService` even used `ReflectionClass` to poke into a
 *     repository's protected `hasColumn()` method.
 *
 * Every one of those variants produced its own uncoordinated cache (or no
 * cache at all) and duplicated the same logic. This helper centralizes
 * schema introspection so every consumer shares one in-memory cache and a
 * single API.
 *
 * Usage:
 *   DbSchema::hasColumn('subscriptions', 'tenant_id');      // bool
 *   DbSchema::columns('orders');                            // string[]
 *   DbSchema::tableExists('queue_settings');                // bool
 *   DbSchema::pickTenantColumn('some_legacy_table');        // 'tenant_id'|'business_id'|'customer_id'|null
 *
 * The cache is request-scoped (static arrays). Schema changes within a
 * running request are vanishingly rare; migrations reset the process.
 */
final class DbSchema
{
    /** @var array<string, array<int, string>> */
    private static array $columnsByTable = [];

    /** @var array<string, array<string, array<string, mixed>>> */
    private static array $columnMetaByTable = [];

    /** @var array<string, bool> */
    private static array $tableExistsCache = [];

    /**
     * Return the list of column names for a table (lowercased-insensitive match).
     * Returns [] if the table does not exist or cannot be introspected.
     *
     * @return array<int, string>
     */
    public static function columns(string $table): array
    {
        if (isset(self::$columnsByTable[$table])) {
            return self::$columnsByTable[$table];
        }

        $db = self::db();
        if ($db === null) {
            return self::$columnsByTable[$table] = [];
        }

        try {
            $stmt = $db->query("SHOW COLUMNS FROM `{$table}`");
            if ($stmt === false) {
                return self::$columnsByTable[$table] = [];
            }
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $names = [];
            $meta  = [];
            foreach ($rows as $row) {
                $name = (string)($row['Field'] ?? $row['field'] ?? '');
                if ($name === '') {
                    continue;
                }
                $names[] = $name;
                $meta[$name] = $row;
            }
            self::$columnMetaByTable[$table] = $meta;
            return self::$columnsByTable[$table] = $names;
        } catch (\Throwable $e) {
            return self::$columnsByTable[$table] = [];
        }
    }

    /**
     * True when the given column exists on the given table.
     */
    public static function hasColumn(string $table, string $column): bool
    {
        return in_array($column, self::columns($table), true);
    }

    /**
     * Return the raw row from `SHOW COLUMNS` for a single column, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function columnMeta(string $table, string $column): ?array
    {
        if (!isset(self::$columnMetaByTable[$table])) {
            self::columns($table);
        }
        return self::$columnMetaByTable[$table][$column] ?? null;
    }

    /**
     * True when the given table exists in the current database.
     */
    public static function tableExists(string $table): bool
    {
        if (isset(self::$tableExistsCache[$table])) {
            return self::$tableExistsCache[$table];
        }
        $db = self::db();
        if ($db === null) {
            return self::$tableExistsCache[$table] = false;
        }
        try {
            $stmt = $db->prepare("SHOW TABLES LIKE :t");
            $stmt->execute(['t' => $table]);
            return self::$tableExistsCache[$table] = $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            return self::$tableExistsCache[$table] = false;
        }
    }

    /**
     * Return the canonical tenant column for a table, picking the preferred
     * name when multiple legacy columns co-exist.
     *
     * Preference order (canonical first): tenant_id, business_id, customer_id.
     */
    public static function pickTenantColumn(string $table): ?string
    {
        foreach (TenantResolver::TENANT_KEYS as $candidate) {
            if (self::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Filter an associative array down to keys that exist as columns on the
     * given table. Handy for "only pass schema-present fields to INSERT".
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function filterToColumns(string $table, array $data): array
    {
        $cols = self::columns($table);
        if (empty($cols)) {
            return $data;
        }
        $flip = array_flip($cols);
        return array_intersect_key($data, $flip);
    }

    /**
     * Forget a specific table's cached entry. Called by migrations after
     * ALTER TABLE so subsequent lookups reflect the new schema.
     */
    public static function forget(string $table): void
    {
        unset(
            self::$columnsByTable[$table],
            self::$columnMetaByTable[$table],
            self::$tableExistsCache[$table]
        );
    }

    /**
     * Forget everything. Useful in tests or after bulk migrations.
     */
    public static function reset(): void
    {
        self::$columnsByTable = [];
        self::$columnMetaByTable = [];
        self::$tableExistsCache = [];
    }

    private static function db(): ?\PDO
    {
        try {
            if (class_exists(DependencyFactory::class)) {
                $db = DependencyFactory::getDatabase();
                if ($db instanceof \PDO) {
                    return $db;
                }
            }
        } catch (\Throwable $e) {
            // Fall through.
        }
        return null;
    }
}
