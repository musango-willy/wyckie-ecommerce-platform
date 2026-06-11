<?php

namespace Wyckie\EcommercePlatform;

use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

class PaymentGateway
{
    private StripeClient $stripe;

    /**
     * Initialize the Gateway with your Stripe Secret Key
     */
    public function __construct(string $secretKey)
    {
        $this->stripe = new StripeClient($secretKey);
    }

    /**
     * Create a secure hosted Stripe Checkout Session URL for a single item
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

    /**
     * Generate a multi-item secure Stripe Checkout Session URL for carts
     * 
     * @param array $lineItems Structured array containing prices, names, and quantities
     */
    public function createCartCheckoutSession(array $lineItems, string $successUrl, string $cancelUrl): string
    {
        try {
            $session = $this->stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);

            return $session->url;
        } catch (ApiErrorException $e) {
            throw new \Exception("Stripe Cart Error: " . $e->getMessage());
        }
    }
}
