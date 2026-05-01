<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee\ChecklistItem;
use App\Models\Employee\ChecklistTemplate;
use App\Models\Employee\Shift;
use App\Models\Employee\ShiftReport;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ShiftReportController extends Controller
{
    public function index(Request $request)
    {
        $weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd   = now()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $from = $request->input('from', $weekStart);
        $to   = $request->input('to', $weekEnd);

        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 31) {
            $to = Carbon::parse($from)->addDays(30)->toDateString();
        }

        $reports = ShiftReport::with('shift.employee', 'shift.shiftArea', 'employee')
            ->whereHas('shift', fn($q) => $q->whereBetween(\DB::raw('DATE(planned_start)'), [$from, $to]))
            ->orderByDesc('created_at')
            ->get();

        $shiftsWithoutReport = Shift::with('employee', 'shiftArea')
            ->whereBetween(\DB::raw('DATE(planned_start)'), [$from, $to])
            ->whereDoesntHave('report')
            ->orderBy('planned_start')
            ->get();

        return view('admin.shifts.reports.index', compact('reports', 'shiftsWithoutReport', 'from', 'to'));
    }

    public function create(Shift $shift)
    {
        if ($shift->report) {
            return redirect()->route('admin.shifts.reports.edit', $shift->report);
        }
        $templates = ChecklistTemplate::active()->with('items')->get();
        return view('admin.shifts.reports.form', compact('shift', 'templates'));
    }

    public function store(Request $request, Shift $shift)
    {
        $data = $request->validate([
            'summary'         => 'nullable|string|max:2000',
            'customer_count'  => 'nullable|integer|min:0',
            'cash_difference' => 'nullable|numeric',
            'incident_level'  => 'required|in:none,minor,major',
            'incident_notes'  => 'nullable|string|max:1000',
            'checklist'       => 'nullable|array',
            'checklist.*'     => 'nullable|string',
        ]);

        $report = ShiftReport::create([
            'shift_id'        => $shift->id,
            'employee_id'     => $shift->employee_id,
            'summary'         => $data['summary'] ?? null,
            'customer_count'  => $data['customer_count'] ?? null,
            'cash_difference' => $data['cash_difference'] ?? null,
            'incident_level'  => $data['incident_level'],
            'incident_notes'  => $data['incident_notes'] ?? null,
        ]);

        // Sync checklist items (checkbox array — presence = checked)
        $checkedIds = array_keys($data['checklist'] ?? []);
        $allIds = ChecklistItem::whereHas('template', fn($q) => $q->active())->pluck('id');
        $sync = [];
        foreach ($allIds as $id) {
            $sync[$id] = ['is_checked' => in_array($id, $checkedIds), 'note' => null];
        }
        if ($allIds->count() > 0) {
            $report->checklistItems()->sync($sync);
        }

        return redirect()->route('admin.shifts.reports.index')->with('success', 'Schichtbericht gespeichert.');
    }

    public function edit(ShiftReport $report)
    {
        $report->load('shift.employee', 'shift.shiftArea', 'checklistItems');
        $templates = ChecklistTemplate::active()->with('items')->get();
        return view('admin.shifts.reports.form', [
            'shift'     => $report->shift,
            'report'    => $report,
            'templates' => $templates,
        ]);
    }

    public function update(Request $request, ShiftReport $report)
    {
        $data = $request->validate([
            'summary'         => 'nullable|string|max:2000',
            'customer_count'  => 'nullable|integer|min:0',
            'cash_difference' => 'nullable|numeric',
            'incident_level'  => 'required|in:none,minor,major',
            'incident_notes'  => 'nullable|string|max:1000',
            'checklist'       => 'nullable|array',
        ]);

        $report->update([
            'summary'         => $data['summary'] ?? null,
            'customer_count'  => $data['customer_count'] ?? null,
            'cash_difference' => $data['cash_difference'] ?? null,
            'incident_level'  => $data['incident_level'],
            'incident_notes'  => $data['incident_notes'] ?? null,
        ]);

        $checkedIds = array_keys($data['checklist'] ?? []);
        $allIds = ChecklistItem::whereHas('template', fn($q) => $q->active())->pluck('id');
        $sync = [];
        foreach ($allIds as $id) {
            $sync[$id] = ['is_checked' => in_array($id, $checkedIds), 'note' => null];
        }
        if ($allIds->count() > 0) {
            $report->checklistItems()->sync($sync);
        }

        return redirect()->route('admin.shifts.reports.index')->with('success', 'Schichtbericht aktualisiert.');
    }

    public function submit(ShiftReport $report)
    {
        $report->update(['is_submitted' => true, 'submitted_at' => now()]);
        return back()->with('success', 'Schichtbericht abgeschlossen.');
    }
}
