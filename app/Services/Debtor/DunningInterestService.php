<?php

declare(strict_types=1);

namespace App\Services\Debtor;

use App\Models\Admin\LexofficeVoucher;
use App\Models\Pricing\AppSetting;
use App\Models\Pricing\Customer;
use Carbon\Carbon;

/**
 * Computes statutory late-payment interest and B2B flat fees.
 *
 * Legal basis (Germany):
 *   § 288 BGB — Verzugszinsen
 *   Consumer (B2C): Basiszinssatz + 5 Prozentpunkte
 *   Commercial (B2B): Basiszinssatz + 8 Prozentpunkte
 *   B2B flat fee: 40 EUR per invoice (§ 288 Abs. 5 BGB) — only when enabled
 *
 * Zinsen fallen NICHT bei Stufe 1 (Zahlungserinnerung) an.
 * Zinsen werden erst ab Stufe 2 berechnet und ausgewiesen.
 *
 * Formel: Zinsen = Hauptforderung × (Jahreszinssatz / 100 / 365) × Verzugstage
 * Jahreszinssatz in Basispunkten (bps): 10.000 bps = 100 %
 */
class DunningInterestService
{
    // B2B flat fee per invoice: 40 EUR = 40_000_000 milli-cents
    public const B2B_FLAT_FEE_MILLI = 40_000_000;

    public function isInterestEnabled(): bool
    {
        return AppSetting::get('dunning.interest_enabled', '0') === '1';
    }

    public function isFlatFeeEnabled(): bool
    {
        return AppSetting::get('dunning.b2b_flat_fee_enabled', '0') === '1';
    }

    /**
     * Annual interest rate in basis points for a customer.
     * 10.000 bps = 100 %; 1.162 bps = 11,62 %
     */
    public function annualRateBps(Customer $customer): int
    {
        $baseBps = (int) AppSetting::get('dunning.base_rate_bps', '362'); // z.B. 362 = 3,62 %
        $addBps  = $customer->isB2B() ? 800 : 500;                       // 8 pp oder 5 pp

        return $baseBps + $addBps;
    }

    /**
     * Compute accrued interest for a single voucher in milli-cents.
     *
     * Zinsen = open_amount × (annualBps / 10.000) / 365 × Verzugstage
     *
     * Nur aufrufen wenn dunningLevel >= 2 — bei Stufe 1 (Erinnerung)
     * werden keine Zinsen berechnet.
     */
    public function computeVoucherInterest(LexofficeVoucher $voucher, Customer $customer): int
    {
        if (! $this->isInterestEnabled()) {
            return 0;
        }

        if (! $voucher->due_date) {
            return 0;
        }

        $daysOverdue = (int) Carbon::parse($voucher->due_date)->diffInDays(now(), false);
        if ($daysOverdue <= 0) {
            return 0;
        }

        $annualBps = $this->annualRateBps($customer);

        // Zinsen = Hauptforderung × Jahresrate × (Tage / 365)
        // annualBps / 10_000 wandelt Basispunkte in dezimale Rate um
        $interest = (int) round((int) $voucher->open_amount * ($annualBps / 10_000) / 365 * $daysOverdue);

        return max(0, $interest);
    }

    /**
     * Compute interest breakdown per voucher — liefert Array für Anzeige.
     *
     * @param  iterable<LexofficeVoucher> $vouchers
     * @return array<int, array{voucher_id: int, voucher_number: string, days_overdue: int, open_milli: int, interest_milli: int, annual_rate_pct: string}>
     */
    public function computeBreakdown(iterable $vouchers, Customer $customer): array
    {
        $annualBps  = $this->annualRateBps($customer);
        $annualPct  = number_format($annualBps / 100, 2, ',', '.') . ' %';
        $breakdown  = [];

        foreach ($vouchers as $voucher) {
            if (! $voucher->due_date) {
                continue;
            }

            $daysOverdue = (int) Carbon::parse($voucher->due_date)->diffInDays(now(), false);
            $interest    = $daysOverdue > 0 && $this->isInterestEnabled()
                ? (int) round((int) $voucher->open_amount * ($annualBps / 10_000) / 365 * $daysOverdue)
                : 0;

            $breakdown[] = [
                'voucher_id'     => $voucher->id,
                'voucher_number' => $voucher->voucher_number ?? '-',
                'due_date'       => $voucher->due_date->format('d.m.Y'),
                'days_overdue'   => $daysOverdue,
                'open_milli'     => (int) $voucher->open_amount,
                'interest_milli' => max(0, $interest),
                'annual_rate_pct'=> $annualPct,
            ];
        }

        return $breakdown;
    }

    /**
     * B2B-Verzugspauschale: 40 € je Rechnung, NUR ab Stufe 2, NUR B2B.
     * Nicht bei Stufe 1 (Zahlungserinnerung).
     */
    public function computeFlatFee(Customer $customer, int $voucherCount): int
    {
        if (! $this->isFlatFeeEnabled()) {
            return 0;
        }

        if (! $customer->isB2B()) {
            return 0;
        }

        return self::B2B_FLAT_FEE_MILLI * $voucherCount;
    }
}
