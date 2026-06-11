<?php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

use Wyckie\EcommercePlatform\Database;

$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpointSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';

try {
    $db = new Database($_ENV['DB_HOST'], $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
    
    // Construct the verified event signature from Stripe's cloud payload
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);

    if ($event->type === 'checkout.session.completed') {
        $session = $event->data->object;

        $stripeSessionId = $session->id;
        $customerEmail = $session->customer_details->email ?? 'Guest';
        $totalAmount = $session->amount_total / 100;

        // 1. Log the main transaction record into the database rows
        $db->query(
            "INSERT INTO orders (stripe_session_id, customer_email, total_amount, payment_status) 
             VALUES (?, ?, ?, 'Paid') 
             ON DUPLICATE KEY UPDATE payment_status = 'Paid'",
            [$stripeSessionId, $customerEmail, $totalAmount]
        );

        // 2. Fetch the detailed line items list directly from the payment session
        $stripe = new \Stripe\StripeClient($_ENV['STRIPE_SECRET_KEY']);
        $lineItems = $stripe->checkout->sessions->allLineItems($stripeSessionId);

        // 3. Loop through purchased items and decrement inventory tables accordingly
        foreach ($lineItems->data as $item) {
            $productName = $item->description; 
            $purchasedQty = intval($item->quantity);

            // Execute an operational reduction query matching product rows natively
            $db->query(
                "UPDATE products 
                 SET stock_quantity = GREATEST(0, stock_quantity - ?) 
                 WHERE name = ?", 
                [$purchasedQty, $productName]
            );
        }
    }

    http_response_code(200);
    echo json_encode(['status' => 'inventory updated']);

} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    exit();
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit();
} catch (\Exception $e) {
    http_response_code(500);
    exit();
}



