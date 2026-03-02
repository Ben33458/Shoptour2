<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\DeferredTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin UI for the deferred task queue.
 *
 * GET  /admin/tasks           → index()  paginated task list
 * POST /admin/tasks/{task}/retry → retry()  reset a failed task to pending
 */
class AdminTasksController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');

        $query = DeferredTask::query()->latest();

        if ($statusFilter && in_array($statusFilter, [
            DeferredTask::STATUS_PENDING,
            DeferredTask::STATUS_RUNNING,
            DeferredTask::STATUS_DONE,
            DeferredTask::STATUS_FAILED,
        ], true)) {
            $query->where('status', $statusFilter);
        }

        $tasks = $query->paginate(20)->withQueryString();

        $counts = [
            'pending' => DeferredTask::where('status', DeferredTask::STATUS_PENDING)->count(),
            'running' => DeferredTask::where('status', DeferredTask::STATUS_RUNNING)->count(),
            'done'    => DeferredTask::where('status', DeferredTask::STATUS_DONE)->count(),
            'failed'  => DeferredTask::where('status', DeferredTask::STATUS_FAILED)->count(),
        ];

        return view('admin.tasks.index', compact('tasks', 'counts', 'statusFilter'));
    }

    public function retry(DeferredTask $task): RedirectResponse
    {
        $task->update([
            'status'     => DeferredTask::STATUS_PENDING,
            'last_error' => null,
            'attempts'   => max(0, $task->attempts - 1),
        ]);

        return redirect()->route('admin.tasks.index')
            ->with('success', "Aufgabe #{$task->id} ({$task->type}) wird erneut verarbeitet.");
    }
}
