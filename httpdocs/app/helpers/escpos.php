<?php
/**
 * ESC/POS Helper Functions
 * Xprinter XP-Q805K (80mm thermal printer) için ESC/POS komut helper'ları
 */

/**
 * Code Page Constants for ESC/POS printers
 * Used with escpos_set_codepage() function
 */
if (!defined('ESCPOS_CP_PC437')) {
    define('ESCPOS_CP_PC437', 0);      // USA, Standard Europe
    define('ESCPOS_CP_KATAKANA', 1);   // Katakana
    define('ESCPOS_CP_PC850', 2);      // Multilingual
    define('ESCPOS_CP_PC860', 3);      // Portuguese
    define('ESCPOS_CP_PC863', 4);      // Canadian-French
    define('ESCPOS_CP_PC865', 5);      // Nordic
    define('ESCPOS_CP_WPC1252', 16);   // Western European (Windows)
    define('ESCPOS_CP_PC866', 17);     // Cyrillic #2
    define('ESCPOS_CP_PC852', 18);     // Latin 2
    define('ESCPOS_CP_PC858', 19);     // Euro
    define('ESCPOS_CP_THAI42', 20);    // Thai character code 42
    define('ESCPOS_CP_THAI11', 21);    // Thai character code 11
    define('ESCPOS_CP_THAI13', 22);    // Thai character code 13
    define('ESCPOS_CP_THAI14', 23);    // Thai character code 14
    define('ESCPOS_CP_THAI16', 24);    // Thai character code 16
    define('ESCPOS_CP_THAI17', 25);    // Thai character code 17
    define('ESCPOS_CP_THAI18', 26);    // Thai character code 18
    define('ESCPOS_CP_PC857', 9);      // Turkish (CP857) - IMPORTANT FOR TURKISH CHARACTERS
    define('ESCPOS_CP_WPC1254', 35);   // Turkish (Windows-1254)
}

if (!function_exists('escpos_init')) {
    /**
     * Initialize printer (ESC @)
     * @return string ESC/POS command
     */
    function escpos_init(): string {
        return "\x1B\x40";
    }
}

if (!function_exists('escpos_set_codepage')) {
    /**
     * Set character code page (ESC t n)
     * @param int $codepage Code page number (use ESCPOS_CP_* constants)
     * @return string ESC/POS command
     */
    function escpos_set_codepage(int $codepage = ESCPOS_CP_PC857): string {
        return "\x1B\x74" . chr($codepage);
    }
}

if (!function_exists('escpos_set_turkish')) {
    /**
     * Set Turkish code page (CP857)
     * This enables proper display of Turkish characters: ç, ğ, ı, ö, ş, ü, Ç, Ğ, İ, Ö, Ş, Ü
     * @return string ESC/POS command
     */
    function escpos_set_turkish(): string {
        return escpos_set_codepage(ESCPOS_CP_PC857);
    }
}

if (!function_exists('escpos_init_turkish')) {
    /**
     * Initialize printer with Turkish character set
     * Combines init and Turkish code page setting
     * @return string ESC/POS commands
     */
    function escpos_init_turkish(): string {
        return escpos_init() . escpos_set_turkish();
    }
}

