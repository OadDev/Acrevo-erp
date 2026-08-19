<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskScheduleGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class TaskController extends Controller
{
    public function __construct(private readonly TaskScheduleGenerator $generator) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->generator->generateForUser($user);

        $scope = in_array($request->get('scope'), ['mine', 'assigned', 'all'], true) ? $request->get('scope') : 'mine';
        $type = in_array($request->get('type'), ['common', 'calendar'], true) ? $request->get('type') : null;
        $status = $request->get('status');

        if ($scope === 'all') {
            abort_unless($user->can('tasks.manage'), 403);
        }

        $isAdmin = $user->hasRole('Admin');
        $filterUserId = $isAdmin ? $request->get('user_id') : null;
        $filterFrom = $isAdmin ? $request->get('from') : null;
        $filterTo = $isAdmin ? $request->get('to') : null;

        $query = $this->filteredTaskQuery($request, $user, $scope, $type, $status, $filterUserId, $filterFrom, $filterTo);

        $tasks = $query->orderByDesc('due_date')->paginate(20)->withQueryString();

        $assignableUsers = $isAdmin ? $this->assignableUsers() : collect();

        return view('tasks.index', compact('tasks', 'scope', 'type', 'status', 'isAdmin', 'assignableUsers', 'filterUserId', 'filterFrom', 'filterTo'));
    }

    public function pdf(Request $request)
    {
        $this->authorizeAdminOnly();

        $user = $request->user();

        $scope = in_array($request->get('scope'), ['mine', 'assigned', 'all'], true) ? $request->get('scope') : 'all';
        $type = in_array($request->get('type'), ['common', 'calendar'], true) ? $request->get('type') : null;
        $status = $request->get('status');
        $filterUserId = $request->get('user_id');
        $filterFrom = $request->get('from');
        $filterTo = $request->get('to');

        $query = $this->filteredTaskQuery($request, $user, $scope, $type, $status, $filterUserId, $filterFrom, $filterTo);

        $tasks = $query->orderBy('assigned_to')->orderByDesc('due_date')->get();

        $performance = $tasks->groupBy('assigned_to')->map(function ($userTasks) {
            return [
                'user' => $userTasks->first()->assignedTo,
                'total' => $userTasks->count(),
                'verified' => $userTasks->where('status', 'verified')->count(),
                'submitted' => $userTasks->where('status', 'submitted')->count(),
                'pending' => $userTasks->where('status', 'pending')->count(),
                'retasked' => $userTasks->where('status', 'retasked')->count(),
                'overdue' => $userTasks->filter(fn ($t) => $t->isOverdue())->count(),
            ];
        })->values();

        $filterUser = $filterUserId ? User::find($filterUserId) : null;

        $pdf = Pdf::loadView('tasks.pdf', compact('tasks', 'performance', 'filterUser', 'filterFrom', 'filterTo', 'status'));

        return $pdf->download('task-performance-'.now()->format('Y-m-d').'.pdf');
    }

    private function filteredTaskQuery(Request $request, User $user, string $scope, ?string $type, ?string $status, ?string $filterUserId, ?string $filterFrom, ?string $filterTo): Builder
    {
        $query = Task::query()->with(['assignedBy', 'assignedTo', 'verifier', 'schedule']);

        if ($scope === 'assigned') {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_by', $user->id)->orWhere('verifier_id', $user->id);
            });
        } elseif ($scope === 'mine') {
            $query->where('assigned_to', $user->id);
        }

        if ($type === 'common') {
            $query->whereNull('task_schedule_id');
        } elseif ($type === 'calendar') {
            $query->whereNotNull('task_schedule_id');
        }

        if ($status === 'overdue') {
            $query->where('status', 'pending')->whereDate('due_date', '<', now()->toDateString());
        } elseif (in_array($status, ['pending', 'submitted', 'verified', 'retasked'], true)) {
            $query->where('status', $status);
        }

        if ($filterUserId) {
            $query->where('assigned_to', $filterUserId);
        }

        if ($filterFrom) {
            $query->whereDate('due_date', '>=', $filterFrom);
        }

        if ($filterTo) {
            $query->whereDate('due_date', '<=', $filterTo);
        }

        return $query;
    }

    public function create(): View
    {
        $users = $this->assignableUsers();

        return view('tasks.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['required', 'date'],
        ]);

        $task = Task::create($data + [
            'assigned_by' => $request->user()->id,
            'verifier_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        return redirect()->route('tasks.show', $task)->with('success', 'Task assigned.');
    }

    public function show(Request $request, Task $task): View
    {
        $user = $request->user();
        abort_unless(
            in_array($user->id, [$task->assigned_to, $task->assigned_by, $task->verifier_id], true) || $user->can('tasks.manage'),
            403
        );

        $task->load(['assignedBy', 'assignedTo', 'verifier', 'verifiedBy', 'schedule', 'media', 'parentTask', 'retasks.assignedTo']);

        return view('tasks.show', compact('task'));
    }

    public function complete(Request $request, Task $task): RedirectResponse
    {
        abort_unless($task->assigned_to === $request->user()->id || $request->user()->hasRole('Admin'), 403);
        abort_if(in_array($task->status, ['submitted', 'verified'], true), 422, 'This task has already been submitted.');

        $data = $request->validate([
            'completion_notes' => ['required', 'string'],
            'proof' => ['nullable', 'array'],
            'proof.*' => ['file', 'max:20480'],
        ]);

        $task->update([
            'status' => 'submitted',
            'completion_notes' => $data['completion_notes'],
            'completed_at' => now(),
        ]);

        foreach ($request->file('proof', []) as $file) {
            try {
                $task->addMedia($file)->toMediaCollection('proof');
            } catch (FileIsTooBig $e) {
                return back()->withErrors(['proof' => 'One of the files is too large (max 20MB).']);
            }
        }

        return back()->with('success', 'Task marked completed and sent for verification.');
    }

    public function reportDelay(Request $request, Task $task): RedirectResponse
    {
        abort_unless($task->assigned_to === $request->user()->id || $request->user()->hasRole('Admin'), 403);
        abort_if(in_array($task->status, ['submitted', 'verified'], true), 422, 'This task has already been submitted.');

        $data = $request->validate([
            'delay_reason' => ['required', 'string'],
        ]);

        $task->update([
            'delay_reason' => $data['delay_reason'],
            'delay_reported_at' => now(),
        ]);

        return back()->with('success', 'Delay reason recorded.');
    }

    public function verify(Request $request, Task $task): RedirectResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->id, [$task->verifier_id, $task->assigned_by], true) || $user->hasRole('Admin'), 403);
        abort_unless($task->status === 'submitted', 422, 'This task has not been submitted for verification yet.');

        $task->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $user->id,
        ]);

        return back()->with('success', 'Task verified as completed.');
    }

    public function edit(Request $request, Task $task): View
    {
        $this->authorizeTaskManager($task, $request->user());
        abort_unless($task->task_schedule_id === null, 404, 'Calendar task instances follow their schedule - edit the calendar task itself instead.');

        $users = $this->assignableUsers();

        return view('tasks.edit', compact('task', 'users'));
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskManager($task, $request->user());
        abort_unless($task->task_schedule_id === null, 404);

        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['required', 'date'],
        ]);

        $task->update($data);

        return redirect()->route('tasks.show', $task)->with('success', 'Task updated.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskManager($task, $request->user());

        $task->delete();

        return redirect()->route('tasks.index')->with('success', 'Task removed.');
    }

    private function authorizeTaskManager(Task $task, User $user): void
    {
        abort_unless(
            in_array($user->id, [$task->assigned_by, $task->verifier_id], true) || $user->hasRole('Admin'),
            403
        );
    }

    public function retask(Request $request, Task $task): RedirectResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->id, [$task->verifier_id, $task->assigned_by], true) || $user->hasRole('Admin'), 403);
        abort_unless($task->status === 'submitted', 422, 'Only a submitted task can be retasked.');

        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'due_date' => ['required', 'date'],
            'retask_note' => ['required', 'string'],
        ]);

        $task->update(['status' => 'retasked']);

        $newTask = Task::create([
            'parent_task_id' => $task->id,
            'assigned_by' => $user->id,
            'assigned_to' => $data['assigned_to'],
            'verifier_id' => $user->id,
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $data['due_date'],
            'status' => 'pending',
            'retask_note' => $data['retask_note'],
        ]);

        return redirect()->route('tasks.show', $newTask)->with('success', 'Task retasked.');
    }

    private function assignableUsers()
    {
        return User::where('is_active', true)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Client'))
            ->orderBy('name')
            ->get();
    }
}
