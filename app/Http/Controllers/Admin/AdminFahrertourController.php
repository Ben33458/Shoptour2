<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery\RegularDeliveryTour;
use App\Models\Delivery\Tour;
use App\Models\Employee\Employee;
use App\Services\Delivery\TourPlannerService;
use App\Services\Ninox\NinoxPullService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminFahrertourController extends Controller
{
    public function index(Request $request): View
    {
        $date        = $request->input('date');
        $status      = $request->input('status');
        $defaultMode = ! $date;

        $query = Tour::with(['regularDeliveryTour', 'stops']);

        if ($date) {
            $query->whereDate('tour_date', $date)
                  ->orderBy('tour_date', 'asc')
                  ->orderBy('id', 'asc');
        } else {
            $query->whereNotIn('status', [Tour::STATUS_DONE, Tour::STATUS_CANCELLED])
                  ->whereDate('tour_date', '>=', today())
                  ->orderBy('tour_date', 'asc')
                  ->orderBy('id', 'asc')
                  ->limit(6);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $tours = $query->get();

        $employeeIds = $tours->pluck('driver_employee_id')->filter()->unique()->values()->all();
        $employees   = Employee::whereIn('id', $employeeIds)
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        return view('admin.touren.index', compact('tours', 'employees', 'date', 'status', 'defaultMode'));
    }

    public function create(): View
    {
        $templates = RegularDeliveryTour::where('active', true)->orderBy('name')->get();

        return view('admin.touren.create', compact('templates'));
    }

    public function store(Request $request, TourPlannerService $planner): RedirectResponse
    {
        $request->validate([
            'regular_delivery_tour_id' => 'required|exists:regular_delivery_tours,id',
            'tour_date'                => 'required|date',
        ]);

        try {
            $tour = $planner->createTourForDate(
                Carbon::parse($request->input('tour_date')),
                (int) $request->input('regular_delivery_tour_id')
            );

            return redirect()
                ->route('admin.touren.show', $tour)
                ->with('success', 'Tour wurde angelegt mit ' . $tour->stops->count() . ' Stopp(s).');
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Tour $tour): View
    {
        $tour->load([
            'regularDeliveryTour',
            'stops.order.customer',
            'stops.order.items',
        ]);

        $employees = Employee::orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        $driver = $tour->driver_employee_id
            ? Employee::find($tour->driver_employee_id)
            : null;

        $prevTour = Tour::with('regularDeliveryTour')
            ->where(function ($q) use ($tour) {
                $q->whereDate('tour_date', '<', $tour->tour_date)
                  ->orWhere(fn ($q2) => $q2->whereDate('tour_date', $tour->tour_date)->where('id', '<', $tour->id));
            })->orderBy('tour_date', 'desc')->orderBy('id', 'desc')->first();

        $nextTour = Tour::with('regularDeliveryTour')
            ->where(function ($q) use ($tour) {
                $q->whereDate('tour_date', '>', $tour->tour_date)
                  ->orWhere(fn ($q2) => $q2->whereDate('tour_date', $tour->tour_date)->where('id', '>', $tour->id));
            })->orderBy('tour_date', 'asc')->orderBy('id', 'asc')->first();

        return view('admin.touren.show', compact('tour', 'employees', 'driver', 'prevTour', 'nextTour'));
    }

    public function updateDriver(Request $request, Tour $tour): RedirectResponse
    {
        $request->validate([
            'driver_employee_id' => 'nullable|exists:employees,id',
        ]);

        $tour->update(['driver_employee_id' => $request->input('driver_employee_id') ?: null]);

        return back()->with('success', 'Fahrer wurde aktualisiert.');
    }

    public function syncFromNinox(Tour $tour, NinoxPullService $pull): RedirectResponse
    {
        if (! $tour->ninox_id) {
            return back()->with('error', 'Diese Tour hat keine Ninox-ID — kein Sync möglich.');
        }

        try {
            $changed = $pull->pullTourStatus($tour);
            $message = $changed
                ? 'Tour-Status von Ninox aktualisiert: ' . $tour->fresh()->status . '. Warenkorb-Daten wurden ebenfalls synchronisiert.'
                : 'Status bereits aktuell. Warenkorb-Daten wurden synchronisiert.';

            return back()->with('success', $message);
        } catch (\Throwable $e) {
            return back()->with('error', 'Ninox-Abfrage fehlgeschlagen: ' . $e->getMessage());
        }
    }

    public function pullFromNinox(NinoxPullService $pull): RedirectResponse
    {
        try {
            $imported = $pull->pullTours();
            return back()->with('success', "Ninox-Import abgeschlossen: {$imported} Tour(en) importiert.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Ninox-Import fehlgeschlagen: ' . $e->getMessage());
        }
    }

    public function destroy(Tour $tour): RedirectResponse
    {
        if ($tour->status !== Tour::STATUS_PLANNED) {
            return back()->with('error', 'Nur geplante Touren können gelöscht werden.');
        }

        $tour->stops()->delete();
        $tour->delete();

        return redirect()->route('admin.touren.index')
            ->with('success', 'Tour wurde gelöscht.');
    }
}
