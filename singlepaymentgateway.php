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
     * Generate a secure Stripe Checkout URL
     * Amount must be in CENTS (e.g., 2000 = $20.00)
     */
    public function createCheckoutSession(int $amount, string $currency, string $successUrl, string $cancelUrl): string
    {
        try {
            $session = $this->stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'product_data' => [
                            'name' => 'Ecommerce Platform Purchase',
                        ],
                        'unit_amount' => $amount,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);

            return $session->url;
        } catch (ApiErrorException $e) {
            throw new \Exception("Stripe Error: " . $e->getMessage());
        }
    }
}
