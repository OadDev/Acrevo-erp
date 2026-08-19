<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaskSchedule;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class TaskScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->get('user_id');
        $role = $request->get('role');
        $frequency = in_array($request->get('frequency'), ['daily', 'weekly', 'monthly', 'yearly'], true) ? $request->get('frequency') : null;

        $schedules = TaskSchedule::with(['assignedToUser', 'verifier'])
            ->when($userId, fn ($q) => $q->where('assigned_to_user_id', $userId))
            ->when($role, fn ($q) => $q->where('assignee_role', $role))
            ->when($frequency, fn ($q) => $q->where('frequency', $frequency))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.task-schedules.index', compact('schedules', 'userId', 'role', 'frequency') + $this->formData());
    }

    public function create(Request $request): View
    {
        $prefillUserId = $request->get('user_id');
        $prefillRole = $request->get('role');
        $prefillFrequency = in_array($request->get('frequency'), ['daily', 'weekly', 'monthly', 'yearly'], true) ? $request->get('frequency') : null;

        return view('admin.task-schedules.create', compact('prefillUserId', 'prefillRole', 'prefillFrequency') + $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        TaskSchedule::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('admin.task-schedules.index')->with('success', 'Calendar task created.');
    }

    public function edit(TaskSchedule $taskSchedule): View
    {
        return view('admin.task-schedules.edit', ['schedule' => $taskSchedule] + $this->formData());
    }

    public function update(Request $request, TaskSchedule $taskSchedule): RedirectResponse
    {
        $taskSchedule->update($this->validated($request));

        return redirect()->route('admin.task-schedules.index')->with('success', 'Calendar task updated.');
    }

    public function destroy(TaskSchedule $taskSchedule): RedirectResponse
    {
        $taskSchedule->delete();

        return back()->with('success', 'Calendar task removed.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_type' => ['required', 'in:user,role'],
            'assigned_to_user_id' => ['nullable', 'required_if:assignee_type,user', 'exists:users,id'],
            'assignee_role' => ['nullable', 'required_if:assignee_type,role', 'string'],
            'frequency' => ['required', 'in:daily,weekly,monthly,yearly'],
            'day_of_week' => ['nullable', 'required_if:frequency,weekly', 'integer', 'between:0,6'],
            'day_of_month' => ['nullable', 'required_if:frequency,monthly,yearly', 'integer', 'between:1,31'],
            'month_of_year' => ['nullable', 'required_if:frequency,yearly', 'integer', 'between:1,12'],
            'verifier_user_id' => ['required', 'exists:users,id'],
        ]);

        $data['assigned_to_user_id'] = $data['assignee_type'] === 'user' ? $data['assigned_to_user_id'] : null;
        $data['assignee_role'] = $data['assignee_type'] === 'role' ? $data['assignee_role'] : null;
        $data['is_active'] = $request->boolean('is_active', true);
        unset($data['assignee_type']);

        return $data;
    }

    private function formData(): array
    {
        $users = User::where('is_active', true)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Client'))
            ->orderBy('name')
            ->get();
        $roles = Role::where('name', '!=', 'Client')->orderBy('name')->pluck('name');

        return compact('users', 'roles');
    }
}
