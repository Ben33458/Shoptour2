<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Models\Pricing\Customer;
use Illuminate\Database\Eloquent\Collection;

class LexofficeContactMatcher
{
    /** @var Collection<int, Customer>|null */
    private ?Collection $customers = null;

    /**
     * Suggest a local customer for a given Lexoffice contact name.
     * Returns [customer, confidence (0–100)] or null if no match found (score < 40).
     *
     * @return array{customer: Customer, confidence: int}|null
     */
    public function suggestCustomer(string $contactName): ?array
    {
        if ($contactName === '') {
            return null;
        }

        $customers = $this->getCustomers();
        $contactLower = mb_strtolower(trim($contactName));

        $best = null;
        $bestScore = 0;

        foreach ($customers as $customer) {
            $score = $this->score($contactLower, $customer);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $customer;
            }
        }

        if ($bestScore < 40 || $best === null) {
            return null;
        }

        return ['customer' => $best, 'confidence' => min(100, $bestScore)];
    }

    private function score(string $contactLower, Customer $customer): int
    {
        $score = 0;

        $company = mb_strtolower(trim((string) ($customer->company_name ?? '')));
        $name    = mb_strtolower(trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')));

        // Check company field first
        if ($company !== '') {
            if (str_contains($contactLower, $company)) {
                $score += 50;
            } elseif (str_contains($company, $contactLower)) {
                $score += 40;
            } else {
                similar_text($contactLower, $company, $pct);
                if ($pct >= 70) {
                    $score += 30;
                } elseif ($pct >= 50) {
                    $score += 15;
                }

                if (levenshtein($contactLower, $company) <= 3) {
                    $score += 20;
                }
            }
        }

        // Also check customer name
        if ($name !== '' && $name !== $company) {
            if (str_contains($contactLower, $name)) {
                $score += 25;
            } elseif (str_contains($name, $contactLower)) {
                $score += 20;
            }
        }

        return $score;
    }

    /** @return Collection<int, Customer> */
    private function getCustomers(): Collection
    {
        if ($this->customers === null) {
            $this->customers = Customer::where('active', true)
                ->select(['id', 'company_name', 'first_name', 'last_name'])
                ->get();
        }

        return $this->customers;
    }
}
