<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\LexofficeVoucher;
use App\Models\Pricing\Customer;
use App\Services\Integrations\LexofficeContactMatcher;
use App\Services\Integrations\LexofficePull;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LexofficeBankMatchingController extends Controller
{
    public function __construct(
        private readonly LexofficeContactMatcher $matcher
    ) {}

    public function index(Request $request): View
    {
        $year   = (int) $request->input('year', 2024);
        $status = $request->input('status', 'unconfirmed'); // unconfirmed|confirmed|all

        $query = LexofficeVoucher::whereNull('customer_id')
            ->where('voucher_type', LexofficeVoucher::TYPE_SALES_INVOICE)
            ->whereYear('voucher_date', $year)
            ->orderBy('voucher_date');

        if ($status === 'unconfirmed') {
            $query->whereNull('manually_confirmed_at');
        } elseif ($status === 'confirmed') {
            $query->whereNotNull('manually_confirmed_at');
        }

        $vouchers = $query->paginate(50)->withQueryString();

        // Pre-compute suggestions for unconfirmed vouchers (without customer assigned)
        $suggestions = [];
        foreach ($vouchers as $voucher) {
            if ($voucher->contact_name) {
                $suggestion = $this->matcher->suggestCustomer($voucher->contact_name);
                if ($suggestion) {
                    $suggestions[$voucher->id] = $suggestion;
                }
            }
        }

        // All confirmed customers for the dropdown
        $customers = Customer::where('active', true)
            ->select(['id', 'company_name', 'first_name', 'last_name', 'customer_number'])
            ->orderBy('company_name')
            ->get();

        // Stats for the current year
        $baseQuery = LexofficeVoucher::whereNull('customer_id')
            ->where('voucher_type', LexofficeVoucher::TYPE_SALES_INVOICE)
            ->whereYear('voucher_date', $year);

        $stats = [
            'total'       => (clone $baseQuery)->count(),
            'unconfirmed' => (clone $baseQuery)->whereNull('manually_confirmed_at')->count(),
            'confirmed'   => (clone $baseQuery)->whereNotNull('manually_confirmed_at')->count(),
        ];

        $availableYears = LexofficeVoucher::whereNull('customer_id')
            ->where('voucher_type', LexofficeVoucher::TYPE_SALES_INVOICE)
            ->selectRaw('YEAR(voucher_date) as y')
            ->groupBy('y')
            ->orderByDesc('y')
            ->pluck('y');

        return view('admin.integrations.bank-matching', compact(
            'vouchers', 'suggestions', 'customers', 'stats', 'year', 'status', 'availableYears'
        ));
    }

    /**
     * Link a voucher to a local customer.
     */
    public function link(Request $request, LexofficeVoucher $voucher): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);

        $voucher->update(['customer_id' => $validated['customer_id'] ?: null]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->back()->with('success', 'Kundenzuordnung gespeichert.');
    }

    /**
     * Toggle the manual confirmation checkbox.
     */
    public function confirm(LexofficeVoucher $voucher): JsonResponse|RedirectResponse
    {
        $voucher->update([
            'manually_confirmed_at' => $voucher->manually_confirmed_at ? null : now(),
        ]);

        if (request()->wantsJson()) {
            return response()->json([
                'ok'        => true,
                'confirmed' => $voucher->manually_confirmed_at !== null,
                'timestamp' => $voucher->manually_confirmed_at?->format('d.m.Y H:i'),
            ]);
        }

        return redirect()->back();
    }

    /**
     * Save a free-text note for a voucher.
     */
    public function note(Request $request, LexofficeVoucher $voucher): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $voucher->update(['assignment_note' => $validated['note'] ?: null]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->back()->with('success', 'Notiz gespeichert.');
    }

    /**
     * Trigger a fresh pull of vouchers from the Lexoffice API.
     */
    public function pull(): RedirectResponse
    {
        try {
            app(LexofficePull::class)->pullVouchers();
            return redirect()->route('admin.integrations.lexoffice.bank-matching')
                ->with('success', 'Rechnungen aus Lexoffice aktualisiert.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.integrations.lexoffice.bank-matching')
                ->with('error', 'Fehler beim Laden: ' . $e->getMessage());
        }
    }
}