if (!function_exists('convert_utf8_to_cp857')) {
    /**
     * Convert UTF-8 text to CP857 (Turkish) encoding
     * This is needed because thermal printers don't understand UTF-8
     * @param string $text UTF-8 encoded text
     * @return string CP857 encoded text
     */
    function convert_utf8_to_cp857(string $text): string {
        // Turkish character mapping from UTF-8 to CP857
        $turkishMap = [
            'ç' => "\x87",  // LATIN SMALL LETTER C WITH CEDILLA
            'Ç' => "\x80",  // LATIN CAPITAL LETTER C WITH CEDILLA
            'ğ' => "\xA7",  // LATIN SMALL LETTER G WITH BREVE
            'Ğ' => "\xA6",  // LATIN CAPITAL LETTER G WITH BREVE
            'ı' => "\x8D",  // LATIN SMALL LETTER DOTLESS I
            'İ' => "\x98",  // LATIN CAPITAL LETTER I WITH DOT ABOVE
            'ö' => "\x94",  // LATIN SMALL LETTER O WITH DIAERESIS
            'Ö' => "\x99",  // LATIN CAPITAL LETTER O WITH DIAERESIS
            'ş' => "\x9F",  // LATIN SMALL LETTER S WITH CEDILLA
            'Ş' => "\x9E",  // LATIN CAPITAL LETTER S WITH CEDILLA
            'ü' => "\x81",  // LATIN SMALL LETTER U WITH DIAERESIS
            'Ü' => "\x9A",  // LATIN CAPITAL LETTER U WITH DIAERESIS
            // Common currency and special characters
            '€' => "\xD5",  // Euro sign (if supported)
            '₺' => "TL",    // Turkish Lira (not in CP857, use TL)
        ];
        
        // First replace Turkish characters
        $result = str_replace(array_keys($turkishMap), array_values($turkishMap), $text);
        
        // Then convert remaining characters from UTF-8 to ISO-8859-9 (Latin-5)
        // which is compatible with CP857 for most characters
        $result = @iconv('UTF-8', 'CP857//TRANSLIT//IGNORE', $result);
        
        // If iconv fails, try manual conversion
        if ($result === false) {
            $result = str_replace(array_keys($turkishMap), array_values($turkishMap), $text);
            // Remove any remaining multi-byte characters
            $result = preg_replace('/[\x80-\xFF]{2,}/', '?', $result);
        }
        
        return $result;
    }
}

if (!function_exists('format_turkish_text_for_printer')) {
    /**
     * Format Turkish text for thermal printer
     * Converts UTF-8 to CP857 and applies ESC/POS formatting
     * @param string $text UTF-8 encoded Turkish text
     * @param int $maxWidth Maximum width in characters (default: 48 for 80mm)
     * @param string $align Alignment: 'left', 'center', 'right'
     * @return string Formatted text ready for printing
     */
    function format_turkish_text_for_printer(string $text, int $maxWidth = 48, string $align = 'left'): string {
        // Convert to CP857 first
        $convertedText = convert_utf8_to_cp857($text);
        
        // Then format for printer
        return format_text_for_xprinter($convertedText, $maxWidth, $align);
    }
}

if (!function_exists('escpos_align')) {
    /**
     * Set text alignment (ESC a)
     * @param string $align 'left', 'center', or 'right'
     * @return string ESC/POS command
     */
    function escpos_align(string $align = 'left'): string {
        $alignments = [
            'left' => "\x00",
            'center' => "\x01",
            'right' => "\x02"
        ];
        return "\x1B\x61" . ($alignments[$align] ?? "\x00");
    }
}

if (!function_exists('escpos_feed')) {
    /**
     * Line feed (ESC d)
     * @param int $lines Number of lines to feed
     * @return string ESC/POS command
     */
    function escpos_feed(int $lines = 1): string {
        return "\x1B\x64" . chr(min($lines, 255));
    }
}

if (!function_exists('escpos_font_size')) {
    /**
     * Set font size (GS !)
     * @param int $width Width multiplier (1-8)
     * @param int $height Height multiplier (1-8)
     * @return string ESC/POS command
     */
    function escpos_font_size(int $width = 1, int $height = 1): string {
        $width = max(1, min(8, $width));
        $height = max(1, min(8, $height));
        $value = (($width - 1) << 4) | ($height - 1);
        return "\x1D\x21" . chr($value);
    }
}

if (!function_exists('escpos_bold')) {
    /**
     * Set bold text (ESC E)
     * @param bool $enabled Enable or disable bold
     * @return string ESC/POS command
     */
    function escpos_bold(bool $enabled = true): string {
        return "\x1B\x45" . chr($enabled ? 1 : 0);
    }
}

if (!function_exists('escpos_cut')) {
    /**
     * Cut paper (GS v)
     * @param int $mode Cut mode (0 = full cut, 1 = partial cut)
     * @return string ESC/POS command
     */
    function escpos_cut(int $mode = 0): string {
        return "\x1D\x56" . chr($mode);
    }
}

