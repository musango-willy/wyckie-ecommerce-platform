<?php

namespace Wyckie\EcommercePlatform;

use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

class PaymentGateway
{
    private StripeClient $stripe;

    public function __construct(string $secretKey)
    {
        // Initializes the Stripe SDK client
        $this->stripe = new StripeClient($secretKey);
    }

        /**
     * Generate a secure Stripe Checkout URL supporting dynamic multi-item baskets
     */
    public function createCartCheckoutSession(array $cartItems, string $successUrl, string $cancelUrl): string
    {
        try {
            $lineItems = [];
            
            // Map each product row data into Stripe's line_items blueprint array structures
            foreach ($cartItems as $item) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => $item['name'] ?? 'Store Item',
                        ],
                        'unit_amount' => intval(($item['price'] ?? 0) * 100), // Converted to cents natively inside wrapper
                    ],
                    'quantity' => intval($item['quantity'] ?? 1),
                ];
            }

            if (empty($lineItems)) {
                throw new \Exception("Cannot initialize gateway routing with an empty line items tray.");
            }

            $session = $this->stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);

            return $session->url;
        } catch (ApiErrorException $e) {
            throw new \Exception("Stripe Gateway Error: " . $e->getMessage());
        }
    }
}
