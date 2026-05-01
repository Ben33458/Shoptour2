<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SourceMatch;
use App\Models\Supplier\Supplier;
use App\Services\Reconcile\SupplierReconcileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReconcileSupplierController extends Controller
{
    public function __construct(
        private readonly SupplierReconcileService $service,
    ) {}

    /**
     * Show all Ninox suppliers with match proposals.
     * Supports ?sort=name|email|confidence and ?dir=asc|desc and ?search=
     */
    public function index(Request $request): View
    {
        $sort   = $request->input('sort', 'confidence');
        $dir    = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $search = trim((string) $request->input('search', ''));

        $proposals = $this->service->proposeMatches();

        if ($search !== '') {
            $proposals = array_values(array_filter($proposals, function (array $p) use ($search): bool {
                $d    = $p['source_data'];
                $name = strtolower($d['name'] ?? '');
                $mail = strtolower($d['kontakt_e_mail'] ?? '');
                $q    = strtolower($search);
                return str_contains($name, $q) || str_contains($mail, $q);
            }));
        }

        usort($proposals, function (array $a, array $b) use ($sort, $dir): int {
            $valA = match ($sort) {
                'name'  => mb_strtolower((string) ($a['source_data']['name'] ?? '')),
                'email' => mb_strtolower((string) ($a['source_data']['kontakt_e_mail'] ?? '')),
                default => $a['confidence'],
            };
            $valB = match ($sort) {
                'name'  => mb_strtolower((string) ($b['source_data']['name'] ?? '')),
                'email' => mb_strtolower((string) ($b['source_data']['kontakt_e_mail'] ?? '')),
                default => $b['confidence'],
            };
            if ($valA === $valB) return 0;
            $cmp = $valA <=> $valB;
            return $dir === 'asc' ? $cmp : -$cmp;
        });

        return view('admin.reconcile.suppliers', compact('proposals', 'sort', 'dir', 'search'));
    }

    /**
     * Confirm a match between a Ninox supplier and a local supplier.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'source_id'   => 'required|string',
            'supplier_id' => 'required|integer|exists:suppliers,id',
        ]);

        $this->service->confirm(
            'ninox',
            $request->source_id,
            (int) $request->supplier_id,
            $request->user()->id,
        );

        return back()->with('success', 'Verknüpfung bestätigt.');
    }

    /**
     * Create a new local supplier from a Ninox record.
     */
    public function createFrom(Request $request): RedirectResponse
    {
        $request->validate([
            'source_id' => 'required|string',
        ]);

        $sourceId  = $request->source_id;
        $proposals = $this->service->proposeMatches();
        $proposal  = collect($proposals)->firstWhere('source_id', $sourceId);

        if (! $proposal) {
            return back()->with('error', 'Quelldatensatz nicht gefunden.');
        }

        $data = $proposal['source_data'];

        $supplier = Supplier::create([
            'name'         => $data['name'] ?? 'Unbekannt',
            'email'        => $data['kontakt_e_mail'] ?? null,
            'phone'        => $data['telefon'] ?? null,
            'type'         => Supplier::TYPE_SUPPLIER,
            'currency'     => 'EUR',
            'active'       => true,
        ]);

        $this->service->confirm('ninox', $sourceId, $supplier->id, $request->user()->id);

        return redirect()
            ->route('admin.suppliers.edit', $supplier)
            ->with('success', 'Neuer Lieferant angelegt und verknüpft.');
    }

    /**
     * Lieferanten-Datensatz ablehnen.
     */
    public function ignore(Request $request): RedirectResponse
    {
        $request->validate([
            'source_id' => 'required|string',
        ]);

        $this->service->ignore('ninox', $request->source_id, $request->user()->id);

        return back()->with('success', 'Datensatz abgelehnt.');
    }
}
