<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\Contact;
use App\Models\Pricing\Customer;
use App\Models\Supplier\Supplier;

class AssignmentService
{
    /**
     * Try to assign a communication based on the sender's email address.
     *
     * Returns an array with:
     *   communicable_type  — 'App\Models\Pricing\Customer' | 'App\Models\Supplier\Supplier' | null
     *   communicable_id    — int | null
     *   sender_contact_id  — int | null
     *   dim_contact        — 0–100
     *   dim_org            — 0–100
     */
    public function assign(string $fromAddress): array
    {
        $email = strtolower(trim($fromAddress));

        $result = [
            'communicable_type' => null,
            'communicable_id'   => null,
            'sender_contact_id' => null,
            'dim_contact'       => 0,
            'dim_org'           => 0,
        ];

        if (empty($email)) {
            return $result;
        }

        // 1. Check contacts table (polymorphic — attaches to Customer or Supplier)
        $contact = Contact::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($contact) {
            $result['sender_contact_id'] = $contact->id;
            $result['dim_contact']       = 80;

            // Derive org from contactable
            if ($contact->contactable_type && $contact->contactable_id) {
                $result['communicable_type'] = $contact->contactable_type;
                $result['communicable_id']   = $contact->contactable_id;
                $result['dim_org']           = 80;
            }
            return $result;
        }

        // 2. Check customers.email directly
        $customer = Customer::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($customer) {
            $result['communicable_type'] = Customer::class;
            $result['communicable_id']   = $customer->id;
            $result['dim_org']           = 70;
            return $result;
        }

        // 3. Check suppliers.email directly
        $supplier = Supplier::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($supplier) {
            $result['communicable_type'] = Supplier::class;
            $result['communicable_id']   = $supplier->id;
            $result['dim_org']           = 70;
            return $result;
        }

        // 4. Domain-level fallback — check customers/suppliers whose email shares the domain
        // Öffentliche E-Mail-Provider werden ausgeschlossen, da ein Domain-Match dort sinnlos ist.
        $publicDomains = [
            'gmail.com', 'googlemail.com',
            'gmx.de', 'gmx.net', 'gmx.at', 'gmx.ch',
            'web.de', 't-online.de', 'freenet.de', 'arcor.de',
            'yahoo.com', 'yahoo.de', 'yahoo.co.uk',
            'hotmail.com', 'hotmail.de', 'live.de', 'live.com',
            'outlook.com', 'outlook.de',
            'icloud.com', 'me.com', 'mac.com',
            'aol.com',
        ];

        $atPos = strpos($email, '@');
        if ($atPos !== false) {
            $domain = substr($email, $atPos + 1);

            if (!in_array($domain, $publicDomains, true)) {
                $domainCustomer = Customer::whereRaw('LOWER(email) LIKE ?', ["%@{$domain}"])->first();
                if ($domainCustomer) {
                    $result['communicable_type'] = Customer::class;
                    $result['communicable_id']   = $domainCustomer->id;
                    $result['dim_org']           = 40; // lower confidence — domain match only
                    return $result;
                }

                $domainSupplier = Supplier::whereRaw('LOWER(email) LIKE ?', ["%@{$domain}"])->first();
                if ($domainSupplier) {
                    $result['communicable_type'] = Supplier::class;
                    $result['communicable_id']   = $domainSupplier->id;
                    $result['dim_org']           = 40;
                    return $result;
                }
            }
        }

        return $result;
    }
}
