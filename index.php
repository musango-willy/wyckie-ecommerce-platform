<?php
// Force XAMPP to show errors instead of a blank screen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

use Wyckie\EcommercePlatform\PaymentGateway;
use Wyckie\EcommercePlatform\Database;

$message = '';
$checkoutUrl = '';

// Handle Admin Logout Action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Handle Form Login Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $inputUser = $_POST['username'] ?? '';
    $inputPass = $_POST['password'] ?? '';
    
    $envUser = 'admin';
    $envPass = 'SecretWyckie2026';

    if ($inputUser === $envUser && $inputPass === $envPass) {
        $_SESSION['authenticated'] = true;
        header("Location: index.php");
        exit;
    } else {
        $message = "❌ Invalid administrative username or password combination.";
    }
}

// Intercept unauthorized requests
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Wyckie Gateway Security</title>
    <script src="https://jsdelivr.net"></script>
</head>
<body class="bg-slate-900 flex items-center justify-center min-h-screen font-sans">
    <div class="max-w-md w-full mx-4 bg-white p-8 rounded-xl shadow-2xl">
        <h1 class="text-2xl font-black text-center text-slate-800 mb-6">Wyckie Core Security</h1>
        <?php if (!empty($message)): ?><div class="mb-4 p-3 bg-red-50 text-red-700 text-xs"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="login">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Username</label>
                <input type="text" name="username" class="w-full p-2.5 border rounded-md" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Password</label>
                <input type="password" name="password" class="w-full p-2.5 border rounded-md" required>
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-md cursor-pointer">Unlock Desk</button>
        </form>
    </div>
</body>
</html>
<?php 
    exit; 
}

// --- AUTHENTICATED STATE ---
if (!isset($_COOKIE['shop_session'])) {
    $sessionToken = bin2hex(random_bytes(16));
    setcookie('shop_session', $sessionToken, time() + (86400 * 30), "/");
    $_COOKIE['shop_session'] = $sessionToken;
}
$sessionToken = $_COOKIE['shop_session'];

