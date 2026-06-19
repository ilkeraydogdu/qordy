<?php
namespace App\Core\Traits;

/**
 * HandlesExport Trait
 * Provides export functionality for controllers
 */
trait HandlesExport {
    /**
     * Export data to CSV
     * @param array $data Data to export
     * @param array $headers Column headers
     * @param string $filename Output filename
     * @return void
     */
    protected function exportToCSV(array $data, array $headers, string $filename): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export data to Excel (using ExportService)
     * @param array $data Data to export
     * @param array $headers Column headers
     * @param string $filename Output filename
     * @return void
     */
    protected function exportToExcel(array $data, array $headers, string $filename): void {
        $exportService = \App\Core\DependencyFactory::getExportService();
        $exportService->exportToExcel($data, $headers, $filename);
    }
    
    /**
     * Export orders using ExportService
     * @param array $orders Order data
     * @param string $format Export format ('csv', 'excel', 'pdf')
     * @param array $filters Applied filters
     * @return void
     */
    protected function exportOrders(array $orders, string $format = 'csv', array $filters = []): void {
        $exportService = \App\Core\DependencyFactory::getExportService();
        $exportService->exportOrders($orders, $format, $filters);
    }
    
    /**
     * Export report data
     * @param array $reportData Report data
     * @param string $reportType Report type identifier
     * @param string $format Export format ('csv', 'excel', 'pdf')
     * @param array $params Additional parameters (dates, filters, etc.)
     * @return void
     */
    protected function exportReport(
        array $reportData,
        string $reportType,
        string $format = 'csv',
        array $params = []
    ): void {
        $exportService = \App\Core\DependencyFactory::getExportService();
        
        // Generate filename based on report type and date
        $startDate = $params['start_date'] ?? date('Y-m-d');
        $endDate = $params['end_date'] ?? date('Y-m-d');
        
        $filenameMap = [
            'sales' => 'sales_report_' . $startDate . '_to_' . $endDate,
            'inventory' => 'inventory_report_' . date('Y-m-d'),
            'employee' => 'employee_report_' . $startDate . '_to_' . $endDate,
            'customer' => 'customer_report_' . $startDate . '_to_' . $endDate,
            'expense' => 'expense_report_' . $startDate . '_to_' . $endDate,
            'profit_loss' => 'profit_loss_report_' . $startDate . '_to_' . $endDate
        ];
        
        $filename = $filenameMap[$reportType] ?? 'report_' . date('Y-m-d');
        
        if ($format === 'csv') {
            $this->exportReportToCSV($reportData, $reportType, $filename);
        } else {
            $exportService->exportReport($reportData, $reportType, $format, $params);
        }
    }
    
    /**
     * Export report to CSV (internal method)
     * @param array $reportData Report data
     * @param string $reportType Report type
     * @param string $filename Output filename
     * @return void
     */
    private function exportReportToCSV(array $reportData, string $reportType, string $filename): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write headers and data based on report type
        switch ($reportType) {
            case 'employee':
                fputcsv($output, ['Personel Adı', 'E-posta', 'İşlenen Sipariş', 'Toplam Satış', 'Ortalama Sipariş Değeri', 'Tamamlanan Sipariş']);
                foreach ($reportData as $row) {
                    fputcsv($output, [
                        $row['name'] ?? '',
                        $row['email'] ?? '',
                        $row['orders_processed'] ?? 0,
                        $row['total_sales'] ?? 0,
                        $row['avg_order_value'] ?? 0,
                        $row['completed_orders'] ?? 0
                    ]);
                }
                break;
                
            case 'customer':
                fputcsv($output, ['Masa Adı', 'Bölge', 'Toplam Sipariş', 'Toplam Gelir', 'Ortalama Sipariş Değeri', 'Tamamlanan Sipariş', 'Aktif Günler']);
                foreach ($reportData as $row) {
                    fputcsv($output, [
                        $row['table_name'] ?? '',
                        $row['zone'] ?? '',
                        $row['total_orders'] ?? 0,
                        $row['total_revenue'] ?? 0,
                        $row['avg_order_value'] ?? 0,
                        $row['completed_orders'] ?? 0,
                        $row['active_days'] ?? 0
                    ]);
                }
                break;
                
            default:
                // Generic export
                fputcsv($output, ['Metrik', 'Değer']);
                foreach ($reportData as $key => $value) {
                    if (is_array($value)) {
                        fputcsv($output, [$key, 'Detaylar']);
                        foreach ($value as $subItem) {
                            if (is_array($subItem)) {
                                fputcsv($output, ['', json_encode($subItem)]);
                            } else {
                                fputcsv($output, ['', $subItem]);
                            }
                        }
                    } else {
                        fputcsv($output, [$key, $value]);
                    }
                }
                break;
        }
        
        fclose($output);
        exit;
    }
}

