<?php
namespace App\Services;

/**
 * Category Validation Service
 * Validates category assignments and provides suggestions for menu items
 */
class CategoryValidationService {
    
    /**
     * Normalize Turkish characters
     */
    private function normalizeTurkish($text) {
        $turkish = ['ç', 'ğ', 'ı', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü'];
        $english = ['c', 'g', 'i', 'o', 's', 'u', 'C', 'G', 'I', 'O', 'S', 'U'];
        return str_replace($turkish, $english, mb_strtoupper($text, 'UTF-8'));
    }
    
    /**
     * Find suggested category for a menu item based on its name
     * @param string $itemName Menu item name
     * @param array $allCategories All available categories
     * @return array|null Suggested category with confidence score
     */
    public function suggestCategoryForItem($itemName, $allCategories) {
        $itemNameUpper = mb_strtoupper(trim($itemName), 'UTF-8');
        $itemNameNormalized = $this->normalizeTurkish($itemNameUpper);
        
        // Category patterns (order matters - more specific first)
        $patterns = [
            // BİTKİ ÇAYLARI (check before SICAK İÇECEKLER - more specific)
            ['pattern' => ['ADAÇAYI', 'MELİSA', 'IHLAMUR', 'IHHLAMUR', 'NANE-LIMON', 'NANE LİMON', 'NANE-LİMON', 'PAPATYA'], 'category' => 'BİTKİ ÇAYLARI'],
            
            // SOĞUK KAHVELER (check before KAHVE ÇEŞİTLERİ - more specific)
            ['pattern' => ['BUZLU LATTE', 'BUZLU AMERICANO', 'BUZLU ÇİKOLATA', 'BUZLU ÇIKOLATA', 'BUZLU MOCHA'], 'category' => 'SOĞUK KAHVELER'],
            
            // SICAK İÇECEKLER
            ['pattern' => ['ÇAY', 'FİNCAN ÇAY', 'TÜRK KAHVESİ', 'SAHLEP', 'SICAK ÇİKOLATA', 'SICAK ÇIKOLATA', 'ORALET', 'KUŞBURNU', 'KİVİ', 'KAKAO', 'MUZ'], 'category' => 'SICAK İÇECEKLER'],
            
            // KAHVE ÇEŞİTLERİ
            ['pattern' => ['ESPRESSO', 'DOUBLE ESPRESSO', 'SÜTLÜ KAHVE', 'CAFFE LATTE', 'MOCHA', 'FLAT WHITE', 'LUNGO', 'RISTRETTO', 'AMERICANO', 'CAPPUCCINO', 'ESPRESSO MACCHIATO'], 'category' => 'KAHVE ÇEŞİTLERİ'],
            
            // SOĞUK İÇECEKLER
            ['pattern' => ['SU', 'SODA', 'KOLA', 'FANTA', 'SPRITE', 'FUSE TEA', 'AYRAN', 'CAPPY', 'SHWEPPES', 'SCHWEPPES', 'MEYVELİ SODALAR'], 'category' => 'SOĞUK İÇECEKLER'],
            
            // SALATALAR
            ['pattern' => ['ÇOBAN SALATA', 'TON BALIKLI SALATA', 'MEVSİM SALATA'], 'category' => 'SALATALAR'],
            
            // TATLILAR (specific patterns first)
            ['pattern' => ['ÇİKOLATALI DİLİM PASTA', 'ÇIKOLATALI DİLİM PASTA', 'FISTIK DÜNYASI RÜYASI', 'FRAMBUAZLI CHEESECAKE', 'LİMONLU CHEESECAKE', 'MOZAIK PASTA', 'TİRAMİSU', 'TİRAMİSÜ', 'ÇİKOLATALI BROWNIE', 'ÇIKOLATALI BROWNIE'], 'category' => 'TATLILAR'],
            
            // NARGİLE ÇEŞİTLERİ
            ['pattern' => ['NAKHLA', 'AL-FAKHER', 'ÖZEL KARIŞIMLAR', 'DARK SERİSİ', 'DARK SERİ', 'DİĞER'], 'category' => 'NARGİLE ÇEŞİTLERİ'],
            
            // TOSTLAR
            ['pattern' => ['KAŞARLI TOST', 'SUCUKLU TOST', 'KARIŞIK TOST', 'AYVALIK TOST', 'CADDE TOST', 'KAVURMALI KAŞARLI TOST', 'BEYAZ PEYNİRLİ TOST', 'KUMRU', 'PATSO'], 'category' => 'TOSTLAR'],
            
            // ATIŞTIRMALIKLAR
            ['pattern' => ['KUTU PATATES', 'PORSİYON PATATES', 'SOSİSLİ TABAĞI', 'SOĞAN HALKASI', 'PATATES KROKET', 'ÇITIR TAVUK TOPLARI', 'KOMBO TABAĞI'], 'category' => 'ATIŞTIRMALIKLAR'],
            
            // BURGERLER
            ['pattern' => ['HAMBURGER', 'CHEESBURGER', 'TAVUK BURGER', 'CADDE BURGER'], 'category' => 'BURGERLER'],
            
            // PIZZA
            ['pattern' => ['NAPOLİTEN PİZZA', 'MARGARITA PİZZA', 'VEJETARYEN PİZZA', 'KARIŞIK PİZZA', 'TON BALIKLI PİZZA', 'ACILI KÖFTELİ PİZZA', 'TURKİSH PİZZA', 'MUHTEŞEM ÜÇLÜ PİZZA', 'CADDE PIZZA', 'ŞEFİN SPESİYALİ'], 'category' => 'PIZZA'],
            
            // IZGARALAR & TAVUK SPESİYAL
            ['pattern' => ['TEKİRDAĞ KÖFTE', 'KASAP KÖFTE', 'TAVUK ŞİNİTZEL', 'TAVUK GORDON BLUE', 'TAVUK NUGGET'], 'category' => 'IZGARALAR & TAVUK SPESİYAL'],
        ];
        
        // Try exact match first
        foreach ($patterns as $patternGroup) {
            foreach ($patternGroup['pattern'] as $pattern) {
                $patternUpper = mb_strtoupper($pattern, 'UTF-8');
                $patternNormalized = $this->normalizeTurkish($patternUpper);
                
                // Exact match
                if ($itemNameUpper === $patternUpper || $itemNameNormalized === $patternNormalized) {
                    return $this->findCategoryByName($patternGroup['category'], $allCategories, 1.0);
                }
                
                // Contains match (but only for longer patterns)
                if (stripos($itemNameUpper, $patternUpper) !== false && strlen($pattern) > 5) {
                    return $this->findCategoryByName($patternGroup['category'], $allCategories, 0.8);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Find category by name in categories array
     */
    private function findCategoryByName($categoryName, $allCategories, $confidence) {
        $categoryNameLower = mb_strtolower($categoryName, 'UTF-8');
        
        foreach ($allCategories as $cat) {
            $catNameLower = mb_strtolower(trim($cat['name']), 'UTF-8');
            if ($catNameLower === $categoryNameLower) {
                return [
                    'category_id' => $cat['category_id'],
                    'category_name' => $cat['name'],
                    'confidence' => $confidence
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Validate category assignment for a menu item
     * @param string $itemName Menu item name
     * @param string $categoryId Current category ID
     * @param array $allCategories All available categories
     * @return array Validation result with suggested category if wrong
     */
    public function validateCategoryAssignment($itemName, $categoryId, $allCategories) {
        $suggestion = $this->suggestCategoryForItem($itemName, $allCategories);
        
        if ($suggestion && $suggestion['category_id'] !== $categoryId) {
            return [
                'valid' => false,
                'current_category_id' => $categoryId,
                'suggested_category_id' => $suggestion['category_id'],
                'suggested_category_name' => $suggestion['category_name'],
                'confidence' => $suggestion['confidence']
            ];
        }
        
        return ['valid' => true];
    }
}
