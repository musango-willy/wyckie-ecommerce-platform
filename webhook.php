<?php
// --- STRIPE CRYPTOGRAPHIC WEBHOOK LISTENER ---

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Database.php';

use Wyckie\EcommercePlatform\Database;

// Explicitly toggle on clean json header responses
header('Content-Type: application/json');

// Pull private infrastructure credentials safely
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

$stripeWebhookSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($stripeWebhookSecret) || empty($payload) || empty($sigHeader)) {
    http_response_code(400);
    echo json_encode(['error' => 'Critical Error: Missing operational webhook credentials or payload parameters.']);
    exit();
}

try {
    // Verify the authenticity of the incoming request payload using Stripe's SDK signatures
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $stripeWebhookSecret);
} catch (\UnexpectedValueException $e) {
    // Handle invalid payload structural streams
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload signature body data stream.']);
    exit();
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Handle failed signature parsing (prevents malicious spoofing attempts)
    http_response_code(400);
    echo json_encode(['error' => 'Cryptographic signature mismatch verification failed.']);
    exit();
}

// Instantiate secure database connection map
try {
    $db = new Database(
        $_ENV['DB_HOST'] ?? '127.0.0.1',
        $_ENV['DB_NAME'] ?? 'ecommerce_db',
        $_ENV['DB_USER'] ?? 'root',
        $_ENV['DB_PASS'] ?? ''
    );
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error: Database persistence hook failed.']);
    exit();
}

// --- SECURE INCOMING EVENT ROUTING ---
switch ($event->type) {
    case 'checkout.session.completed':
        $session = $event->data->object;
        
        $stripeSessionId = $session->id;
        $customerEmail = $session->customer_details->email ?? 'guest@wyckie.local';
        $totalAmount = $session->amount_total / 100; // Convert back from cents to standard float decimal dollars
        
        // 1. Verify this order hasn't already been written into logs by index.php
        $existingOrder = $db->query("SELECT id FROM orders WHERE stripe_session_id = ? LIMIT 1", [$stripeSessionId]);
        
        if (empty($existingOrder)) {
            // 2. Insert complete verified transaction invoice metrics into history logs
            $db->query(
                "INSERT INTO orders (stripe_session_id, customer_email, total_amount, payment_status, created_at) VALUES (?, ?, ?, 'Paid', NOW())",
                [$stripeSessionId, $customerEmail, $totalAmount]
            );
            
            // 3. AUTOMATED INVENTORY REDUCTION PIPELINE (STOCKS MATCHING)
            // Query line items included within this Stripe checkout instance session container mapping
            try {
                \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
                $sessionWithLineItems = \Stripe\Checkout\Session::retrieve([
                    'id' => $stripeSessionId,
                    'expand' => ['line_items'],
                ]);
                
                $lineItems = $sessionWithLineItems->line_items->data ?? [];
                
                foreach ($lineItems as $item) {
                    $productName = $item->description;
                    $purchasedQty = intval($item->quantity);
                    
                    // Deduct stock limits natively by mapping descriptions securely
                    $db->query(
                        "UPDATE products SET stock = GREATEST(0, stock - ?) WHERE name = ?",
                        [$purchasedQty, $productName]
                    );
                }
            } catch (\Exception $stripeEx) {
                // Log stock lookup faults silently without dropping HTTP response flags
            }
        }
        break;

    default:
        // Gracefully ignore unhandled hook signals (e.g. charge.succeeded)
        break;
}

// Return confirmation acknowledgment signal back to Stripe cloud hubs
http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Event processed and system transaction maps synchronized.']);
