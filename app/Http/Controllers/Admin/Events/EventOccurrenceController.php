<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Events;

use App\Constants\EventLabels;
use App\Http\Controllers\Controller;
use App\Models\Admin\AuditLog;
use App\Models\Events\EventImportLink;
use App\Models\Events\EventOccurrence;
use App\Models\Events\EventSeries;
use App\Models\Pricing\Customer;
use App\Services\Events\OfferAcceptanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * PROJ-38: Admin CRUD for EventOccurrences.
 */
class EventOccurrenceController extends Controller
{
    public function __construct(
        private readonly OfferAcceptanceService $acceptanceService,
    ) {}

    // ── LIST ──────────────────────────────────────────────────

    /**
     * GET /admin/veranstaltungen
     * Tab 1: Kommende Veranstaltungen — Events ohne erfolgte Abholung, nicht abgelehnt.
     */
    public function index(Request $request): View
    {
        $doneStatuses = ['picked_up', 'post_calculation_open', 'closed'];

        $query = EventOccurrence::with(['customer', 'currentOffer'])
            ->whereNotIn('event_status', $doneStatuses)
            ->where(function ($q) {
                $q->whereNotIn('offer_status', ['rejected', 'cancelled'])
                  ->orWhereNull('offer_status');
            })
            ->orderBy('event_start_at', 'asc')
            ->orderBy('id', 'asc');

        if ($request->filled('offer_status')) {
            $query->where('offer_status', $request->input('offer_status'));
        }

        if ($request->filled('event_status')) {
            $query->where('event_status', $request->input('event_status'));
        }

        if ($request->filled('year')) {
            $query->where('event_year', (int) $request->input('year'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        if ($request->filled('source')) {
            $query->where('source_system', $request->input('source'));
        }

        if ($request->boolean('needs_review')) {
            $query->where('needs_review', true);
        }

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'LIKE', $term)
                  ->orWhere('location_name', 'LIKE', $term)
                  ->orWhere('city', 'LIKE', $term);
            });
        }

        $occurrences = $query->paginate(25)->withQueryString();

        $offerStatuses = [
            'requested'              => 'Anfrage',
            'needs_clarification'    => 'Klärung nötig',
            'draft'                  => 'Entwurf',
            'external_offer_created' => 'Ext. Angebot erstellt',
            'sent'                   => 'Angebot versendet',
            'revision_requested'     => 'Überarbeitung angefordert',
            'accepted'               => 'Angenommen',
            'rejected'               => 'Abgelehnt',
            'expired'                => 'Abgelaufen',
            'cancelled'              => 'Storniert',
            'converted_to_order'     => 'In Bestellung umgewandelt',
        ];

        $eventStatuses = [
            'planned'               => 'Geplant',
            'confirmed'             => 'Bestätigt',
            'delivery_planned'      => 'Lieferung geplant',
            'partially_delivered'   => 'Teilgeliefert',
            'delivered'             => 'Geliefert',
            'during_event'          => 'Laufend',
            'pickup_planned'        => 'Abholung geplant',
            'picked_up'             => 'Abgeholt',
            'post_calculation_open' => 'Nachkalkulation offen',
            'closed'                => 'Abgeschlossen',
        ];

        $eventTypes = EventLabels::eventTypes();

        $reviewCount = EventOccurrence::where('needs_review', true)->count();

