<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $departments = Department::withCount('users', 'employees')->orderBy('name')->get();

        return view('admin.departments.index', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:departments,code'],
            'description' => ['nullable', 'string'],
        ]);

        Department::create($data + ['is_active' => true]);

        return redirect()->route('admin.departments.index')->with('success', 'Department added.');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('departments', 'code')->ignore($department->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $department->update($data + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.departments.index')->with('success', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        abort_if($department->users()->exists() || $department->employees()->exists(), 422, 'This department has users or workers assigned to it. Reassign them before removing it.');

        $department->delete();

        return redirect()->route('admin.departments.index')->with('success', 'Department removed.');
    }
}
