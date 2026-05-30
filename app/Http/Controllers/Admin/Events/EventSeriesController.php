<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Events;

use App\Http\Controllers\Controller;
use App\Models\Events\EventSeries;
use App\Models\Pricing\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PROJ-38: Admin CRUD for EventSeries (recurring event lineages).
 */
class EventSeriesController extends Controller
{
    /**
     * GET /admin/veranstaltungen/serien
     */
    public function index(Request $request): View
    {
        $query = EventSeries::with('customer')
            ->orderBy('name');

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where('name', 'LIKE', $term);
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        $series = $query->paginate(25)->withQueryString();

        $eventTypes = [
            'kerb', 'fastnacht', 'sommerfest', 'geburtstag', 'hochzeit',
            'kommunion', 'firmenfeier', 'vereinsfest', 'weihnachtsmarkt',
            'sonstiges', 'unknown',
        ];

        return view('admin.events.series.index', compact('series', 'eventTypes'));
    }

    /**
     * GET /admin/veranstaltungen/serien/{series}
     */
    public function show(EventSeries $series): View
    {
        $series->load(['customer', 'occurrences' => function ($q) {
            $q->orderByDesc('event_year');
        }]);

        return view('admin.events.series.show', compact('series'));
    }

    /**
     * GET /admin/veranstaltungen/serien/neu
     */
    public function create(): View
    {
        $customers  = Customer::orderBy('company_name')->get();
        $eventTypes = [
            'kerb', 'fastnacht', 'sommerfest', 'geburtstag', 'hochzeit',
            'kommunion', 'firmenfeier', 'vereinsfest', 'weihnachtsmarkt',
            'sonstiges', 'unknown',
        ];

        return view('admin.events.series.create', compact('customers', 'eventTypes'));
    }

    /**
     * POST /admin/veranstaltungen/serien
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'event_type'            => 'required|string|max:50',
            'customer_id'           => 'nullable|integer|exists:customers,id',
            'default_location_name' => 'nullable|string|max:255',
            'default_address'       => 'nullable|string',
            'default_notes'         => 'nullable|string',
            'is_active'             => 'nullable|boolean',
        ]);

        $series = EventSeries::create($validated);

        return redirect()
            ->route('admin.events.series.show', $series)
            ->with('success', 'Serie erstellt.');
    }

    /**
     * GET /admin/veranstaltungen/serien/{series}/bearbeiten
     */
    public function edit(EventSeries $series): View
    {
        $customers  = Customer::orderBy('company_name')->get();
        $eventTypes = [
            'kerb', 'fastnacht', 'sommerfest', 'geburtstag', 'hochzeit',
            'kommunion', 'firmenfeier', 'vereinsfest', 'weihnachtsmarkt',
            'sonstiges', 'unknown',
        ];

        return view('admin.events.series.edit', compact('series', 'customers', 'eventTypes'));
    }

    /**
     * PUT /admin/veranstaltungen/serien/{series}
     */
    public function update(Request $request, EventSeries $series): RedirectResponse
    {
        $validated = $request->validate([
            'name'                   => 'required|string|max:255',
            'event_type'             => 'required|string|max:50',
            'customer_id'            => 'nullable|integer|exists:customers,id',
            'default_location_name'  => 'nullable|string|max:255',
            'default_address'        => 'nullable|string',
            'default_notes'          => 'nullable|string',
            'is_active'              => 'nullable|boolean',
            'is_duplicate_suspect'   => 'nullable|boolean',
        ]);

        $series->update($validated);

        return redirect()
            ->route('admin.events.series.show', $series)
            ->with('success', 'Serie aktualisiert.');
    }
}
