<?php
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
    
    if ($inputUser === 'admin' && $inputPass === 'SecretWyckie2026') {
        $_SESSION['authenticated'] = true;
        header("Location: index.php");
        exit;
    } else {
        $message = "❌ Invalid administrative username or password combination.";
    }
}

// Intercept unauthorized requests
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true):
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
endif;

// --- AUTHENTICATED STATE RUNTIME ---
if (!isset($_COOKIE['shop_session'])) {
    $sessionToken = bin2hex(random_bytes(16));
    setcookie('shop_session', $sessionToken, time() + (86400 * 30), "/");
    $_COOKIE['shop_session'] = $sessionToken;
} else {
    $sessionToken = $_COOKIE['shop_session'];
}

try {
    $db = new Database($_ENV['DB_HOST'] ?? '127.0.0.1', $_ENV['DB_NAME'] ?? 'ecommerce_db', $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? '');
    $stripeKey = $_ENV['STRIPE_SECRET_KEY'] ?? 'sk_test_fallback'; 
    $payment = new PaymentGateway($stripeKey);

    $cartCheckQuery = $db->query("SELECT id FROM carts WHERE session_id = ? LIMIT 1", [$sessionToken]);
    if (empty($cartCheckQuery)) {
        $db->query("INSERT INTO carts (session_id) VALUES (?)", [$sessionToken]);
        $cartCheckQuery = $db->query("SELECT id FROM carts WHERE session_id = ? LIMIT 1", [$sessionToken]);
    }
    
    // Normalize row extract
    $cartId = isset($cartCheckQuery[0]['id']) ? $cartCheckQuery[0]['id'] : (isset($cartCheckQuery['id']) ? $cartCheckQuery['id'] : 1);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        
        if ($_POST['action'] === 'add_to_cart') {
            $productId = intval($_POST['product_id']);
            $itemCheck = $db->query("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1", [$cartId, $productId]);
            
            if (!empty($itemCheck)) {
                $row = isset($itemCheck[0]) ? $itemCheck[0] : $itemCheck;
                $newQty = $row['quantity'] + 1;
                $db->query("UPDATE cart_items SET quantity = ? WHERE id = ?", [$newQty, $row['id']]);
            } else {
                $db->query("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, 1)", [$cartId, $productId]);
            }
            $message = "🛒 Item added successfully!";
        }

        if ($_POST['action'] === 'clear_cart') {
            $db->query("DELETE FROM cart_items WHERE cart_id = ?", [$cartId]);
            $message = "🗑️ Shopping cart cleared.";
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
    
    // Explicitly select the exact multi-item fields for clean tabular rendering
    $cartRows = $db->query("SELECT ci.id, ci.quantity, p.name, p.price FROM cart_items ci JOIN products p ON ci.product_id = p.id WHERE ci.cart_id = ?", [$cartId]);
    $orderHistory = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5");

} catch (\Exception $e) {
    die("Critical Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Wyckie Enterprise Suite</title>
    <script src="https://jsdelivr.net"></script>
</head>
<body class="bg-gray-100 font-sans text-gray-800">
    <div class="max-w-7xl mx-auto px-4 py-8">
        
        <header class="mb-8 border-b border-gray-200 pb-4 flex justify-between items-center bg-white p-4 rounded-xl shadow-xs">
            <div>
                <h1 class="text-3xl font-black text-slate-800">Wyckie Engine</h1>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Administrative Commerce Dashboard</p>
            </div>
            <a href="index.php?action=logout" class="text-xs font-bold text-red-500 bg-red-50 px-3 py-2 rounded-lg hover:bg-red-100">Sign Out</a>
        </header>

        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700 rounded-r text-sm font-semibold"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Catalog Layout -->
            <div class="lg:col-span-2 space-y-6">
                <h2 class="text-xl font-bold text-gray-700 uppercase">🛍️ Showroom Catalogue</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($products as $prod): ?>
                        <div class="bg-white rounded-xl shadow-xs border border-gray-200 overflow-hidden flex flex-col justify-between">
                            <div class="bg-slate-200 h-40 flex items-center justify-center text-slate-400 text-xs font-mono font-bold">Product Asset</div>
                            <div class="p-4 flex-1 flex flex-col justify-between">
                                <div>
                                    <h3 class="font-bold text-gray-800 text-md"><?= htmlspecialchars($prod['name']) ?></h3>
                                    <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($prod['description']) ?></p>
                                </div>
                                <div class="flex justify-between items-center mt-4">
                                    <span class="text-lg font-extrabold text-gray-900">$<?= number_format($prod['price'], 2) ?></span>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
                                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-3 py-2 rounded-md cursor-pointer">+ Add to Cart</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Shopping Basket UI Table component -->
            <div class="bg-white p-6 rounded-xl shadow-xs border border-gray-200 h-fit">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-bold text-gray-700">🛒 Shopping Cart</h2>
                    <?php if (!empty($cartRows)): ?>
                        <form method="POST"><input type="hidden" name="action" value="clear_cart"><button type="submit" class="text-xs text-red-500 hover:underline cursor-pointer font-bold">Clear All</button></form>
                    <?php endif; ?>
                </div>
                <div class="border border-gray-100 rounded-lg overflow-hidden mb-4">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50 text-gray-600 font-bold border-b border-gray-100"><th class="p-3">Item Description</th><th class="p-3 text-center">Qty</th><th class="p-3 text-right">Subtotal</th></tr>
