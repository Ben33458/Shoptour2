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

class MeinCashController extends Controller
{
    private function employee(): Employee
    {
        $employee = Employee::findOrFail(session('employee_id'));
        abort_if($employee->cash_register_id === null, 403, 'Kein Kassengeldbeutel zugewiesen.');
        return $employee;
    }

    /**
     * Compute Soll balance in cents starting from the last cash_count.
     *
     * @return array{soll_cents: int, last_count: CashTransaction|null}
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
            'soll_cents' => $base + $delta,
            'last_count' => $lastCount,
        ];
    }

    public function index(): View
    {
        $employee = $this->employee();
        $register = $employee->cashRegister()->with('transactions')->first();

        $soll     = $this->computeSoll($register);
        $safes    = CashRegister::where('register_type', CashRegister::TYPE_SAFE)
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get();

        $transactions = $register->transactions()
            ->orderByDesc('created_at')
            ->take(50)
            ->get();

        $since     = $soll['last_count']?->created_at;
        $recentTx  = $since
            ? $register->transactions()->where('created_at', '>', $since)->get()
            : $register->transactions()->get();

        $einnahmen = $recentTx->where('type', CashTransaction::TYPE_DEPOSIT)->sum('amount_cents');
        $ausgaben  = $recentTx->where('type', CashTransaction::TYPE_WITHDRAWAL)->sum('amount_cents');

        return view('mein.kasse', [
            'employee'      => $employee,
            'register'      => $register,
            'transactions'  => $transactions,
            'soll_cents'    => $soll['soll_cents'],
            'last_count'    => $soll['last_count'],
            'einnahmen'     => (int) $einnahmen,
            'ausgaben'      => (int) $ausgaben,
            'safes'         => $safes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->employee();
        $register = $employee->cashRegister;

        $request->validate([
            'booking_type' => 'required|in:customer_payment,supplier_payment,safe_deposit',
            'amount'       => 'required|numeric|min:0.01',
            'note'         => 'nullable|string|max:500',
            'safe_id'      => 'required_if:booking_type,safe_deposit|nullable|exists:cash_registers,id',
        ]);

        $amountCents = (int) round((float) $request->input('amount') * 100);
        $bookingType = $request->input('booking_type');

        if ($bookingType === 'safe_deposit') {
            $safe = CashRegister::findOrFail((int) $request->input('safe_id'));

            DB::transaction(function () use ($register, $safe, $amountCents, $employee): void {
                CashTransaction::create([
                    'cash_register_id'            => $register->id,
                    'employee_id'                 => $employee->id,
                    'type'                        => CashTransaction::TYPE_WITHDRAWAL,
                    'category'                    => CashTransaction::CATEGORY_SAFE_DEPOSIT,
                    'amount_cents'                => $amountCents,
                    'note'                        => 'Tresor-Einzahlung: ' . $safe->name,
                    'transfer_target_register_id' => $safe->id,
                ]);
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

        CashTransaction::create([
            'cash_register_id' => $register->id,
            'employee_id'      => $employee->id,
            'type'             => $bookingType === 'customer_payment'
                ? CashTransaction::TYPE_DEPOSIT
                : CashTransaction::TYPE_WITHDRAWAL,
            'category'         => $bookingType,
            'amount_cents'     => $amountCents,
            'note'             => $request->input('note'),
        ]);

        return back()->with('success', 'Buchung erfasst.');
    }

    public function kassensturz(Request $request): RedirectResponse
    {
        $employee = $this->employee();
        $register = $employee->cashRegister;

        $request->validate([
            'ist_betrag' => 'required|numeric|min:0',
            'trinkgeld'  => 'nullable|numeric|min:0',
        ]);

        $istCents       = (int) round((float) $request->input('ist_betrag') * 100);
        $trinkgeldCents = (int) round((float) ($request->input('trinkgeld') ?? 0) * 100);

        $soll      = $this->computeSoll($register);
        $sollCents = $soll['soll_cents'];
        $diffCents = $istCents - $sollCents;
        // Ungeklärte Differenz = Differenz + Trinkgeld (Trinkgeld erklärt eine negative Differenz)
        $ungeklCents = $diffCents + $trinkgeldCents;

        CashTransaction::create([
            'cash_register_id' => $register->id,
            'employee_id'      => $employee->id,
            'type'             => $diffCents >= 0
                ? CashTransaction::TYPE_DEPOSIT
                : CashTransaction::TYPE_WITHDRAWAL,
            'category'         => CashTransaction::CATEGORY_CASH_COUNT,
            'amount_cents'     => abs($diffCents),
            'note'             => json_encode([
                'soll_cents'       => $sollCents,
                'ist_cents'        => $istCents,
                'trinkgeld_cents'  => $trinkgeldCents,
                'diff_cents'       => $diffCents,
                'ungeklaert_cents' => $ungeklCents,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        return back()->with('kassensturz', [
            'soll_cents'       => $sollCents,
            'ist_cents'        => $istCents,
            'diff_cents'       => $diffCents,
            'trinkgeld_cents'  => $trinkgeldCents,
            'ungeklaert_cents' => $ungeklCents,
        ]);
    }
}
