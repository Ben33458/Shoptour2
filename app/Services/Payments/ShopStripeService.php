<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Orders\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * PROJ-4: Stripe integration for shop checkout orders.
 *
 * Creates a Stripe Checkout Session for customer-facing orders
 * (as opposed to StripeProvider which handles invoice-based payments).
 *
 * No Stripe SDK dependency -- uses Laravel HTTP client.
 */
class ShopStripeService
{
    private string $secretKey;
    private string $currency;

    public function __construct()
    {
        $this->secretKey = (string) config('services.stripe.secret_key', '');
        $this->currency  = (string) config('services.stripe.currency', 'eur');
    }

    /**
     * Create a Stripe Checkout Session for the given order.
     *
     * @return string  The hosted checkout URL to redirect the customer to
     *
     * @throws RuntimeException when Stripe API call fails
     */
    public function createCheckoutSession(Order $order, string $successUrl, string $cancelUrl): string
    {
        if ($this->secretKey === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        // Total in cents (Stripe uses integer cents, we store milli-cents)
        $totalMilli = $order->total_gross_milli + $order->total_pfand_brutto_milli;
        $amountCents = (int) round($totalMilli / 1_000);

        $params = [
            'mode'                                                      => 'payment',
            'currency'                                                  => $this->currency,
            'success_url'                                               => $successUrl,
            'cancel_url'                                                => $cancelUrl,
            'line_items[0][price_data][currency]'                       => $this->currency,
            'line_items[0][price_data][unit_amount]'                    => $amountCents,
            'line_items[0][price_data][product_data][name]'             => 'Bestellung ' . $order->order_number,
            'line_items[0][quantity]'                                   => 1,
            'metadata[order_id]'                                        => $order->id,
            'metadata[order_number]'                                    => $order->order_number,
            'client_reference_id'                                       => (string) $order->id,
        ];

        $email = $order->customer?->email;
        if ($email) {
            $params['customer_email'] = $email;
        }

        $response = Http::withBasicAuth($this->secretKey, '')
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', $params);

        if ($response->failed()) {
            throw new RuntimeException(
                'Stripe checkout session creation failed: ' . $response->body()
            );
        }

        $session = $response->json();

        // Store the session ID on the order for webhook matching
        $order->update(['payment_reference' => 'stripe:' . $session['id']]);

        return $session['url'];
    }
}
