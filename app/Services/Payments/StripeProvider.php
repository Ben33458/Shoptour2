<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Contracts\PaymentProviderInterface;
use App\Models\Admin\DeferredTask;
use App\Models\Admin\Invoice;
use App\Models\Admin\Payment;
use App\Models\Orders\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe payment provider (Checkout Sessions API).
 *
 * No Stripe SDK dependency — uses Laravel HTTP client + manual HMAC validation.
 */
class StripeProvider implements PaymentProviderInterface
{
    private string $secretKey;
    private string $webhookSecret;
    private string $currency;

    public function __construct()
    {
        $this->secretKey     = (string) config('services.stripe.secret_key', '');
        $this->webhookSecret = (string) config('services.stripe.webhook_secret', '');
        $this->currency      = (string) config('services.stripe.currency', 'eur');
    }

    /**
     * Create a Stripe Checkout Session and record a pending Payment.
     * Returns the hosted checkout URL.
     */
    public function createCheckoutSession(
        Invoice $invoice,
        string $successUrl,
        string $cancelUrl,
    ): string {
        $invoice->loadMissing('order.customer');

        // total_gross_milli -> milli-cents / 1000 = cents (Stripe uses integer cents)
        $amountCents = (int) round($invoice->total_gross_milli / 1_000);

        $params = [
            'mode'                 => 'payment',
            'currency'             => $this->currency,
            'success_url'          => $successUrl,
            'cancel_url'           => $cancelUrl,
            'line_items[0][price_data][currency]'                    => $this->currency,
            'line_items[0][price_data][unit_amount]'                 => $amountCents,
            'line_items[0][price_data][product_data][name]'          => 'Rechnung ' . $invoice->invoice_number,
            'line_items[0][quantity]'                                => 1,
        ];

        $email = $invoice->order?->customer?->email;
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
        $sessionId = $session['id'];

        // Record a pending payment for idempotent webhook processing
        Payment::create([
            'invoice_id'     => $invoice->id,
            'payment_method' => Payment::METHOD_CARD,
            'provider'       => 'stripe',
            'provider_ref'   => $sessionId,
            'status'         => Payment::STATUS_PENDING,
            'amount_milli'   => $invoice->total_gross_milli,
            'paid_at'        => now(), // placeholder; updated to real timestamp on webhook
        ]);

        return $session['url'];
    }

    /**
     * Verify Stripe webhook signature and process supported events.
     *
     * Stripe-Signature header format: t=TIMESTAMP,v1=HMAC,...
     */
    public function handleWebhook(string $rawPayload, string $signatureHeader): void
    {
        // Parse timestamp and v1 signature
        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $val] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$key] = $val;
        }

        $timestamp = $parts['t'] ?? '';
        $v1        = $parts['v1'] ?? '';

        if ($timestamp === '' || $v1 === '') {
            abort(400, 'Missing Stripe-Signature header components.');
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $this->webhookSecret);

        if (! hash_equals($expected, $v1)) {
            abort(400, 'Invalid Stripe webhook signature.');
        }

        $event = json_decode($rawPayload, true);
        $type  = $event['type'] ?? '';

        match ($type) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event),
            default                      => null, // Unknown events are silently ignored
        };
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function handleCheckoutSessionCompleted(array $event): void
    {
        $session   = $event['data']['object'] ?? [];
        $sessionId = $session['id'] ?? null;

        if (! $sessionId) {
            return;
        }

        // PROJ-4: Check if this is a shop checkout order (payment_reference = "stripe:<sessionId>")
        $order = Order::where('payment_reference', 'stripe:' . $sessionId)->first();
        if ($order !== null) {
            $this->handleShopOrderPayment($order);
            return;
        }

        // Legacy: invoice-based payment flow
        $payment = Payment::where('provider_ref', $sessionId)->first();

        if ($payment === null) {
            // Webhook arrived before checkout was recorded — create a new record
            // (best-effort; invoice matching would require metadata in the session)
            return;
        }

        // Idempotency: ignore if already paid
        if ($payment->status === Payment::STATUS_PAID) {
            return;
        }

        $stripeAmountCents = $session['amount_total'] ?? null;
        $amountMilli = $stripeAmountCents !== null
            ? (int) ($stripeAmountCents * 1_000)
            : $payment->amount_milli;

        $payment->update([
            'status'       => Payment::STATUS_PAID,
            'amount_milli' => $amountMilli,
            'raw_json'     => json_encode($event),
            'paid_at'      => now(),
        ]);
    }

    /**
     * PROJ-4: Confirm a shop order after successful Stripe payment.
     */
    private function handleShopOrderPayment(Order $order): void
    {
        // Idempotency: ignore if already confirmed
        if ($order->status !== Order::STATUS_PENDING) {
            return;
        }

        $order->update(['status' => Order::STATUS_CONFIRMED]);

        // Queue confirmation email
        DeferredTask::create([
            'type'         => 'email.order_confirmation',
            'payload_json' => json_encode([
                'order_id'    => $order->id,
                'customer_id' => $order->customer_id,
            ]),
            'status'       => DeferredTask::STATUS_PENDING,
            'attempts'     => 0,
            'max_attempts' => 3,
        ]);
    }
}