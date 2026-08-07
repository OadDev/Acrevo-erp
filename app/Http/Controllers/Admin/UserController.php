<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
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
        $roles = Role::orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        return view('admin.users.create', compact('roles', 'departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation' => ['nullable', 'string', 'max:150'],
            'role' => ['required', 'exists:roles,name'],
        ]);

        $password = Str::password(12);

        $user = User::create($data + [
            'password' => Hash::make($password),
            'is_active' => true,
            'must_change_password' => true,
            'created_by' => $request->user()->id,
        ]);

        $user->assignRole($data['role']);

        return redirect()->route('admin.users.index')->with('success', "User created. Temporary password: {$password}");
    }

    public function edit(User $user): View
    {
        $roles = Role::orderBy('name')->get();
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
            'role' => ['required', 'exists:roles,name'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user->update($data + ['is_active' => $request->boolean('is_active')]);
        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->update(['is_active' => false]);

        return back()->with('success', 'User deactivated.');
    }
}
