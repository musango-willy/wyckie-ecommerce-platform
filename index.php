<?php
// Start native PHP secure session cookies before any code execution
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

// Intercept unauthorized requests and force display of the login frame interface
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wyckie Gateway Security</title>
    <script src="https://jsdelivr.net"></script>
</head>
<body class="bg-slate-900 flex items-center justify-center min-h-screen font-sans">
    <div class="max-w-md w-full mx-4 bg-white p-8 rounded-xl shadow-2xl border border-gray-100">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-black text-slate-800 tracking-tight">Wyckie Core</h1>
            <p class="text-xs text-slate-400 font-medium mt-1">Authorized Administrative Access Protocol</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="mb-4 p-3 bg-red-50 border-l-4 border-red-500 text-red-700 rounded text-xs">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="login">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Username</label>
                <input type="text" name="username" class="w-full p-2.5 border border-gray-300 rounded-md text-sm focus:outline-indigo-500" placeholder="Enter admin user" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Security Token / Password</label>
                <input type="password" name="password" class="w-full p-2.5 border border-gray-300 rounded-md text-sm focus:outline-indigo-500" placeholder="••••••••••••" required>
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-md transition-all shadow-md text-sm cursor-pointer">
                Unlock System Control Center
            </button>
        </form>
    </div>
</body>
</html>
<?php 
exit; 
}

// --- MAIN SECURE DASHBOARD IMPLEMENTATION ONCE LOGGED IN ---
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

    $cartCheck = $db->query("SELECT id FROM carts WHERE session_id = ? LIMIT 1", [$sessionToken]);
    if (empty($cartCheck)) {
        $db->query("INSERT INTO carts (session_id) VALUES (?)", [$sessionToken]);
        $cartCheck = $db->query("SELECT id FROM carts WHERE session_id = ? LIMIT 1", [$sessionToken]);
    }
    $cartId = $cartCheck['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'add_to_cart') {
            $productId = intval($_POST['product_id']);
            $itemCheck = $db->query("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?", [$cartId, $productId]);
            if (!empty($itemCheck)) {
                $db->query("UPDATE cart_items SET quantity = quantity + 1 WHERE id = ?", [$itemCheck['id']]);
            } else {
                $db->query("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, 1)", [$cartId, $productId]);
            }
            $message = "🛒 Item successfully added to your secure cart ledger.";
        }

        if ($_POST['action'] === 'clear_cart') {
            $db->query("DELETE FROM cart_items WHERE cart_id = ?", [$cartId]);
            $message = "🗑️ Shopping cart cleared.";
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
                $message = "📊 Live cart sheet successfully compiled and saved to: " . basename($filePath);
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
    
    // Safely look up the order logs from your table 
    $orderQuery = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5");
    $orderHistory = !empty($orderQuery) ? $orderQuery : [];

} catch (\Exception $e) {
    die("Error: " . $e->getMessage());
}

