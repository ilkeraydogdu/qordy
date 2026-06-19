<?php
namespace App\Services;

use App\Repositories\StockCategoryRepository;

/**
 * Business logic around the materialized-path category tree:
 *  - CRUD with parent validation and cycle prevention
 *  - Automatic path/depth maintenance on create & move
 *  - Uniqueness check per (tenant, parent, name) because the DB unique
 *    key doesn't help once NULL parent_id is involved (MySQL treats NULLs
 *    as distinct in UNIQUE).
 */
class StockCategoryService
{
    /** @var StockCategoryRepository */
    private $repo;

    public function __construct(StockCategoryRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getTree(bool $includeInactive = false): array
    {
        return $this->repo->findTree($includeInactive);
    }

    public function getList(bool $includeInactive = false): array
    {
        return $this->repo->listAll($includeInactive);
    }

    public function get(string $categoryId): ?array
    {
        return $this->repo->findById($categoryId);
    }

    /**
     * Create a new category under the given parent. Returns the new id or
     * throws on validation errors.
     *
     * @throws \InvalidArgumentException when the name collides with a
     *         sibling or the parent is invalid.
     */
    public function create(array $input): string
    {
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Kategori adı zorunludur.');
        }
        $parentId = isset($input['parent_id']) && $input['parent_id'] !== '' ? (string)$input['parent_id'] : null;
        $parent = null;
        if ($parentId !== null) {
            $parent = $this->repo->findById($parentId);
            if (!$parent) {
                throw new \InvalidArgumentException('Üst kategori bulunamadı.');
            }
        }

        if ($this->repo->findByParentAndName($parentId, $name) !== null) {
            throw new \InvalidArgumentException('Aynı seviyede bu isimde bir kategori zaten var.');
        }

        $depth = $parent ? ((int)($parent['depth'] ?? 0) + 1) : 0;
        $parentPath = $parent ? (string)($parent['path'] ?? $parent['category_id']) : '';
        $row = [
            'name'       => $name,
            'parent_id'  => $parentId,
            'slug'       => $this->slugify($name),
            'depth'      => $depth,
            'sort_order' => (int)($input['sort_order'] ?? 0),
            'icon'       => isset($input['icon']) ? (string)$input['icon'] : null,
            'is_active'  => isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1,
            'path'       => '', // fill in after id is assigned
        ];

        $id = $this->repo->createCategory($row);
        if ($id === false) {
            throw new \RuntimeException('Kategori kaydedilemedi.');
        }
        $path = $parentPath === '' ? $id : ($parentPath . '/' . $id);
        $this->repo->updateCategory($id, ['path' => $path, 'depth' => $depth]);
        return $id;
    }

    /**
     * Update name/sort_order/icon/is_active. Parent changes require
     * moveNode() so we reject them here to keep update() simple.
     *
     * @throws \InvalidArgumentException
     */
    public function update(string $categoryId, array $input): bool
    {
        $current = $this->repo->findById($categoryId);
        if (!$current) {
            throw new \InvalidArgumentException('Kategori bulunamadı.');
        }
        $patch = [];
        if (array_key_exists('name', $input)) {
            $name = trim((string)$input['name']);
            if ($name === '') {
                throw new \InvalidArgumentException('Kategori adı boş olamaz.');
            }
            $sibling = $this->repo->findByParentAndName(
                isset($current['parent_id']) ? (string)$current['parent_id'] : null,
                $name
            );
            if ($sibling && $sibling['category_id'] !== $categoryId) {
                throw new \InvalidArgumentException('Aynı seviyede bu isimde bir kategori zaten var.');
            }
            $patch['name'] = $name;
            $patch['slug'] = $this->slugify($name);
        }
        foreach (['sort_order', 'icon', 'is_active'] as $k) {
            if (array_key_exists($k, $input)) {
                $patch[$k] = $k === 'sort_order' ? (int)$input[$k]
                          : ($k === 'is_active' ? (int)(bool)$input[$k]
                          : (string)$input[$k]);
            }
        }
        if (empty($patch)) {
            return true;
        }
        return $this->repo->updateCategory($categoryId, $patch);
    }

    /**
     * Move a subtree under a new parent (null = move to root). Rebuilds
     * path+depth for the whole subtree atomically.
     *
     * @throws \InvalidArgumentException on cycle or missing target.
     */
    public function moveNode(string $categoryId, ?string $newParentId): bool
    {
        $node = $this->repo->findById($categoryId);
        if (!$node) {
            throw new \InvalidArgumentException('Kategori bulunamadı.');
        }
        $parent = null;
        if ($newParentId !== null) {
            if ($newParentId === $categoryId) {
                throw new \InvalidArgumentException('Kategori kendisinin altına alınamaz.');
            }
            $parent = $this->repo->findById($newParentId);
            if (!$parent) {
                throw new \InvalidArgumentException('Hedef üst kategori bulunamadı.');
            }
            // Prevent cycles: new parent must not be a descendant of $node.
            $descIds = array_map(static fn ($r) => (string)$r['category_id'], $this->repo->descendantsOf($categoryId));
            if (in_array($newParentId, $descIds, true)) {
                throw new \InvalidArgumentException('Kategori kendi alt kategorilerinden birine taşınamaz.');
            }
        }

        $parentPath  = $parent ? (string)($parent['path'] ?? $parent['category_id']) : '';
        $parentDepth = $parent ? (int)($parent['depth'] ?? 0) : -1;
        $newPath     = $parentPath === '' ? $categoryId : ($parentPath . '/' . $categoryId);
        $newDepth    = $parentDepth + 1;

        $this->repo->updateCategory($categoryId, [
            'parent_id' => $newParentId,
            'path'      => $newPath,
            'depth'     => $newDepth,
        ]);

        $oldPath = (string)($node['path'] ?? $categoryId);
        $descendants = $this->repo->descendantsOf($categoryId);
        $updates = [];
        foreach ($descendants as $d) {
            $dPath = (string)($d['path'] ?? '');
            if ($dPath === '' || strpos($dPath, $oldPath . '/') !== 0) {
                continue;
            }
            $suffix = substr($dPath, strlen($oldPath));
            $updates[] = [
                'id'    => (string)$d['category_id'],
                'path'  => $newPath . $suffix,
                'depth' => substr_count($newPath . $suffix, '/'),
            ];
        }
        if (!empty($updates)) {
            $this->repo->bulkUpdatePaths($updates);
        }
        return true;
    }

    /**
     * Delete a category. Children must be empty / reassigned first — the
     * caller gets an exception otherwise so the UI can prompt the user.
     */
    public function delete(string $categoryId): bool
    {
        $descendants = $this->repo->descendantsOf($categoryId);
        if (!empty($descendants)) {
            throw new \InvalidArgumentException('Alt kategorileri olan bir kategori silinemez. Önce alt kategorileri taşıyın veya silin.');
        }
        return $this->repo->delete($categoryId);
    }

    /**
     * ASCII-only slug. Not localized — only used for URL helpers / search.
     */
    private function slugify(string $text): string
    {
        $map = [
            'ç'=>'c','Ç'=>'c','ğ'=>'g','Ğ'=>'g','ı'=>'i','İ'=>'i','ö'=>'o','Ö'=>'o',
            'ş'=>'s','Ş'=>'s','ü'=>'u','Ü'=>'u',
        ];
        $text = strtr($text, $map);
        $text = preg_replace('/[^A-Za-z0-9]+/', '-', $text) ?? '';
        $text = trim(strtolower($text), '-');
        return $text === '' ? 'kategori' : $text;
    }
}
