<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee\ChecklistItem;
use App\Models\Employee\ChecklistTemplate;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeFeedback;
use App\Models\Employee\EmployeeNotification;
use App\Models\Employee\EmployeeTask;
use App\Models\Employee\EmployeeTaskComment;
use App\Models\Employee\Shift;
use App\Models\Employee\ShiftReport;
use App\Models\Employee\TimeEntry;
use App\Models\RecurringTaskCompletion;
use App\Models\RecurringTaskSetting;
use App\Services\Employee\TimeTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MeinController extends Controller
{
    public function __construct(private readonly TimeTrackingService $tracking) {}

    private function employee(): Employee
    {
        return Employee::findOrFail(session('employee_id'));
    }

    public function dashboard()
    {
        $employee = $this->employee();

        $activeEntry = $this->tracking->getActiveEntry($employee);

        $netMinutesToday = TimeEntry::where('employee_id', $employee->id)
            ->whereDate('clocked_in_at', today())
            ->whereNotNull('clocked_out_at')
            ->sum('net_minutes');

        if ($activeEntry) {
            $netMinutesToday += $activeEntry->clocked_in_at->diffInMinutes(now());
        }

        $zustaendigkeit = $employee->zustaendigkeit ?? [];
        $openTasks = 0;
        if (!empty($zustaendigkeit)) {
            $taskIds = DB::table('ninox_77_regelmaessige_aufgaben')
                ->whereIn('zustaendigkeit', $zustaendigkeit)
                ->pluck('ninox_id')
                ->toArray();

            $latestByTask = RecurringTaskCompletion::whereIn('ninox_task_id', $taskIds)
                ->orderBy('completed_at', 'desc')
                ->get()
                ->groupBy('ninox_task_id')
                ->map(fn($g) => $g->first());

            $today = now()->startOfDay();
            foreach ($taskIds as $id) {
                $latest = $latestByTask->get($id);
                if ($latest) {
                    if (!$latest->next_due_date) continue; // done once, no recurrence
                    $due = \Carbon\Carbon::parse($latest->next_due_date);
                    if ($due->gt($today)) continue; // not yet due
                }
                $openTasks++;
            }
        }

        // Active shift today
        $todayShift = Shift::where('employee_id', $employee->id)
            ->whereDate('planned_start', today())
            ->with('report')
            ->orderByDesc('planned_start')
            ->first();

        // Daily report (decoupled from shift)
        $shiftReport = ShiftReport::where('employee_id', $employee->id)
            ->whereDate('report_date', today())
            ->with('checklistItems')
            ->first();

        $reportTemplates = \App\Models\Employee\ChecklistTemplate::active()->with('items')->get();

        // Determine clock status
        if (!$activeEntry) {
            $clockStatus = 'clocked_out';
        } elseif ($activeEntry->breakSegments->whereNull('ended_at')->isNotEmpty()) {
            $clockStatus = 'on_break';
        } else {
            $clockStatus = 'active';
        }

        // Team overview (only for managers and teamleaders)
        $teamEntries = null;
        if (in_array($employee->role, ['admin', 'manager', 'teamleader'])) {
            $teamEntries = TimeEntry::with('employee', 'employee.shifts')
                ->whereNull('clocked_out_at')
                ->whereDate('clocked_in_at', today())
                ->get();
        }

        $notifications = EmployeeNotification::where('employee_id', $employee->id)
            ->unread()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('mein.dashboard', compact(
            'employee', 'activeEntry', 'netMinutesToday',
            'openTasks', 'todayShift', 'shiftReport', 'reportTemplates',
            'clockStatus', 'teamEntries', 'notifications'
        ));
    }

    public function schicht()
    {
        return redirect()->route('mein.dashboard');
    }

    public function schichtSave(Request $request)
    {
        $employee = $this->employee();

        $data = $request->validate([
            'summary'         => 'nullable|string|max:2000',
            'customer_count'  => 'nullable|integer|min:0',
            'cash_difference' => 'nullable|numeric',
            'incident_level'  => 'required|in:none,minor,major',
            'incident_notes'  => 'nullable|string|max:1000',
            'checklist'       => 'nullable|array',
            'submit'          => 'nullable|boolean',
        ]);

        // Existing report may still be linked to a shift
        $existing = ShiftReport::where('employee_id', $employee->id)
            ->whereDate('report_date', today())
            ->first();

        if ($existing?->is_submitted) {
            return back()->with('error', 'Der Schichtbericht wurde bereits abgeschlossen.');
        }

        $reportData = [
            'employee_id'     => $employee->id,
            'report_date'     => today()->toDateString(),
            'shift_id'        => $existing?->shift_id ?? $this->todayShiftId($employee),
            'summary'         => $data['summary'] ?? null,
            'customer_count'  => $data['customer_count'] ?? null,
            'cash_difference' => $data['cash_difference'] ?? null,
            'incident_level'  => $data['incident_level'],
            'incident_notes'  => $data['incident_notes'] ?? null,
        ];

        if ($data['submit'] ?? false) {
            $reportData['is_submitted'] = true;
            $reportData['submitted_at'] = now();
        }

        $report = ShiftReport::updateOrCreate(
            ['employee_id' => $employee->id, 'report_date' => today()->toDateString()],
            $reportData,
        );

        // Sync checklist
        $checkedIds = array_keys($data['checklist'] ?? []);
        $allIds = ChecklistItem::whereHas('template', fn($q) => $q->active())->pluck('id');
        $sync = [];
        foreach ($allIds as $id) {
            $sync[$id] = ['is_checked' => in_array($id, $checkedIds), 'note' => null];
        }
        if ($allIds->count() > 0) {
            $report->checklistItems()->sync($sync);
        }

        $msg = ($data['submit'] ?? false) ? 'Schichtbericht abgeschlossen.' : 'Schichtbericht gespeichert.';
        return redirect()->route('mein.dashboard')->with('success', $msg);
    }

    private function todayShiftId(Employee $employee): ?int
    {
        return Shift::where('employee_id', $employee->id)
            ->whereDate('planned_start', today())
            ->orderByDesc('planned_start')
            ->value('id');
    }

    public function aufgaben()
    {
        $employee = $this->employee();
        $zustaendigkeit = $employee->zustaendigkeit ?? [];

        $query = DB::table('ninox_77_regelmaessige_aufgaben')
            ->orderByRaw("CASE WHEN naechste_faelligkeit IS NULL OR naechste_faelligkeit = '' THEN 1 ELSE 0 END")
            ->orderBy('naechste_faelligkeit')
            ->orderByRaw('COALESCE(prioritaet, 0) DESC');

        if (empty($zustaendigkeit)) {
            $query->whereRaw('1=0');
        } else {
            $query->whereIn('zustaendigkeit', $zustaendigkeit);
        }

        $rawTasks = $query->get();
        $taskIds  = $rawTasks->pluck('ninox_id')->toArray();

        $latestCompletions = RecurringTaskCompletion::whereIn('ninox_task_id', $taskIds)
            ->with('user:id,first_name,last_name', 'employeeModel:id,first_name,last_name')
            ->orderBy('completed_at', 'desc')
            ->get()
            ->groupBy('ninox_task_id')
            ->map(fn($g) => $g->first());

        $allCompletions = RecurringTaskCompletion::whereIn('ninox_task_id', $taskIds)
            ->with('user:id,first_name,last_name', 'employeeModel:id,first_name,last_name')
            ->orderBy('completed_at', 'desc')
            ->get()
            ->groupBy('ninox_task_id');

        $today = now()->startOfDay();

        $tasks = $rawTasks->map(function ($t) use ($today, $latestCompletions) {
            $latest = $latestCompletions->get($t->ninox_id);
            if ($latest) {
                $due = $latest->next_due_date
                    ? \Carbon\Carbon::parse($latest->next_due_date)
                    : null;
                if (!$due) $t->is_done_once = true;
            } else {
                $raw = ($t->naechste_faelligkeit && $t->naechste_faelligkeit !== '') ? $t->naechste_faelligkeit : null;
                $due = $raw ? \Carbon\Carbon::parse($raw) : null;
            }
            $t->due_date     = $due;
            $t->is_overdue   = $due && $due->lt($today);
            $t->is_due_today = $due && $due->eq($today);
            $t->latest_done  = $latest;
            return $t;
        })
        ->filter(function ($t) use ($today) {
            if ($t->is_done_once ?? false) return false;
            if ($t->latest_done && $t->due_date && $t->due_date->gt($today)) return false;
            return true;
        })
        ->sortBy(fn($t) => $t->due_date ? $t->due_date->format('Y-m-d') : '9999-99-98')
        ->values();

        // Also fetch EmployeeTask model tasks (admin-created)
        $statusFilter = request()->get('status', 'open');
        $employeeTasks = EmployeeTask::where('assigned_to', $employee->id)
            ->whereNull('parent_task_id')
            ->when($statusFilter === 'open', fn($q) => $q->whereIn('status', ['open', 'in_progress']))
            ->when($statusFilter === 'done', fn($q) => $q->where('status', 'done'))
            ->with(['subtasks', 'dependsOn'])
            ->orderBy('due_date')
            ->get();

        return view('mein.aufgaben', compact('employee', 'tasks', 'zustaendigkeit', 'allCompletions', 'employeeTasks', 'statusFilter'));
    }

    public function aufgabeStore(Request $request)
    {
        $employee = $this->employee();

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'due_date'    => 'nullable|date|after_or_equal:today',
            'priority'    => 'required|in:low,medium,high,urgent',
        ]);

        EmployeeTask::create([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date'    => $data['due_date'] ?? null,
            'priority'    => $data['priority'],
            'assigned_to' => $employee->id,
            'assigned_by' => null,
            'status'      => 'open',
        ]);

        return back()->with('success', 'Aufgabe erstellt.');
    }

    private function canAccessTask(EmployeeTask $task, Employee $employee): bool
    {
        if ($task->assigned_to === $employee->id) return true;
        // Also allow access to subtasks whose parent is assigned to this employee
        if ($task->parent_task_id) {
            $parent = EmployeeTask::find($task->parent_task_id);
            if ($parent && $parent->assigned_to === $employee->id) return true;
        }
        return false;
    }

    public function aufgabeDetail(EmployeeTask $task)
    {
        $employee = $this->employee();
        if (!$this->canAccessTask($task, $employee)) {
            abort(403);
        }
        $task->load(['subtasks.assignee', 'dependsOn', 'comments', 'parent']);

        return view('mein.aufgabe-detail', compact('employee', 'task'));
    }

    public function aufgabeStart(EmployeeTask $task)
    {
        $employee = $this->employee();
        if (!$this->canAccessTask($task, $employee)) {
            abort(403);
        }

        if ($task->status === 'done') {
            return back()->with('error', 'Aufgabe ist bereits erledigt.');
        }

        $task->update([
            'status'           => 'in_progress',
            'timer_started_at' => now(),
        ]);

        return back()->with('success', 'Aufgabe gestartet. Timer läuft.');
    }

    public function aufgabeComplete(Request $request, EmployeeTask $task)
    {
        $employee = $this->employee();
        if (!$this->canAccessTask($task, $employee)) {
            abort(403);
        }

        if ($task->status === 'done') {
            return back()->with('error', 'Aufgabe ist bereits erledigt.');
        }

        $data = $request->validate([
            'time_minutes' => 'nullable|integer|min:0|max:9999',
            'time_seconds' => 'nullable|integer|min:0|max:59',
            'comment'      => 'nullable|string|max:2000',
            'images.*'     => 'nullable|image|max:10240',
        ]);

        $minutes = (int) ($data['time_minutes'] ?? 0);
        $seconds = (int) ($data['time_seconds'] ?? 0);
        $totalSeconds = $minutes * 60 + $seconds;

        $task->update([
            'status'             => 'done',
            'completed_at'       => now(),
            'completed_by'       => $employee->id,
            'time_spent_seconds' => $totalSeconds > 0 ? $totalSeconds : null,
        ]);

        if (!empty($data['comment']) || $request->hasFile('images')) {
            $images = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    $images[] = $file->store('task-images', 'public');
                }
            }
            $timeNote = $totalSeconds > 0
                ? " (Bearbeitungszeit: {$minutes} Min" . ($seconds > 0 ? " {$seconds} Sek" : '') . ")"
                : '';
            EmployeeTaskComment::create([
                'task_id'     => $task->id,
                'author_type' => 'employee',
                'author_id'   => $employee->id,
                'body'        => ($data['comment'] ?? '') . $timeNote,
                'images'      => $images ?: null,
                'is_liveblog' => false,
            ]);
        }

        $msg = 'Aufgabe erledigt.' . ($totalSeconds > 0 ? " Bearbeitungszeit: {$minutes} Min" . ($seconds > 0 ? " {$seconds} Sek." : '.') : '');
        return redirect()->route('mein.aufgaben')->with('success', $msg);
    }

    public function aufgabeReopen(EmployeeTask $task)
    {
        $employee = $this->employee();
        if (!$this->canAccessTask($task, $employee)) {
            abort(403);
        }

        $task->update([
            'status'             => 'open',
            'completed_at'       => null,
            'completed_by'       => null,
            'timer_started_at'   => null,
            'time_spent_seconds' => null,
        ]);

        return back()->with('success', 'Aufgabe wieder geöffnet.');
    }

    public function aufgabeComment(Request $request, EmployeeTask $task)
    {
        $employee = $this->employee();
        if (!$this->canAccessTask($task, $employee)) {
            abort(403);
        }

        $data = $request->validate([
            'body'     => 'required|string|max:5000',
            'images.*' => 'nullable|image|max:10240',
        ]);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $images[] = $file->store('task-images', 'public');
            }
        }

        EmployeeTaskComment::create([
            'task_id'     => $task->id,
            'author_type' => 'employee',
            'author_id'   => $employee->id,
            'body'        => $data['body'],
            'images'      => $images ?: null,
            'is_liveblog' => false,
        ]);

        return back()->with('success', 'Update hinzugefügt.');
    }

    public function liveblog()
    {
        $employee = $this->employee();
        $posts = EmployeeTaskComment::where('is_liveblog', true)
            ->with('task')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('mein.news', compact('employee', 'posts'));
    }

    public function taskComplete(Request $request)
    {
        $employee = $this->employee();

        $request->validate(['ninox_task_id' => 'required|integer']);

        $taskId = (int) $request->ninox_task_id;
        $task   = DB::table('ninox_77_regelmaessige_aufgaben')->where('ninox_id', $taskId)->firstOrFail();

        $completedAt   = now();
        $basisOverride = RecurringTaskSetting::find($taskId)?->recurrence_basis;
        $basisOverride = ($basisOverride === 'auto' || $basisOverride === null) ? null : $basisOverride;
        $nextDue       = RecurringTaskCompletion::calcNextDue($task, $completedAt, $basisOverride);

        RecurringTaskCompletion::create([
            'ninox_task_id' => $taskId,
            'user_id'       => $employee->user_id,
            'employee_id'   => $employee->id,
            'note'          => $request->note,
            'next_due_date' => $nextDue?->toDateString(),
            'completed_at'  => $completedAt,
        ]);

        $msg = 'Aufgabe erledigt.';
        if ($nextDue) {
            $msg .= ' Nächste Fälligkeit: ' . $nextDue->locale('de')->isoFormat('D. MMMM YYYY') . '.';
        }

        return back()->with('success', $msg);
    }

    public function timeclockAction(Request $request)
    {
        $employee = $this->employee();

        $data = $request->validate([
            'action' => 'required|in:clock_in,clock_out,break_start,break_end',
        ]);

        try {
            match ($data['action']) {
                'clock_in' => $this->doClockIn($employee),
                'clock_out' => (function () use ($employee) {
                    $entry = $this->tracking->getActiveEntry($employee);
                    if (!$entry) throw new \RuntimeException('Kein aktiver Eintrag.');
                    $this->tracking->clockOut($entry);
                })(),
                'break_start' => (function () use ($employee) {
                    $entry = $this->tracking->getActiveEntry($employee);
                    if (!$entry) throw new \RuntimeException('Kein aktiver Eintrag.');
                    $this->tracking->startBreak($entry);
                })(),
                'break_end' => (function () use ($employee) {
                    $entry = $this->tracking->getActiveEntry($employee);
                    if (!$entry) throw new \RuntimeException('Kein aktiver Eintrag.');
                    $openBreak = $entry->breakSegments()->whereNull('ended_at')->first();
                    if (!$openBreak) throw new \RuntimeException('Keine laufende Pause.');
                    $this->tracking->endBreak($openBreak);
                })(),
            };
        } catch (\RuntimeException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return back()->with('success', 'Aktion erfolgreich.');
    }

    public function feedbackStore(Request $request)
    {
        $employee = $this->employee();
        $data = $request->validate([
            'category' => 'required|in:bug,improvement,other',
            'subject'  => 'required|string|max:200',
            'body'     => 'required|string|max:2000',
        ]);
        EmployeeFeedback::create([...$data, 'employee_id' => $employee->id]);
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('feedback_sent', true);
    }

    public function markNotificationRead(EmployeeNotification $notification)
    {
        $employee = $this->employee();
        if ($notification->employee_id === $employee->id) {
            $notification->update(['read_at' => now()]);
        }
        return back();
    }

    public function logout()
    {
        session()->forget('employee_id');
        return redirect('/timeclock');
    }

    private function doClockIn(Employee $employee): void
    {
        $shift = Shift::where('employee_id', $employee->id)
            ->whereDate('planned_start', today())
            ->where('status', 'planned')
            ->orderBy('planned_start')
            ->first();

        if (!$shift) {
            $shift = Shift::create([
                'employee_id'   => $employee->id,
                'planned_start' => now(),
                'planned_end'   => now()->addHours(8),
                'status'        => 'active',
                'notes'         => 'Ad-hoc Stempelung',
            ]);
        } else {
            $shift->update(['status' => 'active', 'actual_start' => now()]);
        }

        $this->tracking->clockIn($employee, $shift);
    }
}
