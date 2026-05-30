<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Events;

use App\Http\Controllers\Controller;
use App\Models\Events\EventOccurrence;
use App\Models\Events\EventOfferItem;
use App\Models\Events\EventOfferVersion;
use App\Services\Events\EventOfferAuditService;
use App\Services\Events\OfferAcceptanceService;
use App\Services\Events\OfferNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * PROJ-38: Admin controller for EventOfferVersions.
 */
class EventOfferController extends Controller
{
    public function __construct(
        private readonly OfferNumberService    $offerNumberService,
        private readonly OfferAcceptanceService $acceptanceService,
        private readonly EventOfferAuditService $auditService,
    ) {}

    // ── CREATE / STORE ────────────────────────────────────────

    /**
     * GET /admin/veranstaltungen/{occurrence}/angebote/neu
     */
    public function create(EventOccurrence $occurrence): View
    {
        $occurrence->load('offerVersions');

        return view('admin.events.offers.create', compact('occurrence'));
    }

    /**
     * POST /admin/veranstaltungen/{occurrence}/angebote
     */
    public function store(Request $request, EventOccurrence $occurrence): RedirectResponse
    {
        $validated = $request->validate([
            'valid_until'         => 'nullable|date',
            'net_total_milli'     => 'nullable|integer',
            'gross_total_milli'   => 'nullable|integer',
            'deposit_total_milli' => 'nullable|integer',
            'rental_total_milli'  => 'nullable|integer',
            'delivery_total_milli'=> 'nullable|integer',
        ]);

        // Determine next version number
        $nextVersion = $occurrence->offerVersions()->max('version_number') + 1;

        $offerNumber = $this->offerNumberService->generate();

        $version = EventOfferVersion::create(array_merge($validated, [
            'event_occurrence_id' => $occurrence->id,
            'offer_number'        => $offerNumber,
            'version_number'      => $nextVersion,
            'source_system'       => 'shoptour2',
            'status'              => 'draft',
            'created_by_user_id'  => auth()->id(),
        ]));

        $this->auditService->log('event.offer.created', $version, [
            'offer_number'   => $offerNumber,
            'version_number' => $nextVersion,
        ]);

        return redirect()
            ->route('admin.events.offers.show', [$occurrence, $version])
            ->with('success', 'Angebot ' . $offerNumber . ' erstellt.');
    }

    // ── SHOW ──────────────────────────────────────────────────

    /**
     * GET /admin/veranstaltungen/{occurrence}/angebote/{version}
     */
    public function show(EventOccurrence $occurrence, EventOfferVersion $version): View
    {
        $version->load('items');

        return view('admin.events.offers.show', compact('occurrence', 'version'));
    }

    // ── ACCEPT ────────────────────────────────────────────────

    /**
     * POST /admin/veranstaltungen/{occurrence}/angebote/{version}/annehmen
     */
    public function accept(Request $request, EventOccurrence $occurrence, EventOfferVersion $version): RedirectResponse
    {
        $result = $this->acceptanceService->accept($version, auth()->id());

        if ($result['success']) {
            return redirect()
                ->route('admin.events.show', $occurrence)
                ->with('success', 'Angebot angenommen. Bestellung ' . $result['order']->order_number . ' erstellt.');
        }

        $errorList = implode(', ', $result['errors']);

        return redirect()
            ->route('admin.events.show', $occurrence)
            ->with('warning', 'Angebotsannahme erfordert manuelle Prüfung: ' . $errorList);
    }

    // ── PDF UPLOAD ────────────────────────────────────────────

    /**
     * POST /admin/veranstaltungen/{occurrence}/angebote/{version}/pdf
     */
    public function uploadPdf(Request $request, EventOccurrence $occurrence, EventOfferVersion $version): RedirectResponse
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:20480',
        ]);

        $path = Storage::disk('local')->putFile(
            'event-offers/' . $occurrence->id,
            $request->file('pdf')
        );

        $version->update(['pdf_file_path' => $path]);

        $this->auditService->log('event.offer.pdf_uploaded', $version, [
            'path' => $path,
        ]);

        return redirect()
            ->route('admin.events.offers.show', [$occurrence, $version])
            ->with('success', 'PDF hochgeladen.');
    }

    // ── NEW VERSION ───────────────────────────────────────────

    /**
     * POST /admin/veranstaltungen/{occurrence}/angebote/{version}/neue-version
     * Duplicates the given version as a new draft version.
     */
    public function createVersion(Request $request, EventOccurrence $occurrence, EventOfferVersion $version): RedirectResponse
    {
        $nextVersion = $occurrence->offerVersions()->max('version_number') + 1;
        $offerNumber = $this->offerNumberService->generate();

        $newVersion = EventOfferVersion::create([
            'event_occurrence_id'  => $occurrence->id,
            'offer_number'         => $offerNumber,
            'version_number'       => $nextVersion,
            'source_system'        => 'shoptour2',
            'status'               => 'draft',
            'valid_until'          => $version->valid_until,
            'net_total_milli'      => $version->net_total_milli,
            'gross_total_milli'    => $version->gross_total_milli,
            'deposit_total_milli'  => $version->deposit_total_milli,
            'rental_total_milli'   => $version->rental_total_milli,
            'delivery_total_milli' => $version->delivery_total_milli,
            'created_by_user_id'   => auth()->id(),
        ]);

        // Duplicate items
        foreach ($version->items as $item) {
            EventOfferItem::create(array_merge(
                $item->only($item->getFillable()),
                ['event_offer_version_id' => $newVersion->id]
            ));
        }

        $this->auditService->log('event.offer.version_created', $newVersion, [
            'copied_from_version' => $version->id,
            'offer_number'        => $offerNumber,
            'version_number'      => $nextVersion,
        ]);

        return redirect()
            ->route('admin.events.offers.show', [$occurrence, $newVersion])
            ->with('success', 'Neue Angebotsversion ' . $offerNumber . ' (V' . $nextVersion . ') erstellt.');
    }
}
