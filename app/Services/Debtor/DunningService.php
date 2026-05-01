<?php

declare(strict_types=1);

namespace App\Services\Debtor;

use App\Models\Admin\LexofficeVoucher;
use App\Models\Debtor\DebtorNote;
use App\Models\Debtor\DunningRun;
use App\Models\Debtor\DunningRunItem;
use App\Models\Pricing\AppSetting;
use App\Models\Pricing\Customer;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Determines which customers are eligible for dunning and builds dunning run proposals.
 *
 * Dunning levels:
 *   0 = not yet dunned
 *   1 = first reminder sent (E-Mail Mahnung Stufe 1)
 *   2 = second reminder sent (postalische Mahnung über Briefdienst)
 *
 * A customer is skipped when:
 *   - customers.debt_hold = true  (Klärfall)
 *   - any open DebtorNote of type=dispute or payment_promise exists
 *   - any open invoice has is_dunning_blocked = true
 */
class DunningService
{
    public function __construct(
        private readonly DunningInterestService $interestService,
    ) {}

    // ── Settings helpers ─────────────────────────────────────────────────────

    public function daysToLevel1(): int
    {
        return AppSetting::getInt('dunning.days_to_level1', 7);
    }

    public function daysLevel1ToLevel2(): int
    {
        return AppSetting::getInt('dunning.days_level1_to_level2', 14);
    }

    public function maxPerRun(): int
    {
        return AppSetting::getInt('dunning.max_send_per_run', 50);
    }

    public function isTestMode(): bool
    {
        return AppSetting::get('dunning.test_mode', '0') === '1';
    }

    // ── Proposal logic ───────────────────────────────────────────────────────

    /**
     * Build a single proposal for one customer (used for quick/direct dunning send).
     * Returns null if the customer is not eligible.
     *
     * @return array<string, mixed>|null
     */
    public function buildProposalForCustomer(Customer $customer): ?array
    {
        $proposals = $this->buildProposals($customer);
        return $proposals->first();
    }

    /**
     * Find all customers with open invoices that are eligible for a dunning action.
     * Returns a collection of proposal arrays, one per customer.
     *
     * @param  Customer|null $only  If given, only evaluate this one customer.
     */
    public function buildProposals(?Customer $only = null): Collection
    {
        $daysToL1 = $this->daysToLevel1();
        $daysL1L2 = $this->daysLevel1ToLevel2();

        // Customers that have open/overdue sales invoices
        $query = Customer::whereHas('openVouchers', fn ($q) => $q->where('is_dunning_blocked', false))
            ->where('debt_hold', false)
            ->with(['openVouchers', 'openDebtorNotes']);

        if ($only) {
            $query->where('id', $only->id);
        }

        $customers = $query->get();

        $proposals = collect();

        foreach ($customers as $customer) {
            // Skip if any open note blocks dunning
            $hasBlockingNote = $customer->openDebtorNotes
                ->filter(fn (DebtorNote $n) => $n->blocksDunning())
                ->isNotEmpty();

            if ($hasBlockingNote) {
                continue;
            }

            $eligibleVouchers = $customer->openVouchers
                ->filter(fn (LexofficeVoucher $v) => ! $v->is_dunning_blocked);

            if ($eligibleVouchers->isEmpty()) {
                continue;
            }

            // Determine required dunning level per voucher, then take max
            $proposedLevel = null;
            $includedVouchers = collect();

            foreach ($eligibleVouchers as $voucher) {
                $daysOverdue = $voucher->daysOverdue();
                $currentLevel = (int) $voucher->dunning_level;

                if ($currentLevel === 0 && $daysOverdue >= $daysToL1) {
                    // Ready for level 1
                    $proposedLevel  = max($proposedLevel ?? 0, 1);
                    $includedVouchers->push($voucher);
                } elseif ($currentLevel === 1) {
                    // Check if enough time passed since last dunning for level 2
                    $lastDunned   = $voucher->last_dunned_at;
                    $daysElapsed  = $lastDunned ? (int) $lastDunned->diffInDays(now()) : 9999;

                    if ($daysElapsed >= $daysL1L2) {
                        $proposedLevel  = max($proposedLevel ?? 0, 2);
                        $includedVouchers->push($voucher);
                    }
                }
                // level 2+ is handled manually (no further auto-escalation)
            }

            if ($proposedLevel === null || $includedVouchers->isEmpty()) {
                continue;
            }

            $openTotal = $includedVouchers->sum('open_amount');

            // Zinsen und Pauschalen NUR ab Stufe 2 — Stufe 1 ist eine reine Zahlungserinnerung
            if ($proposedLevel >= 2) {
                $interestBreakdown = $this->interestService->computeBreakdown($includedVouchers, $customer);
                $interestTotal     = array_sum(array_column($interestBreakdown, 'interest_milli'));
                $flatFee           = $this->interestService->computeFlatFee($customer, $includedVouchers->count());
            } else {
                $interestBreakdown = [];
                $interestTotal     = 0;
                $flatFee           = 0;
            }

            $channel = $proposedLevel >= 2 ? DunningRunItem::CHANNEL_POST : DunningRunItem::CHANNEL_EMAIL;

            // Check if postal service is enabled for level 2
            if ($channel === DunningRunItem::CHANNEL_POST) {
                $postalEnabled = AppSetting::get('dunning.postal_service_enabled', '0') === '1';
                if (! $postalEnabled) {
                    $channel = DunningRunItem::CHANNEL_EMAIL; // fallback to email
                }
            }

            $proposals->push([
                'customer'           => $customer,
                'proposed_level'     => $proposedLevel,
                'channel'            => $channel,
                'vouchers'           => $includedVouchers,
                'open_total_milli'   => $openTotal,
                'interest_milli'     => $interestTotal,
                'interest_breakdown' => $interestBreakdown,
                'flat_fee_milli'     => $flatFee,
                'recipient_email'    => $customer->billing_email ?? $customer->email,
                'recipient_name'     => $customer->displayName(),
            ]);
        }

        return $proposals->sortByDesc('open_total_milli')->values();
    }

