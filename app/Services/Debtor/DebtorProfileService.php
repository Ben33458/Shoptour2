<?php

declare(strict_types=1);

namespace App\Services\Debtor;

use App\Models\Admin\LexofficePayment;
use App\Models\Admin\LexofficeVoucher;
use App\Models\Debtor\DebtorNote;
use App\Models\Orders\Order;
use App\Models\Pricing\Customer;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes debtor profile metrics for a customer.
 *
 * All logic is transparent and simple — no black-box scoring.
 * Designed to give an operations team a practical, daily-use snapshot.
 */
class DebtorProfileService
{
    /**
     * Build the full debtor profile DTO for the given customer.
     * Returns an associative array (easy to pass to views).
     */
    public function getProfile(Customer $customer): array
    {
        $openVouchers = LexofficeVoucher::where('customer_id', $customer->id)
            ->where('voucher_type', LexofficeVoucher::TYPE_SALES_INVOICE)
            ->whereIn('voucher_status', [LexofficeVoucher::STATUS_OPEN, LexofficeVoucher::STATUS_OVERDUE])
            ->get();

        $openCreditNotes = LexofficeVoucher::where('customer_id', $customer->id)
            ->where('voucher_type', LexofficeVoucher::TYPE_CREDIT_NOTE)
            ->whereIn('voucher_status', [LexofficeVoucher::STATUS_OPEN, LexofficeVoucher::STATUS_OVERDUE])
            ->get();

        $openTotal    = $openVouchers->sum('open_amount') - $openCreditNotes->sum('open_amount');
        $openCount    = $openVouchers->count();
        $oldestDue    = $openVouchers->min('due_date');
        $daysOverdue  = $oldestDue ? (int) Carbon::parse($oldestDue)->diffInDays(now(), false) : 0;
        $maxLevel     = $openVouchers->max('dunning_level') ?? 0;

        $lastDunned = $openVouchers->filter(fn ($v) => $v->last_dunned_at !== null)
            ->sortByDesc('last_dunned_at')
            ->first()?->last_dunned_at;

        // Last payment from Lexoffice
        $lastPayment = LexofficePayment::whereHas('voucher', fn ($q) => $q->where('customer_id', $customer->id))
            ->orderByDesc('payment_date')
            ->value('payment_date');

        // Open active notes that block dunning
        $hasHold        = (bool) $customer->debt_hold;
        $hasDispute     = DebtorNote::where('customer_id', $customer->id)
            ->where('status', DebtorNote::STATUS_OPEN)
            ->whereIn('type', [DebtorNote::TYPE_DISPUTE, DebtorNote::TYPE_PAYMENT_PROMISE])
            ->exists();

        $orderRhythm  = $this->computeOrderRhythm($customer);
        $paymentBehavior = $this->computePaymentBehavior($customer);
        $riskLevel    = $this->computeRiskLevel(
            openTotal: $openTotal,
            daysOverdue: $daysOverdue,
            paymentScore: $paymentBehavior['score'],
            pattern: $paymentBehavior['pattern'],
        );

        return [
            // Open items summary
            'open_total_milli'  => $openTotal,
            'open_count'        => $openCount,
            'oldest_due_date'   => $oldestDue,
            'days_overdue'      => $daysOverdue,
            'dunning_level'     => $maxLevel,
            'has_hold'          => $hasHold || $hasDispute,
            'last_dunned_at'    => $lastDunned,
            'last_payment_date' => $lastPayment,

            // Order behavior
            'order_rhythm'      => $orderRhythm,

            // Payment behavior
            'payment_behavior'  => $paymentBehavior,

            // Risk
            'risk_level'        => $riskLevel,
        ];
    }

