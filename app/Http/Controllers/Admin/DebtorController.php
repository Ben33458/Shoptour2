<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\LexofficeVoucher;
use App\Models\Debtor\DebtorNote;
use App\Models\Pricing\AppSetting;
use App\Models\Pricing\Customer;
use App\Mail\AccountStatementMail;
use App\Services\Admin\AuditLogService;
use App\Services\Debtor\DebtorPdfService;
use App\Services\Debtor\DebtorProfileService;
use App\Services\Integrations\LexofficeVoucherPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class DebtorController extends Controller
{
    public function __construct(
        private readonly DebtorProfileService $profileService,
        private readonly LexofficeVoucherPdfService $voucherPdf,
        private readonly DebtorPdfService $pdfService,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * GET /admin/debitoren
     * Offene-Posten-Übersicht — all customers with open invoices.
     */
    public function index(Request $request): View
    {
        $query = Customer::query()
            ->whereHas('openVouchers')
            ->with(['customerGroup']);

        // Filters
        if ($request->filter === 'hold') {
            $query->where('debt_hold', true);
        }
        if ($request->filter === 'b2b') {
            $query->whereNotNull('company_name')->where('company_name', '!=', '');
        }
        if ($request->filter === 'b2c') {
            $query->where(fn ($q) => $q->whereNull('company_name')->orWhere('company_name', ''));
        }
        if ($request->filled('level') && is_numeric($request->level)) {
            $level = (int) $request->level;
            $query->whereHas('openVouchers', fn ($q) => $q->where('dunning_level', $level));
        }

        $customers    = $query->orderBy('customer_number')->get();
        $customerIds  = $customers->pluck('id');
        $metrics      = $this->profileService->bulkListMetrics($customerIds);

        // Apply sorting
        $sort = $request->sort ?? 'open_desc';
        $customers = match ($sort) {
            'open_desc'     => $customers->sortByDesc(fn ($c) => $metrics[$c->id]['open_total_milli'] ?? 0),
            'oldest_due'    => $customers->sortBy(fn ($c) => $metrics[$c->id]['oldest_due_date']),
            'overdue_desc'  => $customers->sortByDesc(fn ($c) => $metrics[$c->id]['days_overdue'] ?? 0),
            'level_desc'    => $customers->sortByDesc(fn ($c) => $metrics[$c->id]['dunning_level'] ?? 0),
            default         => $customers->sortByDesc(fn ($c) => $metrics[$c->id]['open_total_milli'] ?? 0),
        };

        // Apply risk filter after metrics are loaded
        if ($request->filter === 'risk_high') {
            $customers = $customers->filter(function ($c) use ($metrics) {
                $m = $metrics[$c->id] ?? [];
                return in_array(
                    $this->profileService->computeRiskLevel(
                        $m['open_total_milli'] ?? 0,
                        $m['days_overdue'] ?? 0,
                        'unknown',
                        'unknown',
                    ),
                    ['hoch', 'kritisch'],
                    true
                );
            });
        }

        // Dunnable filter (has open non-blocked vouchers, no hold, no blocking note)
        if ($request->filter === 'dunnable') {
            $blockingCustomerIds = DebtorNote::where('status', DebtorNote::STATUS_OPEN)
                ->whereIn('type', [DebtorNote::TYPE_DISPUTE, DebtorNote::TYPE_PAYMENT_PROMISE])
                ->pluck('customer_id')
                ->toArray();

            $customers = $customers->filter(
                fn ($c) => ! $c->debt_hold && ! in_array($c->id, $blockingCustomerIds, true)
                    && ($metrics[$c->id]['open_count'] ?? 0) > 0
            );
        }

        // Grand total
        $grandTotalOpen = collect($metrics)->sum('open_total_milli');

        return view('admin.debtor.index', compact('customers', 'metrics', 'sort', 'grandTotalOpen'));
    }

    /**
     * GET /admin/debitoren/{customer}
     * Full debtor profile for one customer.
     */
    public function show(Customer $customer): View
    {
        $profile      = $this->profileService->getProfile($customer);

        $openVouchers = LexofficeVoucher::where('customer_id', $customer->id)
            ->whereIn('voucher_type', [LexofficeVoucher::TYPE_SALES_INVOICE, LexofficeVoucher::TYPE_CREDIT_NOTE])
            ->whereIn('voucher_status', [LexofficeVoucher::STATUS_OPEN, LexofficeVoucher::STATUS_OVERDUE])
            ->orderBy('due_date')
            ->get();

        $allVouchers  = LexofficeVoucher::where('customer_id', $customer->id)
            ->whereIn('voucher_type', [LexofficeVoucher::TYPE_SALES_INVOICE, LexofficeVoucher::TYPE_CREDIT_NOTE])
            ->orderByDesc('voucher_date')
            ->limit(20)
            ->get();

        $debtorNotes  = DebtorNote::where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->with(['createdBy', 'assignedTo', 'voucher'])
            ->get();

        $customer->load('customerGroup');

        return view('admin.debtor.show', compact(
            'customer',
            'profile',
            'openVouchers',
            'allVouchers',
            'debtorNotes',
        ));
    }

    /**
     * POST /admin/debitoren/{customer}/hold
     * Toggle debt hold status.
     */
    public function toggleHold(Request $request, Customer $customer): RedirectResponse
    {
        $request->validate([
            'debt_hold'        => 'required|boolean',
            'debt_hold_reason' => 'nullable|string|max:500',
        ]);

        $customer->update([
            'debt_hold'        => (bool) $request->debt_hold,
            'debt_hold_reason' => $request->debt_hold_reason,
        ]);

        $msg = $request->debt_hold
            ? 'Hold gesetzt. Kunde wird bei Mahnläufen übersprungen.'
            : 'Hold aufgehoben.';

        return back()->with('success', $msg);
    }

    /**
     * POST /admin/debitoren/{customer}/delivery-status
     * Update delivery status (Lieferfreigabe).
     */
    public function updateDeliveryStatus(Request $request, Customer $customer): RedirectResponse
    {
        $request->validate([
            'delivery_status'    => 'required|in:normal,warning,blocked',
            'delivery_condition' => 'nullable|in:cash_only,prepayment,stop_check',
            'delivery_status_note' => 'nullable|string|max:500',
        ]);

        $customer->update([
            'delivery_status'        => $request->delivery_status,
            'delivery_condition'     => $request->delivery_condition,
            'delivery_status_note'   => $request->delivery_status_note,
            'delivery_status_set_by' => auth()->id(),
        ]);

        return back()->with('success', 'Lieferstatus aktualisiert.');
    }

    /**
     * GET /admin/debitoren/vouchers/{voucher}/pdf
     * Stream the Lexoffice invoice PDF (fetches from API on first call, cached afterwards).
     */
    public function downloadVoucherPdf(LexofficeVoucher $voucher): Response
    {
        try {
            $content  = file_get_contents($this->voucherPdf->absolutePath($voucher));
            $filename = ($voucher->voucher_number ?? 'Rechnung') . '.pdf';

            return response($content, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
                'Cache-Control'       => 'private, max-age=3600',
            ]);
        } catch (\Throwable $e) {
            return response('PDF konnte nicht geladen werden: ' . $e->getMessage(), 502, [
                'Content-Type' => 'text/plain',
            ]);
        }
    }

    /**
     * POST /admin/debitoren/{customer}/kontoauszug
     * Send an account statement (Offene-Posten-Übersicht) by email — not a dunning letter.
     */
    public function sendAccountStatement(Request $request, Customer $customer): RedirectResponse
    {
        $vouchers = LexofficeVoucher::where('customer_id', $customer->id)
            ->whereIn('voucher_type', [LexofficeVoucher::TYPE_SALES_INVOICE, LexofficeVoucher::TYPE_CREDIT_NOTE])
            ->whereIn('voucher_status', [LexofficeVoucher::STATUS_OPEN, LexofficeVoucher::STATUS_OVERDUE])
            ->orderBy('due_date')
            ->get();

        if ($vouchers->isEmpty()) {
            return back()->with('error', 'Keine offenen Posten vorhanden — kein Versand möglich.');
        }

        $recipient = $customer->billing_email ?? $customer->email;
        if (empty($recipient)) {
            return back()->with('error', 'Kunde hat keine E-Mail-Adresse hinterlegt.');
        }

        $pdfPath = $this->pdfService->generateOpenItemsPdf($customer, $vouchers);

        Mail::to($recipient)->send(new AccountStatementMail($customer, $pdfPath, $vouchers));

        $copyRecipients = [];
        if ($request->boolean('copy_to_me') && auth()->user()?->email) {
            Mail::to(auth()->user()->email)->send(new AccountStatementMail($customer, $pdfPath, $vouchers));
            $copyRecipients[] = auth()->user()->email;
        }

        $this->audit->log('debtor.account_statement.sent', $customer, [
            'customer_nr'   => $customer->customer_number,
            'recipient'     => $recipient,
            'open_items'    => $vouchers->count(),
            'copy_to'       => $copyRecipients ?: null,
        ]);

        $msg = 'Offene-Posten-Übersicht versendet an ' . $recipient;
        if ($copyRecipients) {
            $msg .= ' (Kopie an ' . implode(', ', $copyRecipients) . ')';
        }

        return back()->with('success', $msg . '.');
    }

    /**
     * POST /admin/debitoren/vouchers/{voucher}/block
     * Block or unblock a voucher from dunning.
     */
    public function toggleVoucherBlock(Request $request, LexofficeVoucher $voucher): RedirectResponse
    {
        $request->validate([
            'is_dunning_blocked'   => 'required|boolean',
            'dunning_block_reason' => 'nullable|string|max:500',
        ]);

        $voucher->update([
            'is_dunning_blocked'   => (bool) $request->is_dunning_blocked,
            'dunning_block_reason' => $request->dunning_block_reason,
        ]);

        $msg = $request->is_dunning_blocked ? 'Rechnung für Mahnwesen gesperrt.' : 'Sperre aufgehoben.';

        return back()->with('success', $msg);
    }
}
