<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- COMPOSER ENGINE & ROUTING INITIALIZATION ---
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Database.php';
use Wyckie\EcommercePlatform\Database;

// Safe dotenv package layer registration
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

// Ensure an active session cookie tracks individual cart allocations
if (!isset($_COOKIE['shop_session'])) {
    $sessionToken = bin2hex(random_bytes(16));
    setcookie('shop_session', $sessionToken, time() + (86400 * 30), "/");
    $_COOKIE['shop_session'] = $sessionToken;
} else {
    $sessionToken = $_COOKIE['shop_session'];
}

$message = "";

try {
    // Dynamic database connector instantiation
    $db = new Database(
        $_ENV['DB_HOST'] ?? '127.0.0.1', 
        $_ENV['DB_NAME'] ?? 'ecommerce_db', 
        $_ENV['DB_USER'] ?? 'root', 
        $_ENV['DB_PASS'] ?? ''
    );
    
    $stripeKey = $_ENV['STRIPE_SECRET_KEY'] ?? 'sk_test_fallback';
    
    // Resolve user's relational cart record index
    $cartCheckQuery = $db->query("SELECT id FROM carts WHERE session_id = ? LIMIT 1", [$sessionToken]);
    if (empty($cartCheckQuery)) {
        $db->query("INSERT INTO carts (session_id) VALUES (?)", [$sessionToken]);
        $cartCheckQuery = $db->query("SELECT id FROM carts WHERE session_id = ? LIMIT 1", [$sessionToken]);
    }
    
    // Safety fallback handling array un-nesting structure variations
    $cartId = isset($cartCheckQuery[0]['id']) ? $cartCheckQuery[0]['id'] : (isset($cartCheckQuery['id']) ? $cartCheckQuery['id'] : 1);

    // --- FORM SUBMISSION PROCESSING ACTION ROUTER ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        // 1. Clear Active Cart Items
        if ($action === 'clear_cart') {
            $db->query("DELETE FROM cart_items WHERE cart_id = ?", [$cartId]);
            $message = "🗑️ Your shopping cart has been cleared.";
        }

        // 2. Stripe Secure Session Checkout Redirection
        if ($action === 'checkout_stripe') {
            $cartItems = $db->query("SELECT c.quantity, p.name, p.price FROM cart_items c JOIN products p ON c.product_id = p.id WHERE c.cart_id = ?", [$cartId]);
            
            if (!empty($cartItems)) {
                $lineItems = [];
                foreach ($cartItems as $item) {
                    $lineItems[] = [
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => ['name' => $item['name']],
                            'unit_amount' => intval($item['price'] * 100), // Converted to cents
                        ],
                        'quantity' => intval($item['quantity']),
                    ];
                }

                try {
                    \Stripe\Stripe::setApiKey($stripeKey);
                    $session = \Stripe\Checkout\Session::create([
                        'payment_method_types' => ['card'],
                        'line_items' => $lineItems,
                        'mode' => 'payment',
                        'success_url' => 'http://localhost/ecommerce/index.php?session_id={CHECKOUT_SESSION_ID}',
                        'cancel_url' => 'http://localhost/ecommerce/index.php',
                    ]);
                    
                    // Flush items immediately out of basket database logs upon setup completion
                    $db->query("DELETE FROM cart_items WHERE cart_id = ?", [$cartId]);
                    
                    header("Location: " . $session->url);
                    exit();
                } catch (\Exception $e) {
                    $message = "❌ Stripe Gateway Error: " . $e->getMessage();
                }
            } else {
                $message = "⚠️ Your shopping cart is empty. Cannot initialize checkout session.";
            }
        }

        // 3. Add Item into Basket Logic
        if ($action === 'add_to_cart') {
            $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);
            
            if ($productId > 0) {
                $itemCheck = $db->query("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1", [$cartId, $productId]);
                
                if (!empty($itemCheck)) {
                    $row = $itemCheck[0] ?? $itemCheck;
                    if (isset($row['id'])) {
                        $db->query("UPDATE cart_items SET quantity = quantity + 1 WHERE id = ?", [$row['id']]);
                    }
                } else {
                    $db->query("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, 1)", [$cartId, $productId]);
                }
                $message = "🛒 Item added successfully!";
            }
        }

        // 4. Handle Manual Product Creation
        if ($action === 'create_product') {
            $name = htmlspecialchars($_POST['name']);
            $description = htmlspecialchars($_POST['description']);
            $price = floatval($_POST['price']);
            $stock = intval($_POST['stock']);
            
            $db->query("INSERT INTO products (name, description, price, stock) VALUES (?, ?, ?, ?)", [$name, $description, $price, $stock]);
            $message = "✨ New product added to showroom!";
        }

        // 5. Bulk Generate 50 Assorted Inventory Items Seeder
        if ($action === 'seed_50_items') {
            $categories = ['Electronics', 'Apparel', 'Home Goods', 'Fitness'];
            $nouns = ['Pro', 'Max', 'Ultra', 'Classic', 'Elite', 'Eco', 'Smart'];
            
            for ($i = 1; $i <= 50; $i++) {
                $cat = $categories[$i % 4];
                $noun = $nouns[$i % 7];
                $name = $cat . " " . $noun . " Item #" . $i;
                $description = "Premium grade asset from our " . $cat . " collection. Built for durability and high-performance metrics.";
                $price = rand(15, 299) + 0.99;
                $stock = rand(5, 40);
                
                $db->query("INSERT INTO products (name, description, price, stock) VALUES (?, ?, ?, ?)", [$name, $description, $price, $stock]);
            }
            $message = "🚀 Successfully injected 50 assorted showroom items!";
        }
    }

    // --- STRIPE RETURN REDIRECT TRANSACTION CAPTURE LOG ---
    if (isset($_GET['session_id'])) {
        $stripeSessionId = htmlspecialchars($_GET['session_id']);
        $existingOrder = $db->query("SELECT id FROM orders WHERE stripe_session_id = ? LIMIT 1", [$stripeSessionId]);
        
        if (empty($existingOrder)) {
            $db->query("INSERT INTO orders (stripe_session_id, created_at) VALUES (?, NOW())", [$stripeSessionId]);
            $message = "🎉 Payment Successful! Your order has been placed logged into history.";
        }
    }

    // --- DATA DISPLAY RETRIEVAL QUERIES ---
    $products = $db->query("SELECT id, name, description, price, stock FROM products ORDER BY id DESC");
    $orderHistory = $db->query("SELECT id, created_at FROM orders ORDER BY id DESC LIMIT 10");

} catch (\Exception $e) {
    die("Critical Application Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Wyckie Engine Dashboard</title>
</head>
<body style="background: #f1f5f9; color: #1e293b; font-family: sans-serif; padding: 40px; margin: 0;">

    <div style="max-width: 1200px; margin: 0 auto;">
        
        <!-- Header Brand Layout -->
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #cbd5e1; padding-bottom: 20px; margin-bottom: 30px;">
            <div>
                <h1 style="margin: 0; color: #0f172a; font-size: 28px;">Wyckie Engine</h1>
                <p style="margin: 5px 0 0 0; color: #64748b; font-weight: 500;">Administrative Commerce Dashboard</p>
            </div>
            <a href="?signout=1" style="background: #ef4444; color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; font-size: 14px;">Sign Out</a>
        </div>

        <!-- Dynamic Success Status Notice Banner -->
        <?php if (!empty($message)): ?>
            <div style="background: #ecfdf5; border: 1px solid #10b981; color: #065f46; padding: 16px; border-radius: 8px; margin: 20px 0; font-weight: bold; font-size: 15px;">
                <?= $message; ?>
            </div>
        <?php endif; ?>

        <!-- Split Grid Columns Layout -->
               <!-- Split Grid Columns Layout -->
        <div style="display: grid; grid-template-columns: 1fr 400px; gap: 40px; align-items: start;">
            
            <!-- LEFT MAIN WORKSPACE COLUMN -->
            <div>
                <!-- Inventory Controls Element Component Card -->
                <div style="background: white; border: 1px solid #e2e8f0; padding: 24px; border-radius: 12px; margin-bottom: 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <h3 style="margin-top: 0; color: #0f172a; font-size: 18px; margin-bottom: 16px;">🛠️ Inventory Management Controls</h3>
                    
                    <form method="POST" style="display: grid; gap: 12px; max-width: 500px; margin-bottom: 20px;">
                        <input type="hidden" name="action" value="create_product">
                        <input type="text" name="name" placeholder="Product Title (e.g. Mechanical Keyboard)" required style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                        <textarea name="description" placeholder="Product Description..." required style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: sans-serif; font-size: 14px; height: 70px; resize: vertical;"></textarea>
                        <div style="display: flex; gap: 10px;">
                            <input type="number" step="0.01" name="price" placeholder="Price ($)" required style="flex: 1; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                            <input type="number" name="stock" placeholder="Initial Stock" required style="flex: 1; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                        </div>
                        <button type="submit" style="background: #2563eb; color: white; border: none; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 14px; width: 100%;">➕ Publish Product</button>
                    </form>

                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="seed_50_items">
                        <button type="submit" style="background: #10b981; color: white; border: none; padding: 12px 18px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 14px;">⚡ Auto-Generate 50 Assorted Inventory Items</button>
                    </form>
                </div>

                <!-- Showroom Catalogue Visual Interface Grid -->
                <h2 style="color: #0f172a; margin: 0 0 20px 0; font-size: 22px; display: flex; align-items: center; gap: 8px;">🛍️ Showroom Catalogue</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px;">
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): 
                            $prodId = $product['id'] ?? 0;
                            $prodStock = intval($product['stock'] ?? 0);
                        ?>
                            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; min-height: 250px;">
                                <div>
                                    <span style="background: #f1f5f9; color: #475569; font-size: 10px; padding: 3px 6px; border-radius: 4px; font-weight: bold; text-transform: uppercase;">Product Asset</span>
                                    <h3 style="margin: 10px 0 6px 0; color: #0f172a; font-size: 16px; font-weight: bold;"><?= htmlspecialchars($product['name'] ?? 'Asset Item') ?></h3>
                                    <p style="color: #64748b; font-size: 13px; margin: 0 0 12px 0; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars($product['description'] ?? '') ?></p>
                                </div>
                                
                                <div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                        <span style="font-size: 18px; font-weight: 700; color: #0f172a;">$<?= number_format($product['price'] ?? 0, 2) ?></span>
                                        <span style="font-size: 12px; color: <?= $prodStock > 0 ? '#16a34a' : '#dc2626' ?>; font-weight: 600;">Stock: <?= $prodStock ?></span>
                                    </div>
                                    
                                    <form action="index.php" method="POST" style="margin: 0;">
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <input type="hidden" name="product_id" value="<?= $prodId ?>">
                                        <button type="submit" <?= $prodStock <= 0 ? 'disabled' : '' ?> style="width: 100%; background: #2563eb; color: white; border: none; padding: 9px; border-radius: 6px; font-weight: bold; font-size: 13px; cursor: pointer; opacity: <?= $prodStock <= 0 ? '0.5' : '1' ?>;">
                                            <?= $prodStock > 0 ? '➕ Add to Cart' : '🚫 Out of Stock' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #64748b; font-size: 14px; grid-column: 1/-1;">No storefront catalog assets available. Hit the generator button above to seed data records.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT SIDEBAR COMPONENT COLUMN -->
            <div>
                <!-- Enhanced Shopping Cart Calculator Component Card -->
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); padding: 24px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #f1f5f9; padding-bottom: 14px; margin-bottom: 16px;">
                        <h3 style="margin: 0; font-size: 18px; color: #0f172a; display: flex; align-items: center; gap: 8px;">🛒 Shopping Summary</h3>
                        <span style="background: #eff6ff; color: #2563eb; font-weight: bold; padding: 3px 8px; border-radius: 20px; font-size: 12px;">Active Cart</span>
                    </div>

                    <div style="display: grid; gap: 14px; margin-bottom: 20px; max-height: 300px; overflow-y: auto; padding-right: 4px;">
                        <?php 
                        $runningTotal = 0;
                        $cartItems = $db->query("SELECT c.quantity, p.name, p.price FROM cart_items c JOIN products p ON c.product_id = p.id WHERE c.cart_id = ?", [$cartId]);
                        
                        if (!empty($cartItems)):
                            foreach ($cartItems as $item): 
                                $itemSubtotal = $item['price'] * $item['quantity'];
                                $runningTotal += $itemSubtotal;
                        ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; border-bottom: 1px dashed #e2e8f0;">
                                <div>
                                    <h4 style="margin: 0 0 2px 0; color: #334155; font-size: 14px; font-weight: 600;"><?= htmlspecialchars($item['name']) ?></h4>
                                    <span style="color: #64748b; font-size: 12px;">$<?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?></span>
                                </div>
                                <span style="font-weight: 600; color: #0f172a; font-size: 14px;">$<?= number_format($itemSubtotal, 2) ?></span>
                            </div>
                        <?php 
                            endforeach; 
                        else:
                        ?>
                            <p style="color: #94a3b8; font-size: 13px; text-align: center; padding: 15px 0; margin: 0;">Your basket is completely empty.</p>
                        <?php endif; ?>
                    </div>

                    <div style="background: #f8fafc; padding: 14px; border-radius: 10px; margin-bottom: 20px; display: grid; gap: 6px;">
                        <div style="display: flex; justify-content: space-between; font-size: 13px; color: #64748b;">
                            <span>Subtotal</span>
                            <span>$<?= number_format($runningTotal, 2) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 16px; color: #0f172a; border-top: 1px solid #e2e8f0; padding-top: 8px; margin-top: 4px;">
                            <span>Total Balance:</span>
                            <span>$<?= number_format($runningTotal, 2) ?></span>
                        </div>
                    </div>

                    <div style="display: grid; gap: 10px;">
                        <form action="index.php" method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="checkout_stripe">
                            <button type="submit" style="width: 100%; background: #4f46e5; color: white; border: none; text-align: center; padding: 12px; border-radius: 8px; font-weight: bold; font-size: 14px; cursor: pointer; box-shadow: 0 2px 4px rgba(79, 70, 229, 0.15);">🔗 Proceed to Stripe Checkout</button>
                        </form>
                        

                        
                      
