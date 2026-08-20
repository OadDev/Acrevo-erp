<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ExecutiveTeam;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with(['roles', 'department'])
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $roles = Role::where('name', '!=', 'Client')->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        return view('admin.users.create', compact('roles', 'departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation' => ['nullable', 'string', 'max:150'],
            'role' => ['required', 'exists:roles,name', Rule::notIn(['Client'])],
        ], [
            'role.not_in' => 'Client accounts are created from the Client\'s page ("Generate Portal Access"), not here - that keeps the login linked to the right Client record.',
        ]);

        if (User::where('email', $data['email'])->exists()) {
            return back()->withInput()->withErrors(['email' => 'This email is already registered to an active account.']);
        }

        $password = Str::password(12);

        // The email column is unique at the database level with no exception for
        // soft-deleted rows, so a deleted user's email is otherwise stuck forever.
        // Restoring their old (trashed) row instead of inserting a fresh one is
        // what "delete then recreate this account" actually means to an admin.
        $trashed = User::onlyTrashed()->where('email', $data['email'])->first();

        if ($trashed) {
            $trashed->restore();
            $trashed->update($data + [
                'password' => Hash::make($password),
                'is_active' => true,
                'must_change_password' => true,
                'created_by' => $request->user()->id,
            ]);
            $trashed->syncRoles([$data['role']]);
            $this->syncLinkedEmployeeRecord($trashed, $data);

            return redirect()->route('admin.users.index')->with('success', "Restored the previously deleted account for this email. Temporary password: {$password}");
        }

        $user = User::create($data + [
            'password' => Hash::make($password),
            'is_active' => true,
            'must_change_password' => true,
            'created_by' => $request->user()->id,
        ]);

        $user->assignRole($data['role']);
        $this->syncLinkedEmployeeRecord($user, $data);

        return redirect()->route('admin.users.index')->with('success', "User created. Temporary password: {$password}");
    }

    /**
     * Roles the HR module tracks via an Employee record - not just Workers.
     * The Worker list, Executive Team membership, and a Worker's own
     * attendance dashboard key off Employee::user_id for the Worker role;
     * HR > Attendance needs the same link for Sales/HR/Finance/Executive
     * Team Leader/QC Officer, since that's who staff attendance is for
     * (Worker attendance is entered separately, via a work order's M.Book).
     */
    private const EMPLOYEE_LINKED_ROLES = ['Worker', ...Employee::STAFF_ROLES];

    /**
     * Keeps the linked Employee's basic details in sync on every save, for
     * any role in EMPLOYEE_LINKED_ROLES.
     */
    private function syncLinkedEmployeeRecord(User $user, array $data): void
    {
        if (! in_array($data['role'], self::EMPLOYEE_LINKED_ROLES, true)) {
            return;
        }

        $employee = $user->employee ?: new Employee(['user_id' => $user->id, 'status' => 'active', 'created_by' => $user->created_by]);

        $employee->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'],
            'designation' => $data['designation'] ?? null,
            'department_id' => $data['department_id'] ?? null,
        ])->save();
    }

    public function edit(User $user): View
    {
        $roles = Role::where('name', '!=', 'Client')->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'roles', 'departments'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation' => ['nullable', 'string', 'max:150'],
            'role' => ['required', 'exists:roles,name', Rule::notIn(['Client'])],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'role.not_in' => 'Client accounts are managed from the Client\'s page, not here.',
        ]);

        $user->update($data + ['is_active' => $request->boolean('is_active')]);
        $user->syncRoles([$data['role']]);
        $this->syncLinkedEmployeeRecord($user, $data);

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, "You can't deactivate your own account.");

        $user->update(['is_active' => false]);

        return back()->with('success', 'User deactivated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, "You can't delete your own account.");

        if ($user->hasRole('Admin') && User::role('Admin')->count() <= 1) {
            return back()->withErrors(['user' => 'You cannot delete the last remaining Admin account.']);
        }

        if ($teamNames = ExecutiveTeam::where('team_leader_id', $user->id)->pluck('name')->join(', ')) {
            return back()->withErrors(['user' => "This user leads an executive team ({$teamNames}). Assign a new team leader first, then delete this account."]);
        }

        try {
            $user->delete();
        } catch (QueryException $e) {
            return back()->withErrors(['user' => 'This user created records (measurement books, ledger entries, etc.) that must exist first, so they can\'t be deleted. Deactivate the account instead.']);
        }

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}
