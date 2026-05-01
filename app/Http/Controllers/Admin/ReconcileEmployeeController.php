<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reconcile\EmployeeReconcileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReconcileEmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeReconcileService $service,
    ) {}

    /**
     * Show all Ninox employees with match proposals.
     * Supports ?filter=unmatched|auto|confirmed|ignored
     */
    public function index(Request $request): View
    {
        $filter = $request->input('filter', 'unmatched');
        $search = trim((string) $request->input('search', ''));

        $stats = $this->service->stats();

        $filters = match ($filter) {
            'unmatched' => ['unmatched_only' => true],
            'auto'      => ['status' => 'auto'],
            'confirmed' => ['status' => 'confirmed'],
            'ignored'   => ['status' => 'ignored'],
            default     => [],
        };

        $proposals = $this->service->proposeMatches($filters);

        if ($search !== '') {
            $q         = mb_strtolower($search);
            $proposals = array_values(array_filter($proposals, function (array $p) use ($q): bool {
                $d    = $p['source_data'];
                $name = mb_strtolower(
                    trim(($d['vorname'] ?? '') . ' ' . ($d['nachname'] ?? ''))
                );
                return str_contains($name, $q);
            }));
        }

        return view('admin.reconcile.employees', compact('proposals', 'stats', 'filter', 'search'));
    }

    /**
     * Auto-match all unmatched Ninox employees above a confidence threshold.
     */
    public function autoMatch(Request $request): RedirectResponse
    {
        $request->validate([
            'min_confidence' => 'nullable|integer|min:50|max:100',
        ]);

        $result = $this->service->autoMatchAll(
            (int) $request->input('min_confidence', 85),
        );

        return back()->with('success', sprintf(
            'Auto-Abgleich: %d verknüpft, %d zu unsicher (< %d %%).',
            $result['auto_matched'],
            $result['skipped'],
            $request->input('min_confidence', 85),
        ));
    }

    /**
     * Confirm all current auto matches.
     */
    public function confirmAllAuto(Request $request): RedirectResponse
    {
        $count = $this->service->confirmAllAuto($request->user()->id);

        return back()->with('success', sprintf(
            '%d Mitarbeiter-Verknüpfung%s bestätigt.',
            $count,
            $count === 1 ? '' : 'en',
        ));
    }

    /**
     * Confirm a single match between a Ninox employee and a local employee.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'ninox_id'    => 'required|string',
            'employee_id' => 'required|integer|exists:employees,id',
        ]);

        $this->service->confirm(
            $request->ninox_id,
            (int) $request->employee_id,
            $request->user()->id,
        );

        return back()->with('success', 'Verknüpfung bestätigt.');
    }

    /**
     * Create a new local employee from a Ninox record and confirm the match.
     */
    public function createFrom(Request $request): RedirectResponse
    {
        $request->validate([
            'ninox_id' => 'required|string',
        ]);

        $employee = $this->service->createFrom(
            $request->ninox_id,
            $request->user()->id,
        );

        return redirect()
            ->route('admin.employees.edit', $employee)
            ->with('success', 'Neuer Mitarbeiter aus Ninox angelegt und verknüpft.');
    }

    /**
     * Ignore a Ninox employee (no import).
     */
    public function ignore(Request $request): RedirectResponse
    {
        $request->validate([
            'ninox_id' => 'required|string',
        ]);

        $this->service->ignore($request->ninox_id, $request->user()->id);

        return back()->with('success', 'Datensatz ignoriert.');
    }
}
