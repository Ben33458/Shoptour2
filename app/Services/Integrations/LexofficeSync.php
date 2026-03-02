<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Models\Admin\Invoice;
use App\Models\Pricing\Customer;

/**
 * Builds Lexoffice payloads from domain models and delegates to LexofficeClient.
 *
 * On success: updates lexoffice_contact_id / lexoffice_voucher_id on the model.
 * On failure: stores the error message in lexoffice_sync_error and rethrows.
 */
class LexofficeSync
{
    public function __construct(
        private readonly LexofficeClient $client,
    ) {}

    /**
     * Push a customer as a Lexoffice contact.
     * Stores returned contact ID on the Customer model.
     */
    public function syncCustomer(Customer $customer): void
    {
        $payload = [
            'roles' => ['customer' => (object) []],
            'person' => [
                'firstName' => $customer->first_name ?? '',
                'lastName'  => $customer->last_name  ?? '',
            ],
        ];

        if ($customer->email) {
            $payload['emailAddresses'] = [
                'business' => [$customer->email],
            ];
        }

        try {
            $result = $this->client->pushContact($payload);

            $customer->update([
                'lexoffice_contact_id' => $result['id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Push a finalized invoice as a Lexoffice voucher.
     * Stores returned voucher ID + timestamp on the Invoice model.
     * On failure: stores error message and rethrows.
     */
    public function syncInvoice(Invoice $invoice): void
    {
        $invoice->loadMissing('items', 'order.customer');

        $lineItems = $invoice->items->map(function ($item) {
            return [
                'type'        => 'custom',
                'name'        => $item->description,
                'quantity'    => $item->qty,
                'unitName'    => 'St\xc3\xbcck',
                'unitPrice'   => [
                    'currency'         => 'EUR',
                    'netAmount'        => round($item->unit_price_net_milli / 1_000_000, 6),
                    'taxRatePercentage' => round($item->tax_rate_basis_points / 10_000, 2),
                ],
                'discountPercentage' => 0,
            ];
        })->values()->all();

        $payload = [
            'type'        => 'salesinvoice',
            'voucherDate' => ($invoice->finalized_at ?? now())->toIso8601String(),
            'voucherNumber' => $invoice->invoice_number,
            'lineItems'   => $lineItems,
            'taxConditions' => ['taxType' => 'net'],
            'totalPrice'  => [
                'currency' => 'EUR',
            ],
        ];

        // Link to Lexoffice contact if available
        $contactId = $invoice->order?->customer?->lexoffice_contact_id;
        if ($contactId) {
            $payload['address'] = ['contactId' => $contactId];
        }

        try {
            $result = $this->client->pushVoucher($payload);

            $invoice->update([
                'lexoffice_voucher_id' => $result['id'] ?? null,
                'lexoffice_synced_at'  => now(),
                'lexoffice_sync_error' => null,
            ]);
        } catch (\Throwable $e) {
            $invoice->update(['lexoffice_sync_error' => $e->getMessage()]);
            throw $e;
        }
    }
}