// --- MAIN SECURE DASHBOARD IMPLEMENTATION ONCE LOGGED IN ---
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

    $cartCheck = $db->query("SELECT id FROM carts WHERE session_id = ? LIMIT 1", [$sessionToken]);
    if (empty($cartCheck)) {
        $db->query("INSERT INTO carts (session_id) VALUES (?)", [$sessionToken]);
        $cartCheck = $db->query("SELECT id FROM carts WHERE session_id = ? LIMIT 1", [$sessionToken]);
    }
    $cartId = $cartCheck['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'add_to_cart') {
            $productId = intval($_POST['product_id']);
            $itemCheck = $db->query("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?", [$cartId, $productId]);
            if (!empty($itemCheck)) {
                $db->query("UPDATE cart_items SET quantity = quantity + 1 WHERE id = ?", [$itemCheck['id']]);
            } else {
                $db->query("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, 1)", [$cartId, $productId]);
            }
            $message = "🛒 Item successfully added to your secure cart ledger.";
        }

        if ($_POST['action'] === 'clear_cart') {
            $db->query("DELETE FROM cart_items WHERE cart_id = ?", [$cartId]);
            $message = "🗑️ Shopping cart cleared.";
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
                // Fixed open-stream lock bug by adding unique unix timestamp to file string
                $filePath = __DIR__ . '/live_cart_manifest_' . time() . '.xlsx';
                $report->exportSalesReport($filePath, $exportData);
                $message = "📊 Live cart sheet successfully compiled and saved to: " . basename($filePath);
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
    die("Error: " . $e->getMessage());
}
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
        
        <header class="mb-8 border-b border-gray-200 pb-4 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Wyckie Engine</h1>
                <p class="text-sm text-gray-500">Secure Administrative Commerce Framework</p>
            </div>
            <div class="flex items-center space-x-4">
                <span class="bg-emerald-100 text-emerald-800 text-xs font-semibold px-3 py-1 rounded-full">Secure Session</span>
                <a href="index.php?action=logout" class="text-xs font-bold text-red-500 hover:text-red-700 bg-red-50 px-3 py-1.md rounded hover:bg-red-100 transition-colors">Sign Out</a>
            </div>
        </header>

        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700 rounded-r shadow-xs">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Catalog UI Layout Grid mapping -->
            <div class="lg:col-span-2 space-y-6">
                <h2 class="text-xl font-bold text-gray-700">🛍️ Showroom Catalog</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($products as $prod): ?>
                        <div class="bg-white rounded-lg shadow-xs border border-gray-200 overflow-hidden flex flex-col justify-between">
                            <div class="bg-slate-200 h-40 flex items-center justify-center text-slate-400 font-mono text-xs">Product Resource Asset Frame</div>
                            <div class="p-4 flex-1 flex flex-col justify-between">
                                <div>
                                    <h3 class="font-bold text-gray-800 text-md"><?= htmlspecialchars($prod['name']) ?></h3>
                                    <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($prod['description']) ?></p>
                                </div>
                                <div class="mt-4 flex items-center justify-between">
                                    <span class="text-sm font-semibold text-gray-900">$<?= number_format(floatval($prod['price']), 2) ?></span>
                                    <form method="POST" class="m-0">
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <input type="hidden" name="product_id" value="<?= intval($prod['id']) ?>">
                                        <button type="submit" class="px-3 py-2 bg-indigo-600 text-white rounded text-xs font-semibold hover:bg-indigo-700">Add to cart</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <h2 class="text-xl font-bold text-gray-700 mb-4">🛒 Cart Overview</h2>
                    <?php if (!empty($cartRows)): ?>
                        <div class="space-y-3">
                            <?php foreach ($cartRows as $row): ?>
                                <div class="flex justify-between items-center bg-slate-50 p-3 rounded">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($row['name']) ?></p>
                                        <p class="text-xs text-gray-500">Qty: <?= intval($row['quantity']) ?> × $<?= number_format(floatval($row['price']), 2) ?></p>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900">$<?= number_format(floatval($row['price']) * intval($row['quantity']), 2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-gray-500">Your cart is currently empty.</p>
                    <?php endif; ?>
                    <div class="mt-6 grid gap-3">
                        <form method="POST" class="space-y-2">
                            <input type="hidden" name="action" value="clear_cart">
                            <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Clear Cart</button>
                        </form>
                        <form method="POST" class="space-y-2">
                            <input type="hidden" name="action" value="checkout_cart">
                            <button type="submit" class="w-full px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700">Checkout Cart</button>
                        </form>
                        <form method="POST" class="space-y-2">
                            <input type="hidden" name="action" value="export_cart_excel">
                            <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Export Cart Report</button>
                        </form>
                    </div>
                    <?php if (!empty($checkoutUrl)): ?>
                        <div class="mt-4 p-4 bg-emerald-50 border border-emerald-200 rounded">
                            <a href="<?= htmlspecialchars($checkoutUrl) ?>" class="text-sm font-semibold text-emerald-700">Proceed to Stripe Checkout</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <h2 class="text-xl font-bold text-gray-700 mb-4">📜 Latest Orders</h2>
                    <?php if (!empty($orderHistory)): ?>
                        <ul class="space-y-3 text-sm text-gray-700">
                            <?php foreach ($orderHistory as $order): ?>
                                <li class="p-3 border border-gray-100 rounded">
                                    <p class="font-semibold"><?= htmlspecialchars($order['order_number'] ?? 'Order #' . intval($order['id'])) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($order['created_at'] ?? '') ?></p>
                                    <p><?= htmlspecialchars($order['status'] ?? 'N/A') ?> — $<?= number_format(floatval($order['amount'] ?? 0), 2) ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-sm text-gray-500">No recent order history available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