    /**
     * Diagnose why a customer is not eligible for normal dunning.
     * Returns a structured array of blocking reasons for display in the admin UI.
     *
     * @return array{hold: bool, blocking_notes: array, all_invoices_blocked: bool, threshold_not_met: bool, no_open_invoices: bool, can_force: bool}
     */
    public function diagnoseBlockingReasons(Customer $customer): array
    {
        $openVouchers = LexofficeVoucher::where('customer_id', $customer->id)
            ->where('voucher_type', LexofficeVoucher::TYPE_SALES_INVOICE)
            ->whereIn('voucher_status', [LexofficeVoucher::STATUS_OPEN, LexofficeVoucher::STATUS_OVERDUE])
            ->get();

        $noOpenInvoices = $openVouchers->isEmpty();

        $allBlocked = ! $noOpenInvoices && $openVouchers->every(fn ($v) => $v->is_dunning_blocked);

        $blockingNotes = DebtorNote::where('customer_id', $customer->id)
            ->where('status', DebtorNote::STATUS_OPEN)
            ->whereIn('type', [DebtorNote::TYPE_DISPUTE, DebtorNote::TYPE_PAYMENT_PROMISE])
            ->get()
            ->map(fn ($n) => ['type' => $n->typeName(), 'body' => $n->body])
            ->all();

        $thresholdNotMet = false;
        if (! $noOpenInvoices && ! $allBlocked) {
            $eligible = $openVouchers->filter(fn ($v) => ! $v->is_dunning_blocked);
            $anyReady = $eligible->some(function ($v) {
                $level   = (int) $v->dunning_level;
                $overdue = $v->daysOverdue();
                if ($level === 0) {
                    return $overdue >= $this->daysToLevel1();
                }
                if ($level === 1) {
                    $elapsed = $v->last_dunned_at ? (int) $v->last_dunned_at->diffInDays(now()) : 9999;
                    return $elapsed >= $this->daysLevel1ToLevel2();
                }
                return false;
            });
            $thresholdNotMet = ! $anyReady;
        }

        $canForce = ! $noOpenInvoices;

        return [
            'hold'                => (bool) $customer->debt_hold,
            'blocking_notes'      => $blockingNotes,
            'all_invoices_blocked'=> $allBlocked,
            'threshold_not_met'   => $thresholdNotMet,
            'no_open_invoices'    => $noOpenInvoices,
            'can_force'           => $canForce,
        ];
    }

