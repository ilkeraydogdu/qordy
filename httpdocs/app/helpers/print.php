<?php
/**
 * Print Helper Functions
 * 
 * Helper functions for printing operations
 */

if (!function_exists('printReceipt')) {
    /**
     * Trigger JavaScript print dialog
     * 
     * @param string $receiptId Receipt ID
     * @return string JavaScript code
     */
    function printReceipt(string $receiptId): string {
        $printUrl = (defined('BASE_URL') ? BASE_URL : '') . '/receipt/' . $receiptId . '/print';
        return "window.open('{$printUrl}', '_blank').print();";
    }
}

if (!function_exists('getPrintCSS')) {
    /**
     * Get CSS for print media
     * 
     * @param string $type Print type: 'thermal' or 'a4'
     * @return string CSS code
     */
    function getPrintCSS(string $type = 'thermal'): string {
        if ($type === 'thermal') {
            return "
            @media print {
                @page {
                    size: 80mm auto;
                    margin: 0;
                }
                body {
                    width: 80mm;
                    margin: 0;
                    padding: 10mm 5mm;
                    font-family: 'Courier New', monospace;
                    font-size: 12px;
                    line-height: 1.4;
                }
                .no-print {
                    display: none !important;
                }
                * {
                    box-sizing: border-box;
                }
            }
            ";
        } else {
            return "
            @media print {
                @page {
                    size: A4;
                    margin: 15mm;
                }
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                }
                .no-print {
                    display: none !important;
                }
                .page-break {
                    page-break-after: always;
                }
            }
            ";
        }
    }
}

if (!function_exists('detectPrinterType')) {
    /**
     * Detect printer type from user agent or settings
     * 
     * @return string 'thermal' or 'a4'
     */
    function detectPrinterType(): string {
        // Check if thermal printer is set in settings
        $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $settings = $settingsService->getSettings();
        
        if (isset($settings['printer_type']) && $settings['printer_type'] === 'thermal') {
            return 'thermal';
        }
        
        // Default to A4
        return 'a4';
    }
}

if (!function_exists('getReceiptBarcode')) {
    /**
     * Generate barcode data for receipt number
     * 
     * @param string $receiptNumber Receipt number
     * @return string Barcode data (for barcode library)
     */
    function getReceiptBarcode(string $receiptNumber): string {
        // Remove dashes for barcode
        return str_replace('-', '', $receiptNumber);
    }
}

