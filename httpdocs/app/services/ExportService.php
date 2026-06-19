<?php
namespace App\Services;

/**
 * Export Service - Centralized Export Functionality
 * Handles CSV, Excel (XML format), and PDF exports
 * MC OOP architecture, no hardcode, no mock data
 */
class ExportService {
    
    /**
     * Export data to CSV format
     * @param array $data Array of arrays (rows)
     * @param array $headers Column headers
     * @param string $filename Filename without extension
     * @return void Sends file to browser
     */
    public function exportToCSV(array $data, array $headers, string $filename = 'export'): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8 (Excel compatibility)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write headers
        if (!empty($headers)) {
            fputcsv($output, $headers, ',', '"');
        }
        
        // Write data rows
        foreach ($data as $row) {
            // Ensure row is an array
            if (!is_array($row)) {
                $row = [$row];
            }
            fputcsv($output, $row, ',', '"');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export data to Excel XML format (SpreadsheetML)
     * Compatible with Excel 2003+ and modern Excel
     * @param array $data Array of arrays (rows)
     * @param array $headers Column headers
     * @param string $filename Filename without extension
     * @return void Sends file to browser
     */
    public function exportToExcel(array $data, array $headers, string $filename = 'export'): void {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        echo ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        echo ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        
        echo '<Worksheet ss:Name="Sheet1">' . "\n";
        echo '<Table>' . "\n";
        
        // Write headers
        if (!empty($headers)) {
            echo '<Row>' . "\n";
            foreach ($headers as $header) {
                echo '<Cell><Data ss:Type="String">' . htmlspecialchars($header, ENT_XML1, 'UTF-8') . '</Data></Cell>' . "\n";
            }
            echo '</Row>' . "\n";
        }
        
        // Write data rows
        foreach ($data as $row) {
            echo '<Row>' . "\n";
            
            // Ensure row is an array
            if (!is_array($row)) {
                $row = [$row];
            }
            
            foreach ($row as $cell) {
                // Determine cell type (String or Number)
                $cellValue = $cell ?? '';
                $cellType = is_numeric($cellValue) ? 'Number' : 'String';
                $cellContent = htmlspecialchars((string)$cellValue, ENT_XML1, 'UTF-8');
                
                echo '<Cell><Data ss:Type="' . $cellType . '">' . $cellContent . '</Data></Cell>' . "\n";
            }
            
            echo '</Row>' . "\n";
        }
        
        echo '</Table>' . "\n";
        echo '</Worksheet>' . "\n";
        echo '</Workbook>';
        
        exit;
    }
    
    /**
     * Export data to PDF format
     * Uses HTML output that can be printed to PDF or converted
     * @param array $data Array of arrays (rows)
     * @param array $headers Column headers
     * @param string $filename Filename without extension
     * @param string $title Document title
     * @return void Sends HTML to browser (can be printed to PDF)
     */
    public function exportToPDF(array $data, array $headers, string $filename = 'export', string $title = 'Export'): void {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $filename . '.html"');
        
        echo '<!DOCTYPE html>' . "\n";
        echo '<html lang="tr">' . "\n";
        echo '<head>' . "\n";
        echo '<meta charset="UTF-8">' . "\n";
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        echo '<title>' . htmlspecialchars($title) . '</title>' . "\n";
        echo '<style>' . "\n";
        echo '@media print { @page { size: A4 landscape; margin: 1cm; } }' . "\n";
        echo 'body { font-family: Arial, sans-serif; margin: 20px; }' . "\n";
        echo 'h1 { font-size: 24px; margin-bottom: 20px; }' . "\n";
        echo 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }' . "\n";
        echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }' . "\n";
        echo 'th { background-color: #f2f2f2; font-weight: bold; }' . "\n";
        echo 'tr:nth-child(even) { background-color: #f9f9f9; }' . "\n";
        echo '.print-button { margin: 20px 0; }' . "\n";
        echo '.print-button button { padding: 10px 20px; font-size: 16px; cursor: pointer; }' . "\n";
        echo '</style>' . "\n";
        echo '</head>' . "\n";
        echo '<body>' . "\n";
        
        echo '<h1>' . htmlspecialchars($title) . '</h1>' . "\n";
        echo '<div class="print-button">' . "\n";
        echo '<button onclick="window.print()">Yazdır / PDF Olarak Kaydet</button>' . "\n";
        echo '</div>' . "\n";
        
        echo '<table>' . "\n";
        
        // Write headers
        if (!empty($headers)) {
            echo '<thead><tr>' . "\n";
            foreach ($headers as $header) {
                echo '<th>' . htmlspecialchars($header) . '</th>' . "\n";
            }
            echo '</tr></thead>' . "\n";
        }
        
        // Write data rows
        echo '<tbody>' . "\n";
        foreach ($data as $row) {
            echo '<tr>' . "\n";
            
            // Ensure row is an array
            if (!is_array($row)) {
                $row = [$row];
            }
            
            foreach ($row as $cell) {
                echo '<td>' . htmlspecialchars((string)($cell ?? '')) . '</td>' . "\n";
            }
            
            echo '</tr>' . "\n";
        }
        echo '</tbody>' . "\n";
        
        echo '</table>' . "\n";
        echo '</body>' . "\n";
        echo '</html>';
        
        exit;
    }
    
    /**
     * Export orders to specified format
     * @param array $orders Array of order data
     * @param string $format Format: 'csv', 'excel', or 'pdf'
     * @param array $filters Optional filters info for filename
     * @return void Sends file to browser
     */
    public function exportOrders(array $orders, string $format = 'csv', array $filters = []): void {
        // Define headers
        $headers = [
            'Sipariş ID',
            'Masa',
            'Müşteri',
            'Durum',
            'Tarih',
            'Tutar (₺)',
            'Ödeme Yöntemi'
        ];
        
        // Prepare data rows
        $data = [];
        foreach ($orders as $order) {
            $statusMap = [
                'pending' => 'Bekliyor',
                'preparing' => 'Hazırlanıyor',
                'ready' => 'Hazır',
                'served' => 'Tamamlandı',
                'cancelled' => 'İptal'
            ];
            $status = $statusMap[$order['status']] ?? $order['status'] ?? 'Bilinmiyor';
            
            $paymentMethodMap = [
                'CASH' => 'Nakit',
                'CARD' => 'Kredi Kartı',
                'QR' => 'QR Kod',
                'MIXED' => 'Karışık'
            ];
            $paymentMethod = $paymentMethodMap[$order['payment_method']] ?? $order['payment_method'] ?? 'Nakit';
            
            $data[] = [
                $order['id'] ?? $order['order_id'] ?? '',
                $order['table'] ?? $order['table_name'] ?? 'Bilinmiyor',
                $order['customer'] ?? $order['customer_name'] ?? 'QR Sipariş',
                $status,
                $order['date'] ?? $order['created_at'] ?? '',
                number_format($order['amount'] ?? $order['total_amount'] ?? 0, 2, ',', '.'),
                $paymentMethod
            ];
        }
        
        // Generate filename
        $dateRange = '';
        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $dateRange = '_' . $filters['start_date'] . '_' . $filters['end_date'];
        } elseif (isset($filters['date_filter']) && $filters['date_filter'] !== 'all') {
            $dateRange = '_' . $filters['date_filter'];
        }
        $filename = 'siparisler' . $dateRange . '_' . date('Y-m-d_H-i-s');
        
        $title = 'Sipariş Listesi';
        if (!empty($dateRange)) {
            $title .= ' - ' . ($filters['start_date'] ?? $filters['date_filter'] ?? '');
        }
        
        // Export based on format
        switch (strtolower($format)) {
            case 'excel':
            case 'xlsx':
            case 'xls':
                $this->exportToExcel($data, $headers, $filename);
                break;
            case 'pdf':
                $this->exportToPDF($data, $headers, $filename, $title);
                break;
            case 'csv':
            default:
                $this->exportToCSV($data, $headers, $filename);
                break;
        }
    }
}

