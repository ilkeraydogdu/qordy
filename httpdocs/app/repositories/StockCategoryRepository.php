<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * Repository for `stock_categories` — materialized-path hierarchy so an
 * arbitrary depth is supported without recursive CTEs. Every query is
 * tenant-scoped via BaseRepository::addTenantToWhere.
 */
class StockCategoryRepository extends BaseRepository
{
    protected $table = 'stock_categories';
    protected $primaryKey = 'category_id';

    public function __construct($database)
    {
        parent::__construct($database);
    }

    /**
     * Flat list of all active categories for the current tenant ordered for
     * stable tree rendering (parent before child, sort_order then name).
     * @return array<int, array<string, mixed>>
     */
    public function listAll(bool $includeInactive = false): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        if (!$includeInactive) {
            $sql .= " WHERE is_active = 1";
        }
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY depth ASC, sort_order ASC, name ASC";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Build a nested tree from the flat list. Children are stored under the
     * `children` key; leaf nodes have an empty array.
     * @return array<int, array<string, mixed>>
     */
    public function findTree(bool $includeInactive = false): array
    {
        $rows = $this->listAll($includeInactive);
        if (empty($rows)) {
            return [];
        }
        $byParent = [];
        foreach ($rows as $row) {
            $parent = $row['parent_id'] ?? null;
            $byParent[$parent ?? '__root__'][] = $row;
        }
        $build = function ($parentKey) use (&$build, &$byParent) {
            $out = [];
            foreach ($byParent[$parentKey] ?? [] as $node) {
                $node['children'] = $build($node['category_id']);
                $out[] = $node;
            }
            return $out;
        };
        return $build('__root__');
    }

    public function findById(string $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $params = ['id' => $id];
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " LIMIT 1";
        return $this->fetchOne($sql, $params);
    }

    /**
     * Collect every category between the given one and the root. The
     * returned list is root-first so paths can be assembled directly.
     * @return array<int, array<string, mixed>>
     */
    public function ancestorsOf(string $categoryId): array
    {
        $chain = [];
        $current = $this->findById($categoryId);
        $safety = 0;
        while ($current && $safety++ < 50) {
            array_unshift($chain, $current);
            $parentId = $current['parent_id'] ?? null;
            if (!$parentId) {
                break;
            }
            $current = $this->findById((string)$parentId);
        }
        return $chain;
    }

    /**
     * Return every category that has the given node as an ancestor, based on
     * the materialized path prefix. Matches are anchored at the leading "{id}"
     * segment so we never pick up partial name collisions.
     * @return array<int, array<string, mixed>>
     */
    public function descendantsOf(string $categoryId): array
    {
        $root = $this->findById($categoryId);
        if (!$root) {
            return [];
        }
        $path = (string)($root['path'] ?? '');
        if ($path === '') {
            return [];
        }
        $sql = "SELECT * FROM {$this->table} WHERE path LIKE :path AND {$this->primaryKey} <> :self";
        $params = ['path' => $path . '/%', 'self' => $categoryId];
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY depth ASC, sort_order ASC, name ASC";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Find a direct child by (tenant, parent, name). Case-insensitive.
     */
    public function findByParentAndName(?string $parentId, string $name): ?array
    {
        if ($parentId === null) {
            $sql = "SELECT * FROM {$this->table} WHERE parent_id IS NULL AND LOWER(name) = LOWER(:name)";
            $params = ['name' => $name];
        } else {
            $sql = "SELECT * FROM {$this->table} WHERE parent_id = :pid AND LOWER(name) = LOWER(:name)";
            $params = ['pid' => $parentId, 'name' => $name];
        }
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " LIMIT 1";
        return $this->fetchOne($sql, $params);
    }

    public function createCategory(array $data): string|false
    {
        $id = $data['category_id'] ?? ('cat_' . bin2hex(random_bytes(10)));
        $data['category_id'] = $id;
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');
        return parent::create($data) ? $id : false;
    }

    public function updateCategory(string $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return parent::update($id, $data);
    }

    /**
     * Bulk update the materialized path column for a set of ids. Each row
     * is expected as ['id' => ..., 'path' => ..., 'depth' => ...]. Intended
     * for moveNode() rebuilds where many descendants need re-stamping.
     */
    public function bulkUpdatePaths(array $rows): void
    {
        if (empty($rows)) {
            return;
        }
        $sql = "UPDATE {$this->table} SET path = :path, depth = :depth, updated_at = :uat WHERE {$this->primaryKey} = :id";
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $r) {
            $this->execute($sql, [
                'path'  => $r['path'],
                'depth' => $r['depth'],
                'uat'   => $now,
                'id'    => $r['id'],
            ]);
        }
    }
}