        return view('admin.events.index', compact(
            'occurrences', 'offerStatuses', 'eventStatuses', 'eventTypes', 'reviewCount'
        ) + ['activeTab' => 'upcoming']);
    }

    /**
     * GET /admin/veranstaltungen/offene-angebote
     * Tab 2: Alle Events mit noch nicht entschiedenem Angebotsstatus.
     */
    public function openOffers(Request $request): View
    {
        $openStatuses = [
            'requested', 'needs_clarification', 'draft',
            'external_offer_created', 'sent', 'revision_requested',
        ];

        $query = EventOccurrence::with(['customer', 'currentOffer'])
            ->whereIn('offer_status', $openStatuses);

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'LIKE', $term)
                  ->orWhere('location_name', 'LIKE', $term)
                  ->orWhere('city', 'LIKE', $term);
            });
        }

        $occurrences = $query->orderBy('event_start_at', 'asc')->orderBy('id')->paginate(25)->withQueryString();
        $reviewCount = EventOccurrence::where('needs_review', true)->count();

        return view('admin.events.index', [
            'occurrences'   => $occurrences,
            'activeTab'     => 'open-offers',
            'reviewCount'   => $reviewCount,
            'offerStatuses' => [],
            'eventStatuses' => [],
            'eventTypes'    => [],
        ]);
    }

    /**
     * GET /admin/veranstaltungen/angebote-todo
     * Tab 3: Events, bei denen wir noch handeln müssen.
     */
    public function todoOffers(Request $request): View
    {
        $todoStatuses = ['requested', 'needs_clarification', 'draft', 'revision_requested'];

        $query = EventOccurrence::with(['customer', 'currentOffer'])
            ->whereIn('offer_status', $todoStatuses);

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'LIKE', $term)
                  ->orWhere('location_name', 'LIKE', $term)
                  ->orWhere('city', 'LIKE', $term);
            });
        }

        $occurrences = $query->orderBy('event_start_at', 'asc')->orderBy('id')->paginate(25)->withQueryString();
        $reviewCount = EventOccurrence::where('needs_review', true)->count();

        return view('admin.events.index', [
            'occurrences'   => $occurrences,
            'activeTab'     => 'todo-offers',
            'reviewCount'   => $reviewCount,
            'offerStatuses' => [],
            'eventStatuses' => [],
            'eventTypes'    => [],
        ]);
    }

    /**
     * GET /admin/veranstaltungen/abrechnung-offen
     * Tab 4: Fertig abgeholt, aber noch nicht abgerechnet/geschlossen.
     */
    public function billingOpen(Request $request): View
    {
        $query = EventOccurrence::with(['customer', 'currentOffer'])
            ->whereIn('event_status', ['picked_up', 'post_calculation_open']);

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'LIKE', $term)
                  ->orWhere('location_name', 'LIKE', $term)
                  ->orWhere('city', 'LIKE', $term);
            });
        }

        $occurrences = $query->orderBy('event_start_at', 'asc')->orderBy('id')->paginate(25)->withQueryString();
        $reviewCount = EventOccurrence::where('needs_review', true)->count();

        return view('admin.events.index', [
            'occurrences'   => $occurrences,
            'activeTab'     => 'billing-open',
            'reviewCount'   => $reviewCount,
            'offerStatuses' => [],
            'eventStatuses' => [],
            'eventTypes'    => [],
        ]);
    }

    /**
     * GET /admin/veranstaltungen/anfragen
     * Filtered view showing only unprocessed requests.
     */
    public function requests(Request $request): View
    {
        $occurrences = EventOccurrence::with(['customer', 'currentOffer'])
            ->whereIn('offer_status', ['requested', 'needs_clarification', 'draft'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.events.requests', compact('occurrences'));
    }

    /**
     * GET /admin/veranstaltungen/nachkalkulation
     * Events with open post-calculation.
     */
    public function postCalcOpen(Request $request): View
    {
        $occurrences = EventOccurrence::with(['customer'])
            ->where('event_status', 'post_calculation_open')
            ->orderByDesc('event_start_at')
            ->paginate(25);

        return view('admin.events.post-calc-open', compact('occurrences'));
    }

    // ── SHOW ──────────────────────────────────────────────────

    /**
     * GET /admin/veranstaltungen/{occurrence}
     */
    public function show(EventOccurrence $occurrence): View
    {
        $occurrence->load([
            'series',
            'customer',
            'billingCustomer',
            'offerVersions.items',
            'contacts',
            'logisticsAppointments',
            'rentalReservations',
            'weatherSnapshots',
            'postCalculation',
            'tasks',
            'importLinks',
        ]);

        $auditLogs = AuditLog::where('subject_type', EventOccurrence::class)
            ->where('subject_id', $occurrence->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        // Previous year occurrence (same series, year - 1)
        $previousYear = null;
        if ($occurrence->event_series_id && $occurrence->event_year) {
            $previousYear = EventOccurrence::where('event_series_id', $occurrence->event_series_id)
                ->where('event_year', $occurrence->event_year - 1)
                ->first();
        }

        return view('admin.events.show', compact('occurrence', 'auditLogs', 'previousYear'));
    }

    // ── CREATE / STORE ────────────────────────────────────────

    /**
     * GET /admin/veranstaltungen/neu
     */
    public function create(): View
    {
        $series    = EventSeries::orderBy('name')->get();
        $customers = Customer::orderBy('company_name')->get();

        $eventTypes = EventLabels::eventTypes();

        return view('admin.events.create', compact('series', 'customers', 'eventTypes'));
    }

    /**
     * POST /admin/veranstaltungen
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'event_type'      => 'required|string|max:50',
            'event_series_id' => 'nullable|integer|exists:event_series,id',
            'customer_id'     => 'nullable|integer|exists:customers,id',
            'event_start_at'  => 'nullable|date',
            'event_end_at'    => 'nullable|date',
            'location_name'   => 'nullable|string|max:255',
            'address_line1'   => 'nullable|string|max:255',
            'address_line2'   => 'nullable|string|max:255',
            'postal_code'     => 'nullable|string|max:20',
            'city'            => 'nullable|string|max:100',
            'expected_guests' => 'nullable|integer|min:0',
            'request_channel' => 'nullable|string|max:30',
            'offer_status'    => 'nullable|string|max:50',
            'internal_notes'  => 'nullable|string',
        ]);

        $validated['event_year'] = isset($validated['event_start_at'])
            ? (int) date('Y', strtotime($validated['event_start_at']))
            : null;

        $occurrence = EventOccurrence::create($validated);

        return redirect()
            ->route('admin.events.show', $occurrence)
            ->with('success', 'Veranstaltung erstellt.');
    }

    // ── EDIT / UPDATE ─────────────────────────────────────────

    /**
     * GET /admin/veranstaltungen/{occurrence}/bearbeiten
     */
    public function edit(EventOccurrence $occurrence): View
    {
        $series    = EventSeries::orderBy('name')->get();
        $customers = Customer::orderBy('company_name')->get();

        $eventTypes = EventLabels::eventTypes();

        return view('admin.events.edit', compact('occurrence', 'series', 'customers', 'eventTypes'));
    }

    /**
     * PUT /admin/veranstaltungen/{occurrence}
     */
    public function update(Request $request, EventOccurrence $occurrence): RedirectResponse
    {
        $validated = $request->validate([
            'title'              => 'required|string|max:255',
            'event_type'         => 'required|string|max:50',
            'event_series_id'    => 'nullable|integer|exists:event_series,id',
            'customer_id'        => 'nullable|integer|exists:customers,id',
            'billing_customer_id'=> 'nullable|integer|exists:customers,id',
            'event_start_at'     => 'nullable|date',
            'event_end_at'       => 'nullable|date',
            'event_status'       => 'nullable|string|max:50',
            'offer_status'       => 'nullable|string|max:50',
            'location_name'      => 'nullable|string|max:255',
            'address_line1'      => 'nullable|string|max:255',
            'address_line2'      => 'nullable|string|max:255',
            'postal_code'        => 'nullable|string|max:20',
            'city'               => 'nullable|string|max:100',
            'expected_guests'    => 'nullable|integer|min:0',
            'actual_guests'      => 'nullable|integer|min:0',
            'indoor_outdoor_type'=> 'nullable|string|max:20',
            'request_channel'    => 'nullable|string|max:30',
            'needs_review'       => 'nullable|boolean',
            'internal_notes'     => 'nullable|string',
            'customer_visible_notes' => 'nullable|string',
        ]);

        if (isset($validated['event_start_at'])) {
            $validated['event_year'] = (int) date('Y', strtotime($validated['event_start_at']));
        }

        $occurrence->update($validated);

        return redirect()
            ->route('admin.events.show', $occurrence)
            ->with('success', 'Veranstaltung aktualisiert.');
    }

    // ── DESTROY ───────────────────────────────────────────────

    /**
     * DELETE /admin/veranstaltungen/{occurrence}
     */
    public function destroy(EventOccurrence $occurrence): RedirectResponse
    {
        $occurrence->delete();

        return redirect()
            ->route('admin.events.index')
            ->with('success', 'Veranstaltung gelöscht.');
    }

    // ── CALENDAR ──────────────────────────────────────────────

    /**
     * GET /admin/veranstaltungen/kalender  (HTML shell — see EventCalendarController)
     */
    public function calendar(): View
    {
        return view('admin.events.calendar');
    }
}
