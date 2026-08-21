<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Services\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class EmployeeController extends Controller
{
    private function skillSetFrom(array $data): ?array
    {
        if (! filled($data['skill_set'] ?? null)) {
            return null;
        }

        return collect(explode(',', $data['skill_set']))
            ->map(fn ($skill) => trim($skill))
            ->filter()
            ->values()
            ->all();
    }

    private function attachFiles(Employee $employee, Request $request): ?string
    {
        try {
            foreach ($request->file('files', []) as $file) {
                $employee->addMedia($file)->toMediaCollection('documents');
            }
        } catch (FileIsTooBig $e) {
            return 'One of those files is too large (max 20MB).';
        }

        return null;
    }

    public function index(Request $request): View
    {
        $employees = Employee::query()
            ->with('department')
            ->when($request->get('status', 'active'), fn ($q, $status) => $status === 'all' ? $q : $q->where('status', $status))
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('employees.index', compact('employees'));
    }

    public function create(): View
    {
        $departments = Department::orderBy('name')->get();

        return view('employees.create', compact('departments'));
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $employee = Employee::create(collect($data)->except('files', 'skill_set')->all() + [
            'skill_set' => $this->skillSetFrom($data),
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        if ($error = $this->attachFiles($employee, $request)) {
            return back()->withErrors(['files' => $error]);
        }

        return redirect()->route('employees.show', $employee)->with('success', 'Worker added successfully.');
    }

    public function show(Employee $employee): View
    {
        $employee->load(['department', 'media', 'attendances' => fn ($q) => $q->latest()->limit(30), 'payrolls' => fn ($q) => $q->latest(), 'benefits', 'executiveTeamMemberships.executiveTeam']);
        $discussion = app(ConversationService::class)->discussionFor($employee, auth()->user());

        return view('employees.show', compact('employee', 'discussion'));
    }

    public function edit(Employee $employee): View
    {
        $departments = Department::orderBy('name')->get();
        $employee->load('media');

        return view('employees.edit', compact('employee', 'departments'));
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $data = $request->validated();

        $employee->update(collect($data)->except('files', 'skill_set')->all() + [
            'skill_set' => $this->skillSetFrom($data),
        ]);

        if ($error = $this->attachFiles($employee, $request)) {
            return back()->withErrors(['files' => $error]);
        }

        return redirect()->route('employees.show', $employee)->with('success', 'Worker details updated.');
    }

    public function destroyMedia(Employee $employee, Media $media): RedirectResponse
    {
        abort_unless((string) $media->model_id === (string) $employee->id, 404);

        $media->delete();

        return back()->with('success', 'File removed.');
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate(['exit_notes' => ['nullable', 'string']]);

        $employee->update($data + ['status' => 'relieved', 'relieving_date' => now()]);

        return redirect()->route('employees.index')->with('success', 'Worker marked as relieved.');
    }

    public function remove(Employee $employee): RedirectResponse
    {
        // An active worker with attendance/payroll/advance/benefit history
        // must be relieved first - that's the deliberate two-step workflow.
        // Once relieved, Remove always works: historical records (Payroll,
        // Attendance, etc.) load the employee withTrashed(), so they keep
        // showing this worker's name/details after removal instead of
        // crashing on a null relation.
        if ($employee->status !== 'relieved') {
            $hasHistory = $employee->attendances()->exists()
                || $employee->payrolls()->exists()
                || $employee->salaryAdvances()->exists()
                || $employee->benefits()->exists();

            abort_if($hasHistory, 422, 'This worker has attendance, payroll, or benefit history and can\'t be permanently removed while still active. Mark them relieved first, then remove.');
        }

        // Otherwise a still-active executive team membership would point at
        // a now-trashed employee, and the team's page crashes reading its name.
        foreach ($employee->executiveTeamMemberships as $membership) {
            $membership->update(['left_at' => now()]);
            $membership->delete();
        }

        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Worker removed.');
    }
}
