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
            $count = ExecutiveTeam::count() + 1;

            return ExecutiveTeam::create($request->validated() + [
                'team_number' => sprintf('TEAM-%03d', $count),
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('executive-teams.show', $team)->with('success', 'Executive team created.');
    }

    public function show(ExecutiveTeam $executiveTeam): View
    {
        $executiveTeam->load(['teamLeader', 'members.employee', 'workOrderAssignments.workOrder']);
        $availableEmployees = Employee::where('status', 'active')
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
        $executiveTeam->update(['is_active' => false]);

        return redirect()->route('executive-teams.index')->with('success', 'Team deactivated.');
    }

    public function addMember(Request $request, ExecutiveTeam $executiveTeam): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'role_in_team' => ['nullable', 'string', 'max:100'],
        ]);

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
