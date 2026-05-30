<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Pricing\Customer;
use Illuminate\Support\Facades\DB;

class SepaMandateService
{
    /**
     * Finds the most recent SEPA mandate for a customer.
     * Priority: Ninox-synced mandate → locally stored mandate.
     *
     * @return object{iban:string,account_holder:string|null,mandate_ref:string|null,mandate_date:string|null,source:string}|null
     */
    public function findForCustomer(Customer $customer): ?object
    {
        // 1. Ninox sync table (read-only, authoritative if present)
        if ($customer->ninox_kunden_id) {
            $ninox = DB::table('ninox_sepa_mandat')
                ->where('kunden', $customer->ninox_kunden_id)
                ->whereNotNull('iban')
                ->orderByDesc('ninox_updated_at')
                ->first();

            if ($ninox) {
                return (object) [
                    'iban'           => $ninox->iban,
                    'account_holder' => $ninox->name_zahlungspflichtiger,
                    'mandate_ref'    => $ninox->mandatsreferenz,
                    'mandate_date'   => $ninox->mandatsdatum,
                    'source'         => 'ninox',
                ];
            }
        }

        // 2. Locally stored mandate
        if ($customer->iban) {
            return (object) [
                'iban'           => $customer->iban,
                'account_holder' => $customer->iban_account_holder,
                'mandate_ref'    => $customer->sepa_mandate_ref,
                'mandate_date'   => $customer->sepa_mandate_date
                    ? (string) $customer->sepa_mandate_date
                    : null,
                'source'         => 'local',
            ];
        }

        return null;
    }

    /**
     * Stores a new SEPA mandate on the customer record.
     * Ninox data is never modified.
     */
    public function store(Customer $customer, string $iban, string $holderName): void
    {
        $ref = 'MANDAT-' . $customer->customer_number . '-' . now()->format('Ymd');

        $customer->update([
            'iban'                => $iban,
            'iban_account_holder' => $holderName,
            'sepa_mandate_ref'    => $ref,
            'sepa_mandate_date'   => today(),
        ]);
    }
}
