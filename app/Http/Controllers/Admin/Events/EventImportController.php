<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Events;

use App\Http\Controllers\Controller;
use App\Models\Events\EventImportLink;
use App\Models\Events\EventOccurrence;
use App\Services\Events\JtlOfferDiscoveryService;
use App\Services\Events\JtlOfferImportService;
use App\Services\Events\NinoxEventImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * PROJ-38: Admin controller for event data import (JTL discovery + Ninox import).
 */
class EventImportController extends Controller
{
    public function __construct(
        private readonly JtlOfferDiscoveryService $jtlDiscovery,
        private readonly JtlOfferImportService    $jtlImport,
        private readonly NinoxEventImportService  $ninoxImport,
    ) {}

    /**
     * GET /admin/veranstaltungen/import
     * Shows the discovery report and Ninox candidate list.
     */
    public function index(): View
    {
        $jtlReport = $this->jtlDiscovery->discover();

        $candidates = $this->ninoxImport->discoverEventCandidates();

        // Mark which candidates are already imported
        $importedIds = EventImportLink::where('entity_type', EventOccurrence::class)
            ->where('source_system', 'ninox')
            ->pluck('source_record_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $candidates = $candidates->map(function ($c) use ($importedIds) {
            $c->already_imported = $importedIds->has((int) $c->ninox_id);
            return $c;
        });

        return view('admin.events.import.index', compact('jtlReport', 'candidates'));
    }

    /**
     * GET /admin/veranstaltungen/import/ninox/preview/{ninoxId}
     * Shows a preview of what would be imported for the given ninox_id.
     */
    public function previewNinox(int $ninoxId): View
    {
        $record = DB::table('ninox_bestellannahme')->where('ninox_id', $ninoxId)->firstOrFail();

        $alreadyImported = EventImportLink::where('entity_type', EventOccurrence::class)
            ->where('source_system', 'ninox')
            ->where('source_record_id', (string) $ninoxId)
            ->exists();

        $statusMap = $this->ninoxImport->mapNinoxStatus((string) ($record->status ?? ''));

        // Load associated veranstaltung if present
        $veranstaltung = null;
        if (!empty($record->gehoert_zu_veranstaltung)) {
            $veranstaltung = DB::table('ninox_veranstaltung')
                ->where('ninox_id', $record->gehoert_zu_veranstaltung)
                ->first();
        }

        return view('admin.events.import.preview', compact(
            'record', 'alreadyImported', 'statusMap', 'veranstaltung', 'ninoxId'
        ));
    }

    /**
     * POST /admin/veranstaltungen/import/ninox
     * Imports one or all Ninox event candidates.
     */
    public function importNinox(Request $request): RedirectResponse
    {
        $ninoxId = $request->input('ninox_id');

        if ($ninoxId !== null) {
            // Single import
            $occurrence = $this->ninoxImport->importEventOccurrence((int) $ninoxId);

            if ($occurrence === null) {
                return redirect()
                    ->route('admin.events.import.index')
                    ->with('warning', "Ninox #{$ninoxId} wurde bereits importiert.");
            }

            return redirect()
                ->route('admin.events.show', $occurrence)
                ->with('success', "Ninox #{$ninoxId} erfolgreich importiert als Veranstaltung #{$occurrence->id}.");
        }

        // Bulk import all candidates
        $candidates = $this->ninoxImport->discoverEventCandidates();
        $imported   = 0;
        $skipped    = 0;
        $errors     = 0;

        foreach ($candidates as $candidate) {
            try {
                $result = $this->ninoxImport->importEventOccurrence((int) $candidate->ninox_id);
                if ($result === null) {
                    $skipped++;
                } else {
                    $imported++;
                }
            } catch (\Throwable) {
                $errors++;
            }
        }

        return redirect()
            ->route('admin.events.import.index')
            ->with('success', "Import abgeschlossen: {$imported} importiert, {$skipped} übersprungen, {$errors} Fehler.");
    }

    /**
     * GET /admin/veranstaltungen/import/jtl
     * Lists unimported JTL offers with auto-match suggestions.
     */
    public function jtlCandidates(): View
    {
        $candidates = $this->jtlImport->enrichCandidates(
            $this->jtlImport->getCandidates()
        );

        $occurrences = EventOccurrence::with('customer')
            ->whereIn('event_status', ['planned', 'confirmed', 'requested'])
            ->orderBy('event_start_at', 'desc')
            ->get();

        return view('admin.events.import.jtl-candidates', compact('candidates', 'occurrences'));
    }

    /**
     * POST /admin/veranstaltungen/import/jtl
     * Imports a single JTL offer into the given EventOccurrence.
     */
    public function importJtl(Request $request): RedirectResponse
    {
        $request->validate([
            'k_auftrag'     => 'required|integer',
            'occurrence_id' => 'required|integer|exists:event_occurrences,id',
        ]);

        $version = $this->jtlImport->import(
            (int) $request->k_auftrag,
            (int) $request->occurrence_id,
        );

        return redirect()
            ->route('admin.events.offers.show', [$version->occurrence, $version])
            ->with('success', 'JTL-Angebot ' . $version->external_offer_number . ' importiert.');
    }

    /**
     * POST /admin/veranstaltungen/import/jtl/bulk
     * Imports all auto-matched JTL offers at once.
     */
    public function importJtlBulk(Request $request): RedirectResponse
    {
        $request->validate([
            'matches'                => 'required|array|min:1',
            'matches.*.k_auftrag'    => 'required|integer',
            'matches.*.occurrence_id'=> 'required|integer|exists:event_occurrences,id',
        ]);

        $imported = 0;
        $errors   = 0;

        foreach ($request->input('matches') as $pair) {
            try {
                $this->jtlImport->import((int) $pair['k_auftrag'], (int) $pair['occurrence_id']);
                $imported++;
            } catch (\Throwable) {
                $errors++;
            }
        }

        $msg = "{$imported} JTL-Angebote importiert.";
        if ($errors > 0) {
            $msg .= " {$errors} Fehler.";
        }

        return redirect()
            ->route('admin.events.import.jtl')
            ->with('success', $msg);
    }

    /**
     * POST /admin/veranstaltungen/import/jtl/auto
     * Imports JTL offers that have a known Ninox customer but no EventOccurrence yet.
     * Creates the EventOccurrence automatically from JTL data.
     */
    public function importJtlAuto(Request $request): RedirectResponse
    {
        $request->validate([
            'k_auftraege'   => 'required|array|min:1',
            'k_auftraege.*' => 'required|integer',
        ]);

        $imported = 0;
        $errors   = 0;

        foreach ($request->input('k_auftraege') as $kAuftrag) {
            try {
                $this->jtlImport->importWithNewOccurrence((int) $kAuftrag);
                $imported++;
            } catch (\Throwable) {
                $errors++;
            }
        }

        $msg = "{$imported} JTL-Angebote mit neuer Veranstaltung importiert.";
        if ($errors > 0) {
            $msg .= " {$errors} Fehler.";
        }

        return redirect()
            ->route('admin.events.import.jtl')
            ->with('success', $msg);
    }
}
