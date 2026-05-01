<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RecurringTaskCompletion;
use App\Models\RecurringTaskSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminRecurringTasksController extends Controller
{
    public function index()
    {
        $today = now()->startOfDay();

        $overdue = DB::table('ninox_77_regelmaessige_aufgaben')
            ->whereNotNull('naechste_faelligkeit')
            ->where('naechste_faelligkeit', '!=', '')
            ->where('naechste_faelligkeit', '<', $today->toDateString())
            ->orderBy('naechste_faelligkeit')
            ->orderByRaw('COALESCE(prioritaet, 0) DESC')
            ->get();

        // Enrich overdue tasks with latest completion's next_due_date
        $overdueIds = $overdue->pluck('ninox_id')->toArray();
        $latestCompletions = RecurringTaskCompletion::whereIn('ninox_task_id', $overdueIds)
            ->orderBy('completed_at', 'desc')
            ->get()
            ->groupBy('ninox_task_id')
            ->map(fn($g) => $g->first());

        $overdue = $overdue->map(function ($t) use ($latestCompletions, $today) {
            $latest  = $latestCompletions->get($t->ninox_id);
            $rawDue  = $latest?->next_due_date
                ?? (($t->naechste_faelligkeit && $t->naechste_faelligkeit !== '') ? $t->naechste_faelligkeit : null);
            $t->effective_due = $rawDue ? \Carbon\Carbon::parse($rawDue) : null;
            // Remove from overdue list if next_due is in the future
            $t->still_overdue = !$t->effective_due || $t->effective_due->lt($today);
            return $t;
        })->filter(fn($t) => $t->still_overdue)->values();

        $byZustaendigkeit = $overdue->groupBy('zustaendigkeit');

        // Recent completions log (last 50)
        $recentCompletions = RecurringTaskCompletion::with('user:id,first_name,last_name')
            ->orderBy('completed_at', 'desc')
            ->limit(50)
            ->get();

        $employees = User::where('active', true)
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_MITARBEITER])
            ->orderBy('first_name')
            ->get();

        $zustaendigkeitValues = DB::table('ninox_77_regelmaessige_aufgaben')
            ->select('zustaendigkeit')
            ->distinct()
            ->whereNotNull('zustaendigkeit')
            ->orderBy('zustaendigkeit')
            ->pluck('zustaendigkeit');

        // All tasks with a zustaendigkeit for settings panel
        $allTasks = DB::table('ninox_77_regelmaessige_aufgaben')
            ->whereNotNull('zustaendigkeit')
            ->where('zustaendigkeit', '!=', '')
            ->whereNotIn('ab_wann_wiederholen', ['nie (einmalig)'])
            ->orderBy('zustaendigkeit')
            ->orderBy('name')
            ->get(['ninox_id', 'name', 'zustaendigkeit', 'ab_wann_wiederholen',
                   'alle_x_tage', 'alle_x_wochen', 'alle_x_monate', 'alle_x_quartale', 'alle_x_jahre']);

        $taskSettings = RecurringTaskSetting::whereIn('ninox_task_id', $allTasks->pluck('ninox_id'))
            ->pluck('recurrence_basis', 'ninox_task_id');

        return view('admin.recurring-tasks.index', compact(
            'overdue', 'byZustaendigkeit', 'employees', 'zustaendigkeitValues',
            'recentCompletions', 'allTasks', 'taskSettings'
        ));
    }

    public function updateTaskSetting(Request $request)
    {
        $request->validate([
            'ninox_task_id'    => 'required|integer',
            'recurrence_basis' => 'required|in:auto,from_completion,fixed_schedule',
        ]);

        RecurringTaskSetting::updateOrCreate(
            ['ninox_task_id' => $request->ninox_task_id],
            ['recurrence_basis' => $request->recurrence_basis]
        );

        return back()->with('success', 'Einstellung gespeichert.');
    }

    public function updateUser(Request $request, User $user)
    {
        $request->validate(['zustaendigkeit' => 'nullable|array']);
        $request->validate(['zustaendigkeit.*' => 'string|max:100']);

        $values = array_filter((array) $request->input('zustaendigkeit', []));
        $user->update(['zustaendigkeit' => empty($values) ? null : array_values($values)]);

        return back()->with('success', 'Zuständigkeit gespeichert.');
    }
}
