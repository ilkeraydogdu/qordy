<?php
namespace App\Services;

use App\Repositories\FinanceCategoryRepository;

/**
 * FinanceCategoryService
 *
 * Thin domain wrapper around FinanceCategoryRepository. Handles id generation,
 * duplicate detection, and the "delete but don't orphan historical rows"
 * workflow (archive instead of hard delete when rows reference it).
 */
class FinanceCategoryService {
    public const TYPE_SUPPLIER = 'SUPPLIER';
    public const TYPE_EXPENSE  = 'EXPENSE';

    private FinanceCategoryRepository $repo;

    public function __construct(FinanceCategoryRepository $repo) {
        $this->repo = $repo;
    }

    public function list(?string $type = null): array {
        $items = $this->repo->listByType($type);
        // Enrich with usage count so the UI can disable delete for referenced rows.
        foreach ($items as &$row) {
            $row['usage_count'] = $this->repo->usageCount($row['type'], $row['label']);
        }
        return $items;
    }

    public function create(string $type, string $label, ?string $color = null, ?string $icon = null): array {
        $type = strtoupper(trim($type));
        if (!in_array($type, [self::TYPE_SUPPLIER, self::TYPE_EXPENSE], true)) {
            throw new \InvalidArgumentException('Geçersiz kategori tipi.');
        }
        $label = trim($label);
        if ($label === '') {
            throw new \InvalidArgumentException('Kategori adı boş olamaz.');
        }
        if (mb_strlen($label) > 120) {
            throw new \InvalidArgumentException('Kategori adı 120 karakterden uzun olamaz.');
        }
        if ($this->repo->findByLabel($type, $label)) {
            throw new \RuntimeException('Bu isimde bir kategori zaten var.');
        }

        $categoryId = 'fc_' . bin2hex(random_bytes(8));
        $data = [
            'category_id' => $categoryId,
            'type'        => $type,
            'label'       => $label,
            'color'       => $color ?: null,
            'icon'        => $icon ?: null,
            'sort_order'  => 0,
            'is_archived' => 0,
        ];
        $ok = $this->repo->create($data);
        if (!$ok) {
            throw new \RuntimeException('Kategori kaydedilemedi.');
        }
        $created = $this->repo->getById($categoryId);
        if (is_array($created)) {
            $created['usage_count'] = 0;
        }
        return is_array($created) ? $created : $data;
    }

    public function rename(string $categoryId, string $newLabel): bool {
        $newLabel = trim($newLabel);
        if ($newLabel === '') {
            throw new \InvalidArgumentException('Kategori adı boş olamaz.');
        }
        $current = $this->repo->getById($categoryId);
        if (!$current) {
            throw new \RuntimeException('Kategori bulunamadı.');
        }
        $existing = $this->repo->findByLabel($current['type'], $newLabel);
        if ($existing && ($existing['category_id'] ?? null) !== $categoryId) {
            throw new \RuntimeException('Bu isimde başka bir kategori var.');
        }
        return $this->repo->renameAndPropagate($categoryId, $newLabel, true);
    }

    /**
     * Hard delete if no supplier/expense references the label, otherwise
     * archive so historical rows still render the (now-read-only) label.
     */
    public function delete(string $categoryId): array {
        $current = $this->repo->getById($categoryId);
        if (!$current) {
            throw new \RuntimeException('Kategori bulunamadı.');
        }
        $usage = $this->repo->usageCount($current['type'], $current['label']);
        if ($usage > 0) {
            $this->repo->update($categoryId, ['is_archived' => 1]);
            return ['archived' => true, 'usage_count' => $usage];
        }
        $this->repo->delete($categoryId);
        return ['archived' => false, 'usage_count' => 0];
    }
}
