<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExecutiveTeamRequest;
use App\Models\Employee;
use App\Models\ExecutiveTeam;
use App\Models\ExecutiveTeamMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExecutiveTeamController extends Controller
{
    public function index(): View
    {
        $teams = ExecutiveTeam::with(['teamLeader', 'members'])->latest()->paginate(15);

        return view('executive-teams.index', compact('teams'));
    }

    public function create(): View
    {
        $teamLeaders = User::role(['Executive Team Leader', 'Admin'])->get();

        return view('executive-teams.create', compact('teamLeaders'));
    }

    public function store(ExecutiveTeamRequest $request): RedirectResponse
    {
        $team = DB::transaction(function () use ($request) {
            $prefix = 'TEAM-';
            $next = ExecutiveTeam::withTrashed()
                ->where('team_number', 'like', "{$prefix}%")
                ->pluck('team_number')
                ->map(fn ($value) => (int) substr($value, strlen($prefix)))
                ->max() ?? 0;

            do {
                $next++;
                $candidate = sprintf('%s%03d', $prefix, $next);
            } while (ExecutiveTeam::withTrashed()->where('team_number', $candidate)->exists());

            return ExecutiveTeam::create($request->validated() + [
                'team_number' => $candidate,
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('executive-teams.show', $team)->with('success', 'Executive team created.');
    }

    public function show(ExecutiveTeam $executiveTeam): View
    {
        $executiveTeam->load(['teamLeader', 'members.employee', 'workOrderAssignments.workOrder']);
        // The HR > Worker list holds both unregistered workers (no login,
        // Employee-only) and registered workers (Employee linked to a User
        // with the Worker role). Both are eligible team members - an
        // Employee linked to a non-Worker User (HR, Sales, etc.) is not.
        $availableEmployees = Employee::where('status', 'active')
            ->where(fn ($q) => $q->whereNull('user_id')->orWhereHas('user', fn ($q2) => $q2->role('Worker')))
            ->whereNotIn('id', $executiveTeam->members->pluck('employee_id'))
            ->get();

        return view('executive-teams.show', compact('executiveTeam', 'availableEmployees'));
    }

    public function edit(ExecutiveTeam $executiveTeam): View
    {
        $teamLeaders = User::role(['Executive Team Leader', 'Admin'])->get();

        return view('executive-teams.edit', compact('executiveTeam', 'teamLeaders'));
    }

    public function update(ExecutiveTeamRequest $request, ExecutiveTeam $executiveTeam): RedirectResponse
    {
        $executiveTeam->update($request->validated());

        return redirect()->route('executive-teams.show', $executiveTeam)->with('success', 'Team updated.');
    }

    public function destroy(ExecutiveTeam $executiveTeam): RedirectResponse
    {
        // Only count assignments to work orders that still exist - one
        // pointing at a deleted work order is a dead end for the admin
        // (there's no page left to unassign it from), and WorkOrderController
        // now clears unassigned_at when a work order is deleted anyway; this
        // is just defense against any assignment left over from before that.
        $activeAssignments = $executiveTeam->workOrderAssignments()
            ->whereNull('unassigned_at')
            ->whereHas('workOrder')
            ->count();

        abort_if($activeAssignments > 0, 422, "This team is still assigned to {$activeAssignments} work order(s). Unassign it from those work orders before removing the team.");

        $executiveTeam->update(['is_active' => false]);
        $executiveTeam->delete();

        return redirect()->route('executive-teams.index')->with('success', 'Team removed.');
    }

    public function addMember(Request $request, ExecutiveTeam $executiveTeam): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'role_in_team' => ['nullable', 'string', 'max:100'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        abort_if($employee->user && ! $employee->user->hasRole('Worker'), 422, 'Only workers from the HR > Worker list can be added to an executive team.');

        $executiveTeam->members()->create($data + ['joined_at' => now()]);

        return back()->with('success', 'Worker added to team.');
    }

    public function removeMember(ExecutiveTeam $executiveTeam, ExecutiveTeamMember $member): RedirectResponse
    {
        $member->update(['left_at' => now()]);
        $member->delete();

        return back()->with('success', 'Worker removed from team.');
    }
}
