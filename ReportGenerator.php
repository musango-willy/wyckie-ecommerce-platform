<?php

namespace Wyckie\EcommercePlatform;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportGenerator
{
    /**
     * Export sales logs into a downloadable Excel (.xlsx) Spreadsheet
     *
     * @param string $outputPath Full file save path (e.g., './sales_report.xlsx')
     * @param array $orders Multi-dimensional array of sales data
     */
    public function exportSalesReport(string $outputPath, array $orders): void
    {
        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();
        
        // 1. Define Column Headers
        $activeWorksheet->setCellValue('A1', 'Order ID');
        $activeWorksheet->setCellValue('B1', 'Customer Email');
        $activeWorksheet->setCellValue('C1', 'Total Amount (USD)');
        $activeWorksheet->setCellValue('D1', 'Payment Status');

        // 2. Loop through dynamic array inputs and assign cell positions
        $row = 2;
        foreach ($orders as $order) {
            $activeWorksheet->setCellValue('A' . $row, $order['id']);
            $activeWorksheet->setCellValue('B' . $row, $order['email']);
            $activeWorksheet->setCellValue('C' . $row, $order['total']);
            $activeWorksheet->setCellValue('D' . $row, $order['status']);
            $row++;
        }

        // 3. Render and save the file output
        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
    }
}
