<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Bestandsaufnahme;

use App\Http\Controllers\Controller;
use App\Models\Import\ImportBestandsaufnahmeKonflikt;
use App\Models\Import\ImportBestandsaufnahmeLauf;
use App\Models\Import\ImportBestandsaufnahmeMapping;
use App\Models\Import\ImportBestandsaufnahmeRohzeile;
use App\Models\Supplier\Supplier;
use App\Models\Warehouse;
use App\Services\Bestandsaufnahme\OdsImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminOdsImportController extends Controller
{
    public function __construct(
        private readonly OdsImportService $importService,
    ) {}

    /** GET /admin/bestandsaufnahme/ods-import */
    public function index(): View
    {
        $laeufe = ImportBestandsaufnahmeLauf::with('importiertVon')
            ->orderByDesc('created_at')
            ->paginate(20);

        $mappings = ImportBestandsaufnahmeMapping::with(['lieferant', 'lagerStandard'])
            ->orderBy('tabellenblatt')
            ->get();

        $warehouses = Warehouse::where('active', true)->orderBy('name')->get(['id', 'name']);
        $lieferanten = Supplier::orderBy('name')->get(['id', 'name']);

        return view('admin.bestandsaufnahme.ods-import.index', compact('laeufe', 'mappings', 'warehouses', 'lieferanten'));
    }

    /** POST /admin/bestandsaufnahme/ods-import/upload */
    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'ods_file' => ['required', 'file', 'mimes:ods,xlsx,xls', 'max:20480'],
        ]);

        $lauf = $this->importService->importFile($request->file('ods_file'), $request->user());

        return redirect()
            ->route('admin.bestandsaufnahme.ods-import.lauf', $lauf)
            ->with('success', "Importlauf #{$lauf->id} abgeschlossen. {$lauf->anzahl_rohzeilen} Zeilen, {$lauf->anzahl_konflikte} Konflikte.");
    }

    /** GET /admin/bestandsaufnahme/ods-import/{lauf} */
    public function lauf(ImportBestandsaufnahmeLauf $lauf, Request $request): View
    {
        $blatt = $request->get('blatt');

        $rohzeilen = ImportBestandsaufnahmeRohzeile::with(['product', 'konflikte'])
            ->where('importlauf_id', $lauf->id)
            ->when($blatt, fn($q) => $q->where('tabellenblatt', $blatt))
            ->when($request->status, fn($q) => $q->where('erkannt_status', $request->status))
            ->orderBy('tabellenblatt')
            ->orderBy('zeilennummer')
            ->paginate(50)
            ->withQueryString();

        $blaetter = ImportBestandsaufnahmeRohzeile::where('importlauf_id', $lauf->id)
            ->distinct()->pluck('tabellenblatt');

        return view('admin.bestandsaufnahme.ods-import.lauf', compact('lauf', 'rohzeilen', 'blaetter', 'blatt'));
    }

    /** POST /admin/bestandsaufnahme/ods-import/konflikte/{konflikt}/aktion */
    public function konfliktAktion(Request $request, ImportBestandsaufnahmeKonflikt $konflikt): RedirectResponse
    {
        $validated = $request->validate([
            'aktion' => ['required', 'in:uebernehmen,verwerfen,manuell,referenz'],
        ]);

        $konflikt->update([
            'aktion'       => $validated['aktion'],
            'bearbeitet_von' => $request->user()->id,
            'bearbeitet_am'  => now(),
        ]);

        return back()->with('success', 'Aktion gespeichert.');
    }

    /** POST /admin/bestandsaufnahme/ods-import/mappings */
    public function storeMapping(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tabellenblatt'           => ['required', 'string', 'max:100'],
            'lieferant_id'            => ['nullable', 'exists:suppliers,id'],
            'lager_id_standard'       => ['nullable', 'exists:warehouses,id'],
            'spalte_kolabri_artnr'    => ['nullable', 'string', 'max:50'],
            'spalte_lieferanten_artnr' => ['nullable', 'string', 'max:50'],
            'spalte_produktname'      => ['nullable', 'string', 'max:50'],
            'spalte_mindestbestand'   => ['nullable', 'string', 'max:50'],
            'spalte_bestand'          => ['nullable', 'string', 'max:50'],
            'spalte_bestellmenge'     => ['nullable', 'string', 'max:50'],
            'spalte_mhd'              => ['nullable', 'string', 'max:50'],
            'spalte_vpe_hinweis'      => ['nullable', 'string', 'max:50'],
            'spalte_bestellhinweis'   => ['nullable', 'string', 'max:50'],
            'blatt_typ'               => ['required', 'in:A,B,C,unbekannt'],
            'notiz'                   => ['nullable', 'string'],
        ]);

        ImportBestandsaufnahmeMapping::updateOrCreate(
            ['tabellenblatt' => $validated['tabellenblatt']],
            array_merge($validated, ['aktiv' => true]),
        );

        return back()->with('success', 'Mapping gespeichert.');
    }
}
