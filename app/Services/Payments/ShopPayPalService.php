<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Orders\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * PROJ-4: PayPal integration for shop checkout orders.
 *
 * Uses PayPal REST API v2 (Orders API) with redirect-based checkout.
 * No PayPal SDK dependency -- uses Laravel HTTP client directly.
 *
 * Flow:
 *   1. Create PayPal order -> get approval URL
 *   2. Customer redirects to PayPal to approve
 *   3. On return (success callback), capture the payment
 *   4. On cancel, mark order as cancelled
 */
class ShopPayPalService
{
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;

    public function __construct()
    {
        $this->clientId     = (string) config('services.paypal.client_id', '');
        $this->clientSecret = (string) config('services.paypal.client_secret', '');

        $sandbox = config('services.paypal.sandbox', true);
        $this->baseUrl = $sandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * Create a PayPal order and return the approval URL.
     *
     * @return string  The PayPal approval URL to redirect the customer to
     *
     * @throws RuntimeException when PayPal API call fails
     */
    public function createOrder(Order $order, string $returnUrl, string $cancelUrl): string
    {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new RuntimeException('PayPal credentials are not configured.');
        }

        $accessToken = $this->getAccessToken();

        // Convert milli-cents to EUR with 2 decimal places
        $totalMilli = $order->total_gross_milli + $order->total_pfand_brutto_milli;
        $totalEur = number_format($totalMilli / 1_000_000, 2, '.', '');

        $response = Http::withToken($accessToken)
            ->post($this->baseUrl . '/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => (string) $order->id,
                        'description'  => 'Bestellung ' . $order->order_number,
                        'amount' => [
                            'currency_code' => 'EUR',
                            'value'         => $totalEur,
                        ],
                    ],
                ],
                'application_context' => [
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                    'brand_name' => 'Kolabri Getraenke',
                    'user_action' => 'PAY_NOW',
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'PayPal order creation failed: ' . $response->body()
            );
        }

        $data = $response->json();
        $paypalOrderId = $data['id'];

        // Store PayPal order ID on the order for callback matching
        $order->update(['payment_reference' => 'paypal:' . $paypalOrderId]);

        // Find the approval link
        $approvalUrl = collect($data['links'] ?? [])
            ->firstWhere('rel', 'approve')['href'] ?? null;

        if ($approvalUrl === null) {
            throw new RuntimeException('PayPal did not return an approval URL.');
        }

        return $approvalUrl;
    }

    /**
     * Capture a previously approved PayPal order.
     *
     * @param  string  $paypalOrderId  The PayPal order ID from the return URL
     * @return bool    True if capture succeeded
     */
    public function captureOrder(string $paypalOrderId): bool
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->post($this->baseUrl . "/v2/checkout/orders/{$paypalOrderId}/capture", []);

        if ($response->failed()) {
            return false;
        }

        $data = $response->json();

        return ($data['status'] ?? '') === 'COMPLETED';
    }

    /**
     * Obtain an OAuth2 access token from PayPal.
     *
     * @throws RuntimeException when authentication fails
     */
    private function getAccessToken(): string
    {
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post($this->baseUrl . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'PayPal authentication failed: ' . $response->body()
            );
        }

        return $response->json('access_token');
    }
}
