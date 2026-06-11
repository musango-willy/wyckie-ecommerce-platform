<?php
require __DIR__ . '/vendor/autoload.php';

// 1. Load variables safely from your hidden .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

use Wyckie\EcommercePlatform\PaymentGateway;
use Wyckie\EcommercePlatform\ImageProcessor;
use Wyckie\EcommercePlatform\ReportGenerator;
use Wyckie\EcommercePlatform\Database;

$message = '';
$checkoutUrl = '';

try {
    // 2. Read database values out of the environment safely
    $db = new Database(
        $_ENV['DB_HOST'] ?? '127.0.0.1', 
        $_ENV['DB_NAME'] ?? 'ecommerce_db', 
        $_ENV['DB_USER'] ?? 'root', 
        $_ENV['DB_PASS'] ?? ''
    );
    
    // 3. Read your hidden Stripe API key safely
    $stripeKey = $_ENV['STRIPE_SECRET_KEY'] ?? 'sk_test_fallback'; 
    $payment = new PaymentGateway($stripeKey);
    
    $report = new ReportGenerator();
    $processor = new ImageProcessor();

    // Action 1: Stripe Checkout Generation
    if (isset($_POST['action']) && $_POST['action'] === 'checkout') {
        try {
            $checkoutUrl = $payment->createCheckoutSession(
                3500, 
                'usd',
                'http://localhost/ecommerce/index.php?status=success',
                'http://localhost/ecommerce/index.php?status=cancel'
            );
        } catch (\Exception $e) {
            $message = "Stripe Error: " . $e->getMessage();
        }
    }

    // Action 2: Image Upload & Transformation
    if (isset($_POST['action']) && $_POST['action'] === 'upload' && isset($_FILES['product_image'])) {
        try {
            $file = $_FILES['product_image'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $rawPath = $file['tmp_name'];
                $outputPath = __DIR__ . '/uploads/thumb_product.jpg';
                
                // Trigger your custom package image studio engine!
                $processor->createThumbnail($rawPath, $outputPath, 300, 300);
                $message = "🎉 Image uploaded, compressed, and resized to 300x300px successfully!";
                $previewThumbnail = 'uploads/thumb_product.jpg?' . time();
            } else {
                $message = "Upload Error: Please select a valid image file.";
            }
        } catch (\Exception $e) {
            $message = "Image Studio Error: " . $e->getMessage();
        }
    }

    // Action 3: Export Excel Sheet Report
    if (isset($_POST['action']) && $_POST['action'] === 'export') {
        try {
            $mockOrders = [
                ['id' => 'ORD-1001', 'email' => 'customer1@example.com', 'total' => 35.00, 'status' => 'Paid'],
                ['id' => 'ORD-1002', 'email' => 'customer2@example.com', 'total' => 70.00, 'status' => 'Paid']
            ];
            $filePath = __DIR__ . '/sales_report.xlsx';
            $report->exportSalesReport($filePath, $mockOrders);
            $message = "📈 Spreadsheet report successfully saved to: " . $filePath;
        } catch (\Exception $e) {
            $message = "Excel Error: " . $e->getMessage();
        }
    }
} catch (\Exception $e) {
    $message = "System Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wyckie Ecommerce Platform</title>
    <script src="https://jsdelivr.net"></script>
</head>
<body class="bg-gray-100 font-sans">

    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Header -->
        <header class="mb-8 border-b border-gray-200 pb-4 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Wyckie Engine</h1>
                <p class="text-sm text-gray-500">Custom Ecommerce Platform Control Panel</p>
            </div>
            <span class="bg-green-100 text-green-800 text-xs font-semibold px-3 py-1 rounded-full">System Online</span>
        </header>

        <!-- Status Alerts -->
        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700 rounded-r shadow-xs">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Grid Layout -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Column 1: Storefront / Checkout Integration -->
            <div class="bg-white p-6 rounded-lg shadow-xs border border-gray-200">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">🛒 Live Storefront Demo</h2>
                <div class="border border-gray-100 rounded-lg overflow-hidden mb-4">
                    <img src="<?= $previewThumbnail ?>" class="w-full h-48 object-cover" alt="Product Image">
                    <div class="p-4">
                        <h3 class="font-bold text-gray-800 text-lg">Premium Wireless Headphones</h3>
                        <p class="text-sm text-gray-500 mt-1">High-fidelity audio with active noise cancellation properties.</p>
                        <div class="flex justify-between items-center mt-4">
                            <span class="text-xl font-extrabold text-gray-900">$35.00</span>
                            <form method="POST">
                                <input type="hidden" name="action" value="checkout">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded transition-colors cursor-pointer">
                                    Generate Checkout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if (!empty($checkoutUrl)): ?>
                    <div class="mt-4 p-4 bg-indigo-50 border border-indigo-100 rounded text-center">
                        <p class="text-sm text-indigo-800 font-medium mb-2">Secure Link Ready!</p>
                        <a href="<?= $checkoutUrl ?>" target="_blank" class="inline-block bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-bold py-2 px-4 rounded transition-colors shadow-xs">
                            Go to Stripe Payment Page &rarr;
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Column 2: Media Management (ImageProcessor Active) -->
            <div class="bg-white p-6 rounded-lg shadow-xs border border-gray-200">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">🖼️ Catalog Image Studio</h2>
                <p class="text-sm text-gray-500 mb-4">Upload raw product images to crop, compress, and resize automatically to 300x300px aspect ratios.</p>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="upload">
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-indigo-500 transition-colors bg-gray-50">
                        <input type="file" name="product_image" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer" required id="file-upload">
                        <p class="text-xs text-gray-400 mt-2">PNG, JPG up to 5MB</p>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 rounded transition-colors shadow-xs cursor-pointer">
                        Upload & Transform Image
                    </button>
                </form>
            </div>

            <!-- Column 3: Data Analytics & Reporting (ReportGenerator) -->
            <div class="bg-white p-6 rounded-lg shadow-xs border border-gray-200 flex flex-col justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">📊 Business Intelligence</h2>
                    <p class="text-sm text-gray-500 mb-4">Compile live transactional databases directly into formatted Microsoft Excel files for distribution.</p>
                    
                    <div class="bg-gray-50 p-4 rounded border border-gray-100 mb-4">
                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                            <span>Total Platform Sales</span>
                            <span class="font-bold text-gray-800">$105.00</span>
                        </div>
                        <div class="flex justify-between text-sm text-gray-600">
                            <span>Active Orders Logged</span>
                            <span class="font-bold text-gray-800">4 Records</span>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="export">
                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 rounded transition-colors text-center shadow-xs cursor-pointer">
                        Export Sales Report (.XLSX)
                    </button>
                </form>
            </div>

        </div>
    </div>

</body>
</html>
