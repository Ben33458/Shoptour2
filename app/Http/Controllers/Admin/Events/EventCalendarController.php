<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Events;

use App\Http\Controllers\Controller;
use App\Models\Events\EventOccurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PROJ-38: Calendar view for EventOccurrences.
 */
class EventCalendarController extends Controller
{
    /**
     * GET /admin/veranstaltungen/kalender
     * Monthly calendar HTML shell.
     */
    public function index(Request $request): View
    {
        $year  = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        // Clamp
        $month = max(1, min(12, $month));

        $firstDay = \Carbon\Carbon::create($year, $month, 1);
        $lastDay  = $firstDay->copy()->endOfMonth();

        $occurrences = EventOccurrence::with('customer')
            ->where(function ($q) use ($firstDay, $lastDay) {
                $q->whereBetween('event_start_at', [$firstDay, $lastDay])
                  ->orWhere(function ($q2) use ($firstDay, $lastDay) {
                      $q2->where('event_year', $firstDay->year)
                         ->whereNull('event_start_at');
                  });
            })
            ->orderBy('event_start_at')
            ->get();

        $prevMonth = $firstDay->copy()->subMonth();
        $nextMonth = $firstDay->copy()->addMonth();

        return view('admin.events.calendar', compact(
            'year', 'month', 'firstDay', 'lastDay',
            'occurrences', 'prevMonth', 'nextMonth'
        ));
    }

    /**
     * GET /admin/veranstaltungen/kalender/data
     * JSON API for calendar events (can be used by JS widgets later).
     */
    public function data(Request $request): JsonResponse
    {
        $from = $request->input('from') ? \Carbon\Carbon::parse($request->input('from')) : now()->startOfMonth();
        $to   = $request->input('to')   ? \Carbon\Carbon::parse($request->input('to'))   : now()->endOfMonth();

        $occurrences = EventOccurrence::with('customer')
            ->whereBetween('event_start_at', [$from, $to])
            ->get()
            ->map(fn ($occ) => [
                'id'           => $occ->id,
                'title'        => $occ->title,
                'start'        => $occ->event_start_at?->toDateString(),
                'end'          => $occ->event_end_at?->toDateString(),
                'offer_status' => $occ->offer_status,
                'event_status' => $occ->event_status,
                'customer'     => $occ->customer?->company_name,
                'url'          => route('admin.events.show', $occ),
            ]);

        return response()->json($occurrences);
    }
}
