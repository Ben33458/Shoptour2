<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\RecurringTaskCompletion;
use App\Models\RecurringTaskSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmployeeTaskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $zustaendigkeit = $user->zustaendigkeit ?? [];

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

        $taskIds = $rawTasks->pluck('ninox_id')->toArray();

        // Load per-task recurrence overrides
        $settings = RecurringTaskSetting::whereIn('ninox_task_id', $taskIds)
            ->pluck('recurrence_basis', 'ninox_task_id');

        // Latest completion per task
        $latestCompletions = RecurringTaskCompletion::whereIn('ninox_task_id', $taskIds)
            ->with('user:id,first_name,last_name')
            ->orderBy('completed_at', 'desc')
            ->get()
            ->groupBy('ninox_task_id')
            ->map(fn($group) => $group->first());

        // All completions for history
        $allCompletions = RecurringTaskCompletion::whereIn('ninox_task_id', $taskIds)
            ->with('user:id,first_name,last_name')
            ->orderBy('completed_at', 'desc')
            ->get()
            ->groupBy('ninox_task_id');

        $today = now()->startOfDay();

        $tasks = $rawTasks->map(function ($t) use ($today, $latestCompletions) {
            $latest = $latestCompletions->get($t->ninox_id);

            if ($latest) {
                if ($latest->next_due_date) {
                    $due = \Carbon\Carbon::parse($latest->next_due_date);
                } else {
                    // Einmalig erledigt — keine weitere Fälligkeit
                    $due = null;
                    $t->is_done_once = true;
                }
            } else {
                $rawDue = ($t->naechste_faelligkeit && $t->naechste_faelligkeit !== '')
                    ? $t->naechste_faelligkeit : null;
                $due = $rawDue ? \Carbon\Carbon::parse($rawDue) : null;
            }

            $t->due_date     = $due;
            $t->is_overdue   = $due && $due->lt($today);
            $t->is_due_today = $due && $due->eq($today);
            $t->latest_done  = $latest;
            return $t;
        })
        ->filter(function ($t) use ($today) {
            // Ausblenden: einmalig erledigt ODER erledigt und nächste Fälligkeit in der Zukunft
            if ($t->is_done_once ?? false) return false;
            if ($t->latest_done && $t->due_date && $t->due_date->gt($today)) return false;
            return true;
        })
        ->sortBy(function ($t) {
            if (!$t->due_date) return '9999-99-98';
            return $t->due_date->format('Y-m-d');
        })
        ->values();

        return view('employee.tasks.index', [
            'tasks'          => $tasks,
            'zustaendigkeit' => $zustaendigkeit,
            'allCompletions' => $allCompletions,
        ]);
    }

    public function complete(Request $request)
    {
        $request->validate([
            'ninox_task_id' => 'required|integer',
            'note'          => 'nullable|string|max:5000',
            'images.*'      => 'nullable|image|max:8192',
        ]);

        $taskId = (int) $request->ninox_task_id;
        $task   = DB::table('ninox_77_regelmaessige_aufgaben')->where('ninox_id', $taskId)->first();
        abort_if(!$task, 404);

        // Upload images
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store("task-completions/{$taskId}", 'public');
                $imagePaths[] = $path;
            }
        }

        $completedAt   = now();
        $basisOverride = RecurringTaskSetting::find($taskId)?->recurrence_basis;
        $basisOverride = ($basisOverride === 'auto' || $basisOverride === null) ? null : $basisOverride;
        $nextDue       = RecurringTaskCompletion::calcNextDue($task, $completedAt, $basisOverride);

        RecurringTaskCompletion::create([
            'ninox_task_id' => $taskId,
            'user_id'       => $request->user()->id,
            'note'          => $request->note,
            'images'        => empty($imagePaths) ? null : $imagePaths,
            'next_due_date' => $nextDue?->toDateString(),
            'completed_at'  => $completedAt,
        ]);

        $msg = 'Aufgabe erledigt.';
        if ($nextDue) {
            $msg .= ' Nächste Fälligkeit: ' . $nextDue->format('d.m.Y') . '.';
        }

        return back()->with('success', $msg);
    }
}
