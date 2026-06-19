<?php
/**
 * Text Formatting Helper
 * Formats text for menu items (title case, etc.)
 */

if (!function_exists('formatMenuTitleCase')) {
    /**
     * Format text in title case for menu items
     * Handles special cases like "with", "and", "of" which should be lowercase
     * unless they are the first word
     * 
     * @param string $text Text to format
     * @return string Formatted text in title case
     */
    function formatMenuTitleCase(string $text): string {
        if (empty($text)) {
            return '';
        }
        
        // Words that should remain lowercase (unless first word)
        $lowercaseWords = [
            'with', 'and', 'or', 'of', 'in', 'on', 'at', 'to', 'for',
            'the', 'a', 'an', 'from', 'by', 'as', 'is', 'are', 'was', 'were'
        ];
        
        // Split into words
        $words = preg_split('/\s+/', trim($text));
        $formattedWords = [];
        
        foreach ($words as $index => $word) {
            // Remove any punctuation temporarily
            $cleanWord = preg_replace('/[^\p{L}\p{N}]/u', '', $word);
            $punctuation = preg_replace('/[\p{L}\p{N}]/u', '', $word);
            
            if (empty($cleanWord)) {
                $formattedWords[] = $word;
                continue;
            }
            
            // First word is always capitalized
            if ($index === 0) {
                $formatted = mb_strtoupper(mb_substr($cleanWord, 0, 1, 'UTF-8'), 'UTF-8') . 
                            mb_strtolower(mb_substr($cleanWord, 1, null, 'UTF-8'), 'UTF-8');
            } else {
                // Check if it's a lowercase word
                $lowerWord = mb_strtolower($cleanWord, 'UTF-8');
                if (in_array($lowerWord, $lowercaseWords, true)) {
                    $formatted = $lowerWord;
                } else {
                    // Capitalize first letter
                    $formatted = mb_strtoupper(mb_substr($cleanWord, 0, 1, 'UTF-8'), 'UTF-8') . 
                                mb_strtolower(mb_substr($cleanWord, 1, null, 'UTF-8'), 'UTF-8');
                }
            }
            
            // Restore punctuation
            $formattedWords[] = $formatted . $punctuation;
        }
        
        return implode(' ', $formattedWords);
    }
}

if (!function_exists('formatMenuText')) {
    /**
     * Format menu text (normalize and apply title case)
     * This is a convenience function that combines normalization and formatting
     * 
     * @param string $text Text to format
     * @param bool $applyTitleCase Whether to apply title case (default: true)
     * @return string Formatted text
     */
    function formatMenuText(string $text, bool $applyTitleCase = true): string {
        if (empty($text)) {
            return '';
        }
        
        // Trim and clean
        $formatted = trim($text);
        
        // Remove extra whitespace
        $formatted = preg_replace('/\s+/', ' ', $formatted);
        
        // Apply title case if requested
        if ($applyTitleCase) {
            $formatted = formatMenuTitleCase($formatted);
        }
        
        return $formatted;
    }
}

