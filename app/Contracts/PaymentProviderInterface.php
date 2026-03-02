<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Admin\Invoice;

/**
 * Pluggable payment provider interface.
 *
 * Implementations: StripeProvider (WP-17)
 */
interface PaymentProviderInterface
{
    /**
     * Create a hosted checkout session and return the URL to redirect the customer to.
     */
    public function createCheckoutSession(
        Invoice $invoice,
        string $successUrl,
        string $cancelUrl,
    ): string;

    /**
     * Verify the incoming webhook signature and process the event.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException  on invalid signature (400)
     */
    public function handleWebhook(string $rawPayload, string $signatureHeader): void;
}
