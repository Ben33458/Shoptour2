<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeTask;
use App\Models\Employee\EmployeeTaskComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminEmployeeTaskController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeTask::with(['assignee', 'subtasks'])
            ->whereNull('parent_task_id'); // only top-level tasks in list

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('employee_id')) {
            $query->where('assigned_to', $request->integer('employee_id'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $tasks     = $query->orderByRaw("FIELD(status,'open','in_progress','done')")->orderBy('due_date')->paginate(30)->withQueryString();
        $employees = Employee::where('is_active', true)->orderBy('last_name')->get();

        return view('admin.employee-tasks.index', compact('tasks', 'employees'));
    }

    public function create()
    {
        $employees  = Employee::where('is_active', true)->orderBy('last_name')->get();
        $parentTasks = EmployeeTask::whereNull('parent_task_id')->orderBy('title')->get();

        return view('admin.employee-tasks.create', compact('employees', 'parentTasks'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'             => 'required|string|max:255',
            'description'       => 'nullable|string|max:500',
            'body'              => 'nullable|string',
            'assigned_to'       => 'nullable|exists:employees,id',
            'priority'          => 'required|in:low,medium,high,urgent',
            'due_date'          => 'nullable|date',
            'parent_task_id'    => 'nullable|exists:employee_tasks,id',
            'depends_on_task_id'=> 'nullable|exists:employee_tasks,id',
            'images.*'          => 'nullable|image|max:5120',
        ]);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $images[] = $file->store('task-images', 'public');
            }
        }

        EmployeeTask::create([
            'title'              => $data['title'],
            'description'        => $data['description'] ?? null,
            'body'               => $data['body'] ?? null,
            'assigned_to'        => $data['assigned_to'] ?? null,
            'assigned_by'        => auth()->id(),
            'priority'           => $data['priority'],
            'due_date'           => $data['due_date'] ?? null,
            'parent_task_id'     => $data['parent_task_id'] ?? null,
            'depends_on_task_id' => $data['depends_on_task_id'] ?? null,
            'status'             => 'open',
            'images'             => $images ?: null,
        ]);

        return redirect()->route('admin.emp-tasks.index')->with('success', 'Aufgabe erstellt.');
    }

    public function show(EmployeeTask $task)
    {
        $task->load(['assignee', 'subtasks.assignee', 'dependsOn', 'comments', 'parent']);
        $employees  = Employee::where('is_active', true)->orderBy('last_name')->get();
        $parentTasks = EmployeeTask::whereNull('parent_task_id')->where('id', '!=', $task->id)->orderBy('title')->get();

        return view('admin.employee-tasks.show', compact('task', 'employees', 'parentTasks'));
    }

    public function edit(EmployeeTask $task)
    {
        $employees  = Employee::where('is_active', true)->orderBy('last_name')->get();
        $parentTasks = EmployeeTask::whereNull('parent_task_id')->where('id', '!=', $task->id)->orderBy('title')->get();

        return view('admin.employee-tasks.edit', compact('task', 'employees', 'parentTasks'));
    }

    public function update(Request $request, EmployeeTask $task)
    {
        $data = $request->validate([
            'title'             => 'required|string|max:255',
            'description'       => 'nullable|string|max:500',
            'body'              => 'nullable|string',
            'assigned_to'       => 'nullable|exists:employees,id',
            'priority'          => 'required|in:low,medium,high,urgent',
            'status'            => 'required|in:open,in_progress,done',
            'due_date'          => 'nullable|date',
            'parent_task_id'    => 'nullable|exists:employee_tasks,id',
            'depends_on_task_id'=> 'nullable|exists:employee_tasks,id',
            'images.*'          => 'nullable|image|max:5120',
        ]);

        $images = $task->images ?? [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $images[] = $file->store('task-images', 'public');
            }
        }

        $task->update([
            'title'              => $data['title'],
            'description'        => $data['description'] ?? null,
            'body'               => $data['body'] ?? null,
            'assigned_to'        => $data['assigned_to'] ?? null,
            'priority'           => $data['priority'],
            'status'             => $data['status'],
            'due_date'           => $data['due_date'] ?? null,
            'parent_task_id'     => $data['parent_task_id'] ?? null,
            'depends_on_task_id' => $data['depends_on_task_id'] ?? null,
            'images'             => $images ?: null,
        ]);

        return redirect()->route('admin.emp-tasks.show', $task)->with('success', 'Aufgabe aktualisiert.');
    }

    public function destroy(EmployeeTask $task)
    {
        $task->delete();
        return redirect()->route('admin.emp-tasks.index')->with('success', 'Aufgabe gelöscht.');
    }

    public function addComment(Request $request, EmployeeTask $task)
    {
        $data = $request->validate([
            'body'        => 'required|string|max:5000',
            'is_liveblog' => 'nullable|boolean',
            'images.*'    => 'nullable|image|max:5120',
        ]);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $images[] = $file->store('task-images', 'public');
            }
        }

        EmployeeTaskComment::create([
            'task_id'     => $task->id,
            'author_type' => 'user',
            'author_id'   => auth()->id(),
            'body'        => $data['body'],
            'images'      => $images ?: null,
            'is_liveblog' => $request->boolean('is_liveblog'),
        ]);

        return redirect()->route('admin.emp-tasks.show', $task)->with('success', 'Kommentar hinzugefügt.');
    }

    public function completeTask(EmployeeTask $task)
    {
        $task->update([
            'status'       => 'done',
            'completed_at' => now(),
            'completed_by' => null, // admin-completed, no employee
        ]);

        return redirect()->route('admin.emp-tasks.show', $task)->with('success', 'Aufgabe als erledigt markiert.');
    }
}
