<?php
namespace App\Services;

/**
 * Search Service - Centralized Search System
 * Provides reusable search functionality across the application
 */
class SearchService {
    
    /**
     * Search in array by multiple fields
     * @param array $items - Items to search
     * @param string $query - Search query
     * @param array $fields - Fields to search in
     * @param bool $caseSensitive - Case sensitive search
     * @return array
     */
    public function search($items, $query, $fields = [], $caseSensitive = false) {
        if (empty($query) || empty($items)) {
            return $items;
        }
        
        if (empty($fields)) {
            // Search in all string fields
            $fields = $this->getSearchableFields($items);
        }
        
        $query = $caseSensitive ? $query : strtolower($query);
        
        return array_filter($items, function($item) use ($query, $fields, $caseSensitive) {
            foreach ($fields as $field) {
                $value = $item[$field] ?? '';
                $searchValue = $caseSensitive ? $value : strtolower($value);
                
                if (strpos($searchValue, $query) !== false) {
                    return true;
                }
            }
            
            return false;
        });
    }
    
    /**
     * Advanced search with multiple criteria
     * @param array $items
     * @param array $criteria - ['field' => 'query', 'field2' => 'query2']
     * @return array
     */
    public function advancedSearch($items, $criteria) {
        if (empty($criteria) || empty($items)) {
            return $items;
        }
        
        $results = $items;
        
        foreach ($criteria as $field => $query) {
            if (empty($query)) {
                continue;
            }
            
            $results = $this->search($results, $query, [$field]);
        }
        
        return array_values($results);
    }
    
    /**
     * Get searchable fields from items
     * @param array $items
     * @return array
     */
    private function getSearchableFields($items) {
        if (empty($items)) {
            return [];
        }
        
        $firstItem = reset($items);
        $fields = [];
        
        foreach (array_keys($firstItem) as $key) {
            // Skip non-string fields
            if (is_string($firstItem[$key]) || is_numeric($firstItem[$key])) {
                $fields[] = $key;
            }
        }
        
        return $fields;
    }
    
    /**
     * Highlight search terms in text
     * @param string $text
     * @param string $query
     * @param string $highlightClass
     * @return string
     */
    public function highlight($text, $query, $highlightClass = 'bg-yellow-200') {
        if (empty($query)) {
            return $text;
        }
        
        $pattern = '/' . preg_quote($query, '/') . '/i';
        return preg_replace($pattern, '<mark class="' . $highlightClass . '">$0</mark>', $text);
    }
}

