<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee\EmployeeNotification;
use App\Models\Employee\TimeEntry;
use App\Services\Employee\BreakCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TimeTrackingAdminController extends Controller
{
    public function __construct(private readonly BreakCalculationService $breakCalc) {}

    public function index(Request $request)
    {
        $today     = now()->toDateString();
        $weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd   = now()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $from = $request->input('from', $weekStart);
        $to   = $request->input('to', $weekEnd);

        // Ensure valid range (max 31 days)
        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 31) {
            $to = Carbon::parse($from)->addDays(30)->toDateString();
        }

        $entries = TimeEntry::with('employee', 'shift', 'breakSegments')
            ->whereBetween(\DB::raw('DATE(clocked_in_at)'), [$from, $to])
            ->orderBy('clocked_in_at')
            ->get();

        $open = TimeEntry::with('employee', 'shift')
            ->whereNull('clocked_out_at')
            ->get();

        return view('admin.time-tracking.index', compact('entries', 'open', 'from', 'to', 'today'));
    }

    public function correct(Request $request, TimeEntry $entry)
    {
        $data = $request->validate([
            'clocked_in_at'  => 'required|date',
            'clocked_out_at' => 'nullable|date|after:clocked_in_at',
        ]);
        $data['is_manual_correction'] = true;
        $data['corrected_by']         = auth()->id();
        $entry->update($data);

        if ($entry->clocked_out_at) {
            $entry->refresh();
            $result = $this->breakCalc->finalize($entry);
            $entry->update([
                'break_minutes'    => $result['break_minutes'],
                'net_minutes'      => $result['net_minutes'],
                'compliance_status'=> $result['compliance_status'],
                'compliance_notes' => $result['compliance_notes'],
            ]);
        }

        $this->notifyEmployee($entry, 'Zeitkorrektur durch Admin',
            'Deine Zeiterfassung wurde manuell korrigiert: ' .
            Carbon::parse($data['clocked_in_at'])->format('d.m.Y H:i') . ' – ' .
            (isset($data['clocked_out_at']) && $data['clocked_out_at']
                ? Carbon::parse($data['clocked_out_at'])->format('H:i')
                : 'offen') . ' Uhr.'
        );

        return back()->with('success', 'Zeiteintrag korrigiert.');
    }

    /** One-click: set clocked_in_at = shift.planned_start */
    public function correctToShiftStart(Request $request, TimeEntry $entry)
    {
        if (!$entry->shift) {
            return back()->with('error', 'Kein Schichtbezug für diesen Eintrag.');
        }

        $plannedStart = $entry->shift->planned_start;
        $entry->update([
            'clocked_in_at'       => $plannedStart,
            'is_manual_correction'=> true,
            'corrected_by'        => auth()->id(),
        ]);

        if ($entry->clocked_out_at) {
            $entry->refresh();
            $result = $this->breakCalc->finalize($entry);
            $entry->update([
                'break_minutes'    => $result['break_minutes'],
                'net_minutes'      => $result['net_minutes'],
                'compliance_status'=> $result['compliance_status'],
                'compliance_notes' => $result['compliance_notes'],
            ]);
        }

        $this->notifyEmployee($entry, 'Verspätung korrigiert',
            'Dein Einstempeln wurde auf den Schichtbeginn (' . $plannedStart->format('H:i') . ' Uhr) korrigiert. ' .
            'Bitte denk daran, pünktlich einzustempeln.'
        );

        return back()->with('success', 'Eingestempelt-Zeit auf Schichtbeginn gesetzt.');
    }

    /** One-click: set clocked_out_at = shift.planned_end */
    public function correctToShiftEnd(Request $request, TimeEntry $entry)
    {
        if (!$entry->shift) {
            return back()->with('error', 'Kein Schichtbezug für diesen Eintrag.');
        }

        $plannedEnd = $entry->shift->planned_end;
        $entry->update([
            'clocked_out_at'      => $plannedEnd,
            'is_manual_correction'=> true,
            'corrected_by'        => auth()->id(),
        ]);

        $entry->refresh();
        $result = $this->breakCalc->finalize($entry);
        $entry->update([
            'break_minutes'    => $result['break_minutes'],
            'net_minutes'      => $result['net_minutes'],
            'compliance_status'=> $result['compliance_status'],
            'compliance_notes' => $result['compliance_notes'],
        ]);

        $this->notifyEmployee($entry, 'Überstunden korrigiert',
            'Deine Ausstempelzeit wurde auf das Schichtende (' . $plannedEnd->format('H:i') . ' Uhr) korrigiert. ' .
            'Bitte denk daran, rechtzeitig auszustempeln.'
        );

        return back()->with('success', 'Ausgestempelt-Zeit auf Schichtende gesetzt.');
    }

    private function notifyEmployee(TimeEntry $entry, string $title, string $message): void
    {
        if (!$entry->employee_id) return;
        EmployeeNotification::create([
            'employee_id' => $entry->employee_id,
            'type'        => 'correction',
            'title'       => $title,
            'message'     => $message,
        ]);
    }
}
