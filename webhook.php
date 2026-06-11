<?php
require __DIR__ . '/vendor/autoload.php';

// 1. Initialize environment configurations safely
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

use Wyckie\EcommercePlatform\Database;

// 2. Capture the raw POST payload payload and security headers from Stripe
$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpointSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';

try {
    $db = new Database($_ENV['DB_HOST'], $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
    
    // 3. Construct and verify the authenticity of the event signature
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);

    // 4. Handle the specific successful checkout event sequence
    if ($event->type === 'checkout.session.completed') {
        $session = $event->data->object;

        $stripeSessionId = $session->id;
        $customerEmail = $session->customer_details->email ?? 'Guest';
        $totalAmount = $session->amount_total / 100; // Convert cents back to standard dollars

        // Log the successful transaction as a real row entry inside MySQL
        $db->query(
            "INSERT INTO orders (stripe_session_id, customer_email, total_amount, payment_status) 
             VALUES (?, ?, ?, 'Paid') 
             ON DUPLICATE KEY UPDATE payment_status = 'Paid'",
            [$stripeSessionId, $customerEmail, $totalAmount]
        );

        // Optional: Extract cart details or handle email generation flags here
    }

    // Respond with a 200 HTTP success code to notify Stripe the hook processed perfectly
    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (\UnexpectedValueException $e) {
    // Catches invalid payloads
    http_response_code(400);
    exit();
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Catches invalid signatures or security configuration mismatches
    http_response_code(400);
    exit();
} catch (\Exception $e) {
    // Catches standard script execution issues
    http_response_code(500);
    exit();
}
