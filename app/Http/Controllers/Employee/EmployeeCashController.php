<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Driver\CashRegister;
use App\Models\Driver\CashTransaction;
use App\Models\Employee\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmployeeCashController extends Controller
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the Employee for the currently logged-in user.
     * Aborts with 403 if no employee record or no cash register assigned.
     */
    private function resolveEmployee(Request $request): Employee
    {
        $user     = $request->user();
        $employee = Employee::where('email', $user->email)->first();

        abort_if($employee === null, 403, 'Kein Mitarbeiter-Konto gefunden.');
        abort_if($employee->cash_register_id === null, 403, 'Kein Kassengeldbeutel zugewiesen.');

        return $employee;
    }

    /**
     * Compute the theoretical (Soll) balance in cents from the last cash_count onwards.
     * Returns the Soll balance and the last cash_count transaction.
     *
     * @return array{soll_cents: int, last_count: CashTransaction|null, last_count_at: string|null}
     */
    private function computeSoll(CashRegister $register): array
    {
        /** @var CashTransaction|null $lastCount */
        $lastCount = $register->transactions()
            ->where('category', CashTransaction::CATEGORY_CASH_COUNT)
            ->orderByDesc('created_at')
            ->first();

        $query = $register->transactions();

        if ($lastCount !== null) {
            $query->where('created_at', '>', $lastCount->created_at);
        }

        $transactions = $query->get();

        // Start from the Ist amount of the last count; that count's note holds JSON
        $base = 0;
        if ($lastCount !== null) {
            $noteData = json_decode((string) $lastCount->note, true);
            $base     = (int) ($noteData['ist_cents'] ?? 0);
        }

        $delta = $transactions->reduce(function (int $carry, CashTransaction $tx): int {
            return $carry + ($tx->type === CashTransaction::TYPE_DEPOSIT
                ? $tx->amount_cents
                : -$tx->amount_cents);
        }, 0);

        return [
            'soll_cents'    => $base + $delta,
            'last_count'    => $lastCount,
            'last_count_at' => $lastCount?->created_at?->toDateTimeString(),
        ];
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    public function index(Request $request): View
    {
        $employee = $this->resolveEmployee($request);
        $register = $employee->cashRegister()->with('transactions')->first();

        $soll      = $this->computeSoll($register);
        $safes     = CashRegister::where('register_type', CashRegister::TYPE_SAFE)
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get();

        $transactions = $register->transactions()
            ->orderByDesc('created_at')
            ->take(50)
            ->get();

        // Totals since last cash count
        $since        = $soll['last_count']?->created_at;
        $recentTx     = $since
            ? $register->transactions()->where('created_at', '>', $since)->get()
            : $register->transactions;

        $einnahmen = $recentTx->where('type', CashTransaction::TYPE_DEPOSIT)->sum('amount_cents');
        $ausgaben  = $recentTx->where('type', CashTransaction::TYPE_WITHDRAWAL)->sum('amount_cents');

        return view('employee.cash.index', [
            'register'      => $register,
            'transactions'  => $transactions,
            'soll_cents'    => $soll['soll_cents'],
            'last_count'    => $soll['last_count'],
            'last_count_at' => $soll['last_count_at'],
            'einnahmen'     => (int) $einnahmen,
            'ausgaben'      => (int) $ausgaben,
            'safes'         => $safes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->resolveEmployee($request);
        $register = $employee->cashRegister;

        $request->validate([
            'booking_type' => 'required|in:customer_payment,supplier_payment,safe_deposit',
            'amount'       => 'required|numeric|min:0.01',
            'note'         => 'nullable|string|max:500',
            'safe_id'      => 'required_if:booking_type,safe_deposit|nullable|exists:cash_registers,id',
        ]);

        $amountCents  = (int) round((float) $request->input('amount') * 100);
        $bookingType  = $request->input('booking_type');

        if ($bookingType === 'safe_deposit') {
            $safe = CashRegister::findOrFail((int) $request->input('safe_id'));

            DB::transaction(function () use ($register, $safe, $amountCents, $employee): void {
                // Withdrawal from employee wallet
                CashTransaction::create([
                    'cash_register_id'           => $register->id,
                    'employee_id'                => $employee->id,
                    'type'                       => CashTransaction::TYPE_WITHDRAWAL,
                    'category'                   => CashTransaction::CATEGORY_SAFE_DEPOSIT,
                    'amount_cents'               => $amountCents,
                    'note'                       => 'Tresor-Einzahlung: ' . $safe->name,
                    'transfer_target_register_id' => $safe->id,
                ]);

                // Counter-entry on safe
                CashTransaction::create([
                    'cash_register_id' => $safe->id,
                    'employee_id'      => $employee->id,
                    'type'             => CashTransaction::TYPE_DEPOSIT,
                    'category'         => CashTransaction::CATEGORY_SAFE_DEPOSIT,
                    'amount_cents'     => $amountCents,
                    'note'             => 'Einzahlung von ' . $employee->full_name,
                ]);
            });

            return back()->with('success', 'Tresor-Einzahlung von ' . number_format($amountCents / 100, 2, ',', '.') . ' € erfasst.');
        }

        // Customer payment or supplier payment
        $type     = $bookingType === 'customer_payment'
            ? CashTransaction::TYPE_DEPOSIT
            : CashTransaction::TYPE_WITHDRAWAL;

        CashTransaction::create([
            'cash_register_id' => $register->id,
            'employee_id'      => $employee->id,
            'type'             => $type,
            'category'         => $bookingType,
            'amount_cents'     => $amountCents,
            'note'             => $request->input('note'),
        ]);

        return back()->with('success', 'Buchung erfasst.');
    }

    public function kassensturz(Request $request): RedirectResponse
    {
        $employee = $this->resolveEmployee($request);
        $register = $employee->cashRegister;

        $request->validate([
            'ist_betrag'  => 'required|numeric|min:0',
            'trinkgeld'   => 'nullable|numeric|min:0',
        ]);

        $istCents       = (int) round((float) $request->input('ist_betrag') * 100);
        $trinkgeldCents = (int) round((float) ($request->input('trinkgeld') ?? 0) * 100);

        $soll     = $this->computeSoll($register);
        $sollCents = $soll['soll_cents'];
        $diffCents = $istCents - $sollCents;
        $ungeklCents = $diffCents - (-$trinkgeldCents); // Ist - Soll - Trinkgeld (tip reduces diff)

        // Correction transaction to sync soll to ist
        // (amount = |diff|, type depends on sign)
        if ($diffCents !== 0) {
            CashTransaction::create([
                'cash_register_id' => $register->id,
                'employee_id'      => $employee->id,
                'type'             => $diffCents > 0
                    ? CashTransaction::TYPE_DEPOSIT
                    : CashTransaction::TYPE_WITHDRAWAL,
                'category'         => CashTransaction::CATEGORY_CASH_COUNT,
                'amount_cents'     => abs($diffCents),
                'note'             => json_encode([
                    'soll_cents'      => $sollCents,
                    'ist_cents'       => $istCents,
                    'trinkgeld_cents' => $trinkgeldCents,
                    'diff_cents'      => $diffCents,
                    'ungeklaert_cents' => $ungeklCents,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } else {
            // Even if diff=0, record the cash count with amount=0 as a zero-deposit
            CashTransaction::create([
                'cash_register_id' => $register->id,
                'employee_id'      => $employee->id,
                'type'             => CashTransaction::TYPE_DEPOSIT,
                'category'         => CashTransaction::CATEGORY_CASH_COUNT,
                'amount_cents'     => 0,
                'note'             => json_encode([
                    'soll_cents'       => $sollCents,
                    'ist_cents'        => $istCents,
                    'trinkgeld_cents'  => $trinkgeldCents,
                    'diff_cents'       => 0,
                    'ungeklaert_cents' => $ungeklCents,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        }

        $msg = sprintf(
            'Kassensturz: Soll %s €, Ist %s €, Differenz %s €%s',
            number_format($sollCents / 100, 2, ',', '.'),
            number_format($istCents / 100, 2, ',', '.'),
            number_format($diffCents / 100, 2, ',', '.'),
            $trinkgeldCents > 0
                ? ', davon Trinkgeld ' . number_format($trinkgeldCents / 100, 2, ',', '.') . ' €'
                : ''
        );

        if (abs($ungeklCents) > 500) { // > ±5 €
            $msg .= ' — ⚠ Ungeklärte Differenz: ' . number_format($ungeklCents / 100, 2, ',', '.') . ' € (möglicher Buchungsfehler)';
        }

        return back()->with('kassensturz', [
            'soll_cents'       => $sollCents,
            'ist_cents'        => $istCents,
            'diff_cents'       => $diffCents,
            'trinkgeld_cents'  => $trinkgeldCents,
            'ungeklaert_cents' => $ungeklCents,
            'msg'              => $msg,
        ]);
    }
}
