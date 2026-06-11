<?php

require __DIR__ . '/vendor/autoload.php';

use Wyckie\EcommercePlatform\ReportGenerator;

// 1. Create a dummy list of recent customer transactions
$mockOrders = [
    ['id' => 'ORD-1001', 'email' => 'john@example.com', 'total' => 45.00, 'status' => 'Paid'],
    ['id' => 'ORD-1002', 'email' => 'sarah@example.com', 'total' => 120.50, 'status' => 'Paid'],
    ['id' => 'ORD-1003', 'email' => 'alex@example.com', 'total' => 15.99, 'status' => 'Pending'],
    ['id' => 'ORD-1004', 'email' => 'wycliffe@example.com', 'total' => 300.00, 'status' => 'Refunded']
];

try {
    // 2. Initialize your report generator class
    $reporter = new ReportGenerator();
    
    // 3. Define the file output location inside your current folder
    $fileName = __DIR__ . '/sales_report_june_2026.xlsx';
    
    // 4. Run the export process
    $reporter->exportSalesReport($fileName, $mockOrders);
    
    echo "Success! Your spreadsheet has been created.\n";
    echo "File location: " . $fileName . "\n";

} catch (\Exception $e) {
    echo "Error generating spreadsheet: " . $e->getMessage() . "\n";
}

