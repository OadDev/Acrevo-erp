<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CandidateController extends Controller
{
    private const FILE_RULES = ['file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,doc,docx'];

    public function index(Request $request): View
    {
        $candidates = Candidate::with(['department', 'employee'])
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('position_applied', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('candidates.index', compact('candidates'));
    }

    public function create(): View
    {
        $departments = Department::orderBy('name')->get();

        return view('candidates.create', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $candidate = Candidate::create(collect($data)->except('files')->all() + [
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        if ($error = $this->attachFiles($candidate, $request)) {
            return back()->withErrors(['files' => $error]);
        }

        return redirect()->route('candidates.show', $candidate)->with('success', 'Candidate recorded.');
    }

    public function show(Candidate $candidate): View
    {
        $candidate->load(['department', 'employee', 'media', 'createdBy']);

        return view('candidates.show', compact('candidate'));
    }

    public function edit(Candidate $candidate): View
    {
        $departments = Department::orderBy('name')->get();
        $candidate->load('media');

        return view('candidates.edit', compact('candidate', 'departments'));
    }

    public function update(Request $request, Candidate $candidate): RedirectResponse
    {
        $data = $this->validated($request);

        $candidate->update(collect($data)->except('files')->all());

        if ($error = $this->attachFiles($candidate, $request)) {
            return back()->withErrors(['files' => $error]);
        }

        return redirect()->route('candidates.show', $candidate)->with('success', 'Candidate updated.');
    }

    public function destroy(Candidate $candidate): RedirectResponse
    {
        $candidate->delete();

        return redirect()->route('candidates.index')->with('success', 'Candidate removed.');
    }

    public function destroyMedia(Candidate $candidate, Media $media): RedirectResponse
    {
        abort_unless((string) $media->model_id === (string) $candidate->id, 404);

        $media->delete();

        return back()->with('success', 'File removed.');
    }

    /**
     * Record the interview outcome: selected candidates move to the hire
     * step, not-selected ones stay in the candidate database (unchanged)
     * for future reference instead of being deleted.
     */
    public function decide(Request $request, Candidate $candidate): RedirectResponse
    {
        abort_if($candidate->status === 'hired', 422, 'This candidate was already hired.');

        $data = $request->validate([
            'status' => ['required', 'in:selected,not_selected,pending'],
            'interview_date' => ['nullable', 'date'],
            'interview_notes' => ['nullable', 'string'],
        ]);

        $candidate->update($data);

        return back()->with('success', 'Interview outcome recorded.');
    }

    public function hireForm(Candidate $candidate): View
    {
        abort_unless($candidate->status === 'selected', 422, 'Only a selected candidate can be hired.');

        $departments = Department::orderBy('name')->get();

        return view('candidates.hire', compact('candidate', 'departments'));
    }

    public function hire(Request $request, Candidate $candidate): RedirectResponse
    {
        abort_unless($candidate->status === 'selected', 422, 'Only a selected candidate can be hired.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'designation' => ['nullable', 'string', 'max:150'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'employment_type' => ['required', 'in:permanent,contract,daily_wage'],
            'joining_date' => ['nullable', 'date'],
            'salary_type' => ['required', 'in:monthly,daily,hourly'],
            'salary_amount' => ['nullable', 'numeric', 'min:0'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'experience_summary' => ['nullable', 'string'],
        ]);

        $employee = Employee::create($data + [
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        $candidate->update(['status' => 'hired', 'employee_id' => $employee->id]);

        return redirect()->route('employees.show', $employee)->with('success', 'Candidate hired and added to the Worker list.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'position_applied' => ['nullable', 'string', 'max:150'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'experience_summary' => ['nullable', 'string'],
            'expected_salary' => ['nullable', 'numeric', 'min:0'],
            'interview_date' => ['nullable', 'date'],
            'interview_notes' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => self::FILE_RULES,
        ]);
    }

    private function attachFiles(Candidate $candidate, Request $request): ?string
    {
        try {
            foreach ($request->file('files', []) as $file) {
                $candidate->addMedia($file)->toMediaCollection('documents');
            }
        } catch (FileIsTooBig $e) {
            return 'One of those files is too large (max 20MB).';
        }

        return null;
    }
}