    /**
     * Determine the order rhythm from recent completed orders.
     *
     * Returns: weekly | biweekly | monthly | irregular | unknown
     */
    public function computeOrderRhythm(Customer $customer): array
    {
        $orders = Order::where('customer_id', $customer->id)
            ->whereIn('status', ['confirmed', 'shipped', 'delivered'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->pluck('created_at')
            ->sortDesc()
            ->values();

        if ($orders->count() < 2) {
            return [
                'rhythm'          => 'unknown',
                'label'           => 'Unbekannt',
                'avg_interval'    => null,
                'last_order'      => $orders->first()?->toDateString(),
                'next_expected'   => null,
                'days_to_next'    => null,
            ];
        }

        // Compute intervals in days
        $intervals = [];
        for ($i = 0; $i < $orders->count() - 1; $i++) {
            $intervals[] = (int) $orders[$i + 1]->diffInDays($orders[$i], false);
        }

        $avgInterval = (int) round(array_sum($intervals) / count($intervals));

        $rhythm = match (true) {
            $avgInterval <= 10  => 'weekly',
            $avgInterval <= 17  => 'biweekly',
            $avgInterval <= 40  => 'monthly',
            default             => 'irregular',
        };

        $rhythmLabel = match ($rhythm) {
            'weekly'    => 'Wöchentlich',
            'biweekly'  => '14-tägig',
            'monthly'   => 'Monatlich',
            default     => 'Unregelmäßig',
        };

        $lastOrder    = $orders->first();
        $nextExpected = $lastOrder?->copy()->addDays($avgInterval);
        $daysToNext   = $nextExpected ? (int) now()->diffInDays($nextExpected, false) : null;

        return [
            'rhythm'        => $rhythm,
            'label'         => $rhythmLabel,
            'avg_interval'  => $avgInterval,
            'last_order'    => $lastOrder?->toDateString(),
            'next_expected' => $nextExpected?->toDateString(),
            'days_to_next'  => $daysToNext,
        ];
    }

    /**
     * Compute payment behavior from Lexoffice payment history.
     *
     * Returns score label and pattern classification.
     */
    public function computePaymentBehavior(Customer $customer): array
    {
        // Get recent paid vouchers for this customer with their payment dates
        $vouchers = LexofficeVoucher::where('customer_id', $customer->id)
            ->where('voucher_type', LexofficeVoucher::TYPE_SALES_INVOICE)
            ->whereIn('voucher_status', [
                LexofficeVoucher::STATUS_PAID,
                LexofficeVoucher::STATUS_PAIDOFF,
            ])
            ->whereNotNull('due_date')
            ->with('payments')
            ->orderByDesc('voucher_date')
            ->limit(20)
            ->get();

        if ($vouchers->isEmpty()) {
            return [
                'avg_delay_days' => null,
                'score'          => 'unknown',
                'score_label'    => 'Keine Daten',
                'pattern'        => 'unknown',
                'pattern_label'  => 'Unbekannt',
            ];
        }

        $delays     = [];
        $lateCount  = 0;

        foreach ($vouchers as $voucher) {
            // Use the earliest payment date for this voucher
            $firstPayment = $voucher->payments->sortBy('payment_date')->first();
            if (! $firstPayment) {
                continue;
            }

            $delay = (int) Carbon::parse($voucher->due_date)
                ->diffInDays(Carbon::parse($firstPayment->payment_date), false);
            $delays[] = $delay;

            if ($delay > 3) { // grace period of 3 days
                $lateCount++;
            }
        }

        if (empty($delays)) {
            return [
                'avg_delay_days' => null,
                'score'          => 'unknown',
                'score_label'    => 'Keine Daten',
                'pattern'        => 'unknown',
                'pattern_label'  => 'Unbekannt',
            ];
        }

        $avgDelay = (int) round(array_sum($delays) / count($delays));
        $total    = count($delays);
        $lateRatio = $total > 0 ? $lateCount / $total : 0;

        // Score based on average delay
        $score = match (true) {
            $avgDelay <= 0  => 'sehr_gut',
            $avgDelay <= 7  => 'gut',
            $avgDelay <= 21 => 'mittel',
            $avgDelay <= 45 => 'schlecht',
            default         => 'kritisch',
        };

        $scoreLabel = match ($score) {
            'sehr_gut' => 'Sehr gut',
            'gut'      => 'Gut',
            'mittel'   => 'Mittel',
            'schlecht' => 'Schlecht',
            default    => 'Kritisch',
        };

        // Pattern based on late ratio
        $pattern = match (true) {
            $lateRatio === 0.0           => 'unauffaellig',
            $lateRatio <= 0.15 && $total >= 5 => 'einzelner_ausreisser',
            $lateRatio <= 0.5            => 'wiederkehrend_verspaetet',
            default                      => 'chronisch_problematisch',
        };

        $patternLabel = match ($pattern) {
            'unauffaellig'            => 'Unauffällig',
            'einzelner_ausreisser'    => 'Einzelner Ausreißer',
            'wiederkehrend_verspaetet'=> 'Wiederkehrend verspätet',
            default                   => 'Chronisch problematisch',
        };

        return [
            'avg_delay_days' => $avgDelay,
            'score'          => $score,
            'score_label'    => $scoreLabel,
            'pattern'        => $pattern,
            'pattern_label'  => $patternLabel,
        ];
    }

    /**
     * Compute risk level from open amount, overdue days, payment score, and pattern.
     *
     * Logic:
     *   niedrig  → good payer, low amount, short overdue
     *   mittel   → moderate issues in any dimension
     *   hoch     → persistent lateness or large overdue amount
     *   kritisch → chronically problematic or extremely overdue
     */
    public function computeRiskLevel(
        int $openTotal,
        int $daysOverdue,
        string $paymentScore,
        string $pattern,
    ): string {
        if ($pattern === 'chronisch_problematisch' || $daysOverdue > 90 || $openTotal > 5_000_000_000) {
            return 'kritisch'; // > 5.000 €
        }

        if ($pattern === 'wiederkehrend_verspaetet' || $daysOverdue > 45 || $openTotal > 1_500_000_000) {
            return 'hoch'; // > 1.500 €
        }

        if (in_array($paymentScore, ['schlecht', 'kritisch'], true) || $daysOverdue > 14 || $openTotal > 500_000_000) {
            return 'mittel'; // > 500 €
        }

        return 'niedrig';
    }

    /**
     * Bulk-compute minimal list metrics for multiple customers efficiently.
     * Returns an array keyed by customer_id.
     */
    public function bulkListMetrics(Collection $customerIds): array
    {
        if ($customerIds->isEmpty()) {
            return [];
        }

        $rows = LexofficeVoucher::whereIn('customer_id', $customerIds)
            ->where('voucher_type', LexofficeVoucher::TYPE_SALES_INVOICE)
            ->whereIn('voucher_status', [LexofficeVoucher::STATUS_OPEN, LexofficeVoucher::STATUS_OVERDUE])
            ->selectRaw('
                customer_id,
                SUM(open_amount) as total_open,
                COUNT(*) as open_count,
                MIN(due_date) as oldest_due,
                MAX(dunning_level) as max_level
            ')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $result = [];
        foreach ($customerIds as $id) {
            $row = $rows->get($id);
            $oldest = $row?->oldest_due ? Carbon::parse($row->oldest_due) : null;
            $daysOverdue = $oldest ? (int) $oldest->diffInDays(now(), false) : 0;

            $result[$id] = [
                'open_total_milli' => (int) ($row?->total_open ?? 0),
                'open_count'       => (int) ($row?->open_count ?? 0),
                'oldest_due_date'  => $oldest?->toDateString(),
                'days_overdue'     => $daysOverdue,
                'dunning_level'    => (int) ($row?->max_level ?? 0),
            ];
        }

        return $result;
    }
}
