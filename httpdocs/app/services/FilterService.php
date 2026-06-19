<?php
namespace App\Services;

/**
 * Filter Service - Centralized Filtering System
 * Provides reusable filtering functionality across the application
 */
class FilterService {
    
    /**
     * Filter array by multiple criteria
     * @param array $items - Items to filter
     * @param array $filters - Filter criteria ['field' => 'value', 'field2' => ['value1', 'value2']]
     * @return array
     */
    public function filter($items, $filters) {
        if (empty($filters) || empty($items)) {
            return $items;
        }
        
        $filtered = $items;
        
        foreach ($filters as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            
            $filtered = array_filter($filtered, function($item) use ($field, $value) {
                $itemValue = $item[$field] ?? null;
                
                // Handle array values (multiple selections)
                if (is_array($value)) {
                    return in_array($itemValue, $value);
                }
                
                // Handle exact match
                return $itemValue == $value;
            });
        }
        
        return array_values($filtered);
    }
    
    /**
     * Filter by date range
     * @param array $items
     * @param string $dateField
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function filterByDateRange($items, $dateField, $startDate, $endDate) {
        if (empty($items)) {
            return [];
        }
        
        return array_filter($items, function($item) use ($dateField, $startDate, $endDate) {
            $itemDate = $item[$dateField] ?? null;
            if (!$itemDate) {
                return false;
            }
            
            $itemTimestamp = is_numeric($itemDate) ? $itemDate : strtotime($itemDate);
            $startTimestamp = strtotime($startDate);
            $endTimestamp = strtotime($endDate . ' 23:59:59');
            
            return $itemTimestamp >= $startTimestamp && $itemTimestamp <= $endTimestamp;
        });
    }
    
    /**
     * Filter by status
     * @param array $items
     * @param string $statusField
     * @param string|array $statuses
     * @return array
     */
    public function filterByStatus($items, $statusField, $statuses) {
        if (empty($items)) {
            return [];
        }
        
        if (!is_array($statuses)) {
            $statuses = [$statuses];
        }
        
        return array_filter($items, function($item) use ($statusField, $statuses) {
            $itemStatus = $item[$statusField] ?? null;
            return in_array($itemStatus, $statuses);
        });
    }
    
    /**
     * Sort items
     * @param array $items
     * @param string $sortField
     * @param string $direction - 'asc' or 'desc'
     * @return array
     */
    public function sort($items, $sortField, $direction = 'asc') {
        if (empty($items)) {
            return [];
        }
        
        usort($items, function($a, $b) use ($sortField, $direction) {
            $aValue = $a[$sortField] ?? null;
            $bValue = $b[$sortField] ?? null;
            
            if ($direction === 'desc') {
                return $bValue <=> $aValue;
            }
            
            return $aValue <=> $bValue;
        });
        
        return $items;
    }
    
    /**
     * Paginate items
     * @param array $items
     * @param int $page
     * @param int $perPage
     * @return array - ['items' => [], 'total' => int, 'page' => int, 'per_page' => int, 'total_pages' => int]
     */
    public function paginate($items, $page = 1, $perPage = 10) {
        $total = count($items);
        $totalPages = ceil($total / $perPage);
        $page = max(1, min($page, $totalPages));
        
        $offset = ($page - 1) * $perPage;
        $paginatedItems = array_slice($items, $offset, $perPage);
        
        return [
            'items' => $paginatedItems,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages
        ];
    }
}