if (!function_exists('escpos_underline')) {
    /**
     * Set underline (ESC -)
     * @param int $type 0 = none, 1 = single, 2 = double
     * @return string ESC/POS command
     */
    function escpos_underline(int $type = 0): string {
        return "\x1B\x2D" . chr($type);
    }
}

if (!function_exists('escpos_reverse')) {
    /**
     * Set reverse color (GS B)
     * @param bool $enabled Enable or disable reverse
     * @return string ESC/POS command
     */
    function escpos_reverse(bool $enabled = true): string {
        return "\x1D\x42" . chr($enabled ? 1 : 0);
    }
}

if (!function_exists('format_text_for_xprinter')) {
    /**
     * Format text for Xprinter XP-Q805K (80mm, 48 characters per line)
     * @param string $text Text to format
     * @param int $maxWidth Maximum width in characters (default: 48)
     * @param string $align Alignment: 'left', 'center', 'right'
     * @return string Formatted text with ESC/POS commands
     */
    function format_text_for_xprinter(string $text, int $maxWidth = 48, string $align = 'left'): string {
        $output = escpos_align($align);
        
        // Remove ESC/POS commands from text for width calculation
        $cleanText = preg_replace('/\x1B\[[0-9;]*m/', '', $text);
        $cleanText = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $cleanText);
        
        // Handle multi-byte characters (Turkish characters)
        $textLength = mb_strlen($cleanText, 'UTF-8');
        
        if ($textLength <= $maxWidth) {
            // Text fits, pad if needed
            if ($align === 'center') {
                $padding = ($maxWidth - $textLength) / 2;
                $output .= str_repeat(' ', floor($padding)) . $text . str_repeat(' ', ceil($padding));
            } elseif ($align === 'right') {
                $output .= str_repeat(' ', $maxWidth - $textLength) . $text;
            } else {
                $output .= $text . str_repeat(' ', $maxWidth - $textLength);
            }
        } else {
            // Text is too long, wrap it
            $words = explode(' ', $text);
            $currentLine = '';
            $lines = [];
            
            foreach ($words as $word) {
                $testLine = $currentLine . ($currentLine ? ' ' : '') . $word;
                $testLength = mb_strlen(preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $testLine), 'UTF-8');
                
                if ($testLength <= $maxWidth) {
                    $currentLine = $testLine;
                } else {
                    if ($currentLine) {
                        $lines[] = $currentLine;
                    }
                    $currentLine = $word;
                }
            }
            
            if ($currentLine) {
                $lines[] = $currentLine;
            }
            
            $output = '';
            foreach ($lines as $line) {
                $output .= escpos_align($align);
                $lineLength = mb_strlen(preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $line), 'UTF-8');
                if ($align === 'center') {
                    $padding = ($maxWidth - $lineLength) / 2;
                    $output .= str_repeat(' ', floor($padding)) . $line . str_repeat(' ', ceil($padding));
                } elseif ($align === 'right') {
                    $output .= str_repeat(' ', $maxWidth - $lineLength) . $line;
                } else {
                    $output .= $line . str_repeat(' ', $maxWidth - $lineLength);
                }
                $output .= "\n";
            }
            
            return rtrim($output, "\n");
        }
        
        return $output;
    }
}

if (!function_exists('create_separator_line')) {
    /**
     * Create separator line for thermal printer
     * @param int $width Width in characters (default: 48)
     * @param string $char Character to use (default: '-')
     * @return string Separator line
     */
    function create_separator_line(int $width = 48, string $char = '-'): string {
        return str_repeat($char, $width) . "\n";
    }
}

if (!function_exists('create_double_separator_line')) {
    /**
     * Create double separator line for thermal printer
     * @param int $width Width in characters (default: 48)
     * @return string Double separator line
     */
    function create_double_separator_line(int $width = 48): string {
        return str_repeat('=', $width) . "\n";
    }
}