try {
    $db = new Database($_ENV['DB_HOST'] ?? '127.0.0.1', $_ENV['DB_NAME'] ?? 'ecommerce_db', $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? '');
    $stripeKey = $_ENV['STRIPE_SECRET_KEY'] ?? 'sk_test_fallback'; 
    $payment = new PaymentGateway($stripeKey);

    $cartCheckQuery = $db->query("SELECT id FROM carts WHERE session_id = ? LIMIT 1", [$sessionToken]);
    if (empty($cartCheckQuery)) {
        $db->query("INSERT INTO carts (session_id) VALUES (?)", [$sessionToken]);
        $cartCheckQuery = $db->query("SELECT id FROM carts WHERE session_id = ? LIMIT 1", [$sessionToken]);
    }
    
    // Check array type layout format mapping
    $cartId = isset($cartCheckQuery[0]['id']) ? $cartCheckQuery[0]['id'] : (isset($cartCheckQuery['id']) ? $cartCheckQuery['id'] : 1);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        
        if ($_POST['action'] === 'add_to_cart') {
            $productId = intval($_POST['product_id']);
            $itemCheck = $db->query("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?", [$cartId, $productId]);
            $checkRow = !empty($itemCheck) ? (isset($itemCheck[0]) ? $itemCheck[0] : $itemCheck) : [];
            if (!empty($checkRow)) {
                $db->query("UPDATE cart_items SET quantity = quantity + 1 WHERE id = ?", [$checkRow['id']]);
            } else {
                $db->query("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, 1)", [$cartId, $productId]);
            }
            $message = "🛒 Item added to your secure cart ledger.";
        }

        if ($_POST['action'] === 'clear_cart') {
            $db->query("DELETE FROM cart_items WHERE cart_id = ?", [$cartId]);
            $message = "🗑️ Shopping cart cleared.";
        }

        if ($_POST['action'] === 'create_product') {
            $name = $_POST['p_name'];
            $desc = $_POST['p_desc'];
            $price = floatval($_POST['p_price']);
            $db->query("INSERT INTO products (name, description, price, image_path) VALUES (?, ?, ?, 'https://placehold.co')", [$name, $desc, $price]);
            $message = "🚀 New product added to catalog successfully!";
        }

        if ($_POST['action'] === 'update_product') {
            $id = intval($_POST['p_id']);
            $name = $_POST['p_name'];
            $desc = $_POST['p_desc'];
            $price = floatval($_POST['p_price']);
            $db->query("UPDATE products SET name = ?, description = ?, price = ? WHERE id = ?", [$name, $desc, $price, $id]);
            $message = "✅ Product details updated successfully inside MySQL!";
        }

        if ($_POST['action'] === 'delete_product') {
            $id = intval($_POST['p_id']);
            $db->query("DELETE FROM products WHERE id = ?", [$id]);
            $message = "❌ Product removed from catalog.";
        }

        if ($_POST['action'] === 'export_cart_excel') {
            $liveCartItems = $db->query("SELECT p.name, ci.quantity, (p.price * ci.quantity) as total FROM cart_items ci JOIN products p ON ci.product_id = p.id WHERE ci.cart_id = ?", [$cartId]);
            if (!empty($liveCartItems)) {
                require_once __DIR__ . '/ReportGenerator.php';
                $report = new \Wyckie\EcommercePlatform\ReportGenerator();
                $exportData = [];
                foreach ($liveCartItems as $index => $item) {
                    $exportData[] = ['id' => 'ITEM-' . ($index + 1), 'email' => 'Secure Ledger', 'total' => $item['total'], 'status' => $item['name'] . ' (Qty: ' . $item['quantity'] . ')'];
                }
                $filePath = __DIR__ . '/live_cart_manifest_' . time() . '.xlsx';
                $report->exportSalesReport($filePath, $exportData);
                $message = "📊 Live cart compiled successfully: " . basename($filePath);
            }
        }

        if ($_POST['action'] === 'checkout_cart') {
            $currentCartItems = $db->query("SELECT ci.quantity, p.name, p.price FROM cart_items ci JOIN products p ON ci.product_id = p.id WHERE ci.cart_id = ?", [$cartId]);
            if (!empty($currentCartItems)) {
                $stripeLineItems = [];
                foreach ($currentCartItems as $item) {
                    $stripeLineItems[] = [
                        'price_data' => ['currency' => 'usd', 'product_data' => ['name' => $item['name']], 'unit_amount' => intval($item['price'] * 100)],
                        'quantity' => intval($item['quantity']),
                    ];
                }
                $checkoutUrl = $payment->createCartCheckoutSession($stripeLineItems, 'http://localhost/ecommerce/index.php?status=success', 'http://localhost/ecommerce/index.php?status=cancel');
            }
        }
    }

    $products = $db->query("SELECT * FROM products");
    $cartRows = $db->query("SELECT ci.id, ci.quantity, p.name, p.price FROM cart_items ci JOIN products p ON ci.product_id = p.id WHERE ci.cart_id = ?", [$cartId]);
    $orderHistory = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5");

} catch (\Exception $e) {
    die("Error Exception Caught: " . $e->getMessage());
}

$viewMode = $_GET['view'] ?? 'store'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wyckie Enterprise Suite</title>
    <script src="https://jsdelivr.net"></script>
</head>
<body class="bg-gray-100 font-sans">
    <div class="max-w-7xl mx-auto px-4 py-8">
        
        <!-- Header Bar -->
        <header class="mb-8 border-b border-gray-200 pb-4 flex justify-between items-center bg-white p-4 rounded-xl shadow-xs">
            <div>
                <h1 class="text-3xl font-black text-slate-800">Wyckie Engine</h1>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Dual-Mode Administrative Commerce Core</p>
            </div>
            <div class="flex items-center space-x-2">
                <a href="index.php?view=store" class="px-4 py-2 text-xs font-bold rounded-lg transition-colors <?= $viewMode==='store' ? 'bg-indigo-600 text-white':'bg-gray-100 text-gray-700 hover:bg-gray-200'?>">🛒 Customer Storefront</a>
                <a href="index.php?view=admin" class="px-4 py-2 text-xs font-bold rounded-lg transition-colors <?= $viewMode==='admin' ? 'bg-amber-600 text-white':'bg-gray-100 text-gray-700 hover:bg-gray-200'?>">⚙️ Product Inventory Manager</a>
                <a href="index.php?action=logout" class="text-xs font-bold text-red-500 bg-red-50 px-3 py-2 rounded-lg hover:bg-red-100">Sign Out</a>
            </div>
        </header>

        <!-- Alerts -->
        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700 rounded-r shadow-xs font-semibold text-sm">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- ================= STORE VIEW ================= -->