    /**
     * Build a forced proposal for a single customer, bypassing all eligibility checks.
     * Used by admins to override hold, blocking notes, and time thresholds.
     * Returns null only if the customer has no open invoices at all.
     *
     * @return array<string, mixed>|null
     */
    public function buildForcedProposal(Customer $customer): ?array
    {
        $vouchers = LexofficeVoucher::where('customer_id', $customer->id)
            ->where('voucher_type', LexofficeVoucher::TYPE_SALES_INVOICE)
            ->whereIn('voucher_status', [LexofficeVoucher::STATUS_OPEN, LexofficeVoucher::STATUS_OVERDUE])
            ->get();

        if ($vouchers->isEmpty()) {
            return null;
        }

        // Determine next dunning level from highest current level on any voucher
        $maxLevel     = (int) $vouchers->max('dunning_level');
        $proposedLevel = max(1, $maxLevel + 1);

        $openTotal = $vouchers->sum('open_amount');

        if ($proposedLevel >= 2) {
            $interestBreakdown = $this->interestService->computeBreakdown($vouchers, $customer);
            $interestTotal     = array_sum(array_column($interestBreakdown, 'interest_milli'));
            $flatFee           = $this->interestService->computeFlatFee($customer, $vouchers->count());
        } else {
            $interestBreakdown = [];
            $interestTotal     = 0;
            $flatFee           = 0;
        }

        $channel = $proposedLevel >= 2 ? DunningRunItem::CHANNEL_POST : DunningRunItem::CHANNEL_EMAIL;
        if ($channel === DunningRunItem::CHANNEL_POST) {
            $postalEnabled = AppSetting::get('dunning.postal_service_enabled', '0') === '1';
            if (! $postalEnabled) {
                $channel = DunningRunItem::CHANNEL_EMAIL;
            }
        }

        return [
            'customer'           => $customer,
            'proposed_level'     => $proposedLevel,
            'channel'            => $channel,
            'vouchers'           => $vouchers,
            'open_total_milli'   => $openTotal,
            'interest_milli'     => $interestTotal,
            'interest_breakdown' => $interestBreakdown,
            'flat_fee_milli'     => $flatFee,
            'recipient_email'    => $customer->billing_email ?? $customer->email,
            'recipient_name'     => $customer->displayName(),
        ];
    }

    /**
     * Create a dunning run (draft) from selected proposals.
     *
     * @param  Collection $proposals  subset of buildProposals() output
     * @param  int        $userId     admin user creating the run
     * @param  bool       $testMode   if true, no real emails sent
     */
    public function createRun(Collection $proposals, int $userId, bool $testMode = false): DunningRun
    {
        $run = DunningRun::create([
            'created_by_user_id' => $userId,
            'status'             => DunningRun::STATUS_DRAFT,
            'is_test_mode'       => $testMode || $this->isTestMode(),
            'notes'              => null,
        ]);

        $limit = $this->maxPerRun();
        $count = 0;

        foreach ($proposals as $proposal) {
            if ($count >= $limit) {
                break;
            }

            DunningRunItem::create([
                'dunning_run_id'     => $run->id,
                'customer_id'        => $proposal['customer']->id,
                'channel'            => $proposal['channel'],
                'dunning_level'      => $proposal['proposed_level'],
                'total_open_milli'   => $proposal['open_total_milli'],
                'interest_milli'     => $proposal['interest_milli'],
                'interest_breakdown' => $proposal['interest_breakdown'],
                'flat_fee_milli'     => $proposal['flat_fee_milli'],
                'voucher_ids'        => $proposal['vouchers']->pluck('id')->toArray(),
                'recipient_email'    => $proposal['recipient_email'],
                'recipient_name'     => $proposal['recipient_name'],
                'status'             => DunningRunItem::STATUS_PENDING,
            ]);

            $count++;
        }

        return $run;
    }

    /**
     * After a dunning run item has been successfully sent, update the vouchers.
     */
    public function markVouchersDunned(DunningRunItem $item): void
    {
        if (empty($item->voucher_ids)) {
            return;
        }

        LexofficeVoucher::whereIn('id', $item->voucher_ids)->update([
            'dunning_level'  => $item->dunning_level,
            'last_dunned_at' => now(),
        ]);
    }

    /**
     * Manually set dunning level on a voucher (for admin overrides).
     */
    public function setVoucherDunningLevel(LexofficeVoucher $voucher, int $level): void
    {
        $voucher->update([
            'dunning_level'  => max(0, $level),
            'last_dunned_at' => $level > 0 ? now() : null,
        ]);
    }
}
