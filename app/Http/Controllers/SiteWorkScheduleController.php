<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteWorkSchedule;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkScheduleRecalculator;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteWorkScheduleController extends Controller
{
    /**
     * Overall Schedule View - every accessible site with its work count,
     * planned/revised date range, total duration, and delay count, so an
     * Admin (or anyone granted work_schedules.view_overall) can scan every
     * project's schedule at once instead of opening each site.
     */
    public function overall(Request $request): View
    {
        $sites = $this->overallQuery($request)->paginate(20)->withQueryString();

        return view('work-schedules.overall', compact('sites'));
    }

    public function overallPdf(Request $request)
    {
        $sites = $this->overallQuery($request)->get();

        $pdf = Pdf::loadView('work-schedules.overall-pdf', compact('sites'));

        return $pdf->download('site-work-schedules-overall.pdf');
    }

    /**
     * Individual Site View - every work item on this site's schedule with
     * day numbers, calendar dates, original vs revised, and variance.
     */
    public function show(Request $request, Site $site): View
    {
        $this->authorizeSite($request->user(), $site);

        $site->load(['workSchedules.createdBy', 'client']);

        return view('sites.work-schedule.show', [
            'site' => $site,
            'schedules' => $site->workSchedules,
            'projectStart' => WorkScheduleRecalculator::projectStartDate($site),
            'projectedCompletion' => WorkScheduleRecalculator::projectedCompletionDate($site),
        ]);
    }

    public function pdf(Request $request, Site $site)
    {
        $this->authorizeSite($request->user(), $site);

        $site->load(['workSchedules.createdBy', 'client']);

        $pdf = Pdf::loadView('sites.work-schedule.pdf', [
            'site' => $site,
            'schedules' => $site->workSchedules,
            'projectStart' => WorkScheduleRecalculator::projectStartDate($site),
            'projectedCompletion' => WorkScheduleRecalculator::projectedCompletionDate($site),
        ]);

        return $pdf->download("{$site->site_no}-work-schedule.pdf");
    }

    public function store(Request $request, Site $site): RedirectResponse
    {
        $this->authorizeSite($request->user(), $site);

        $hasExisting = $site->workSchedules()->exists();

        $data = $request->validate([
            'work_name' => ['required', 'string', 'max:255'],
            'work_details' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'schedule_mode' => [$hasExisting ? 'required' : 'nullable', 'in:'.implode(',', SiteWorkSchedule::MODES)],
            'depends_on_schedule_id' => ['nullable', 'integer', 'exists:site_work_schedules,id'],
            'lag_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'start_date' => ['nullable', 'date'],
        ]);

        $mode = $hasExisting ? $data['schedule_mode'] : 'independent';

        if ($mode === 'depends_on') {
            $dependency = $site->workSchedules()->find($data['depends_on_schedule_id'] ?? null);
            abort_unless($dependency, 422, 'Pick a work on this site for the new work to depend on.');
        } elseif (empty($data['start_date'])) {
            return back()->withErrors(['start_date' => 'A start date is required for an independent work.'])->withInput();
        }

        $sequenceOrder = ($site->workSchedules()->max('sequence_order') ?? 0) + 1;
        $placeholder = $data['start_date'] ?? now()->toDateString();

        $schedule = $site->workSchedules()->create([
            'sequence_order' => $sequenceOrder,
            'work_name' => $data['work_name'],
            'work_details' => $data['work_details'] ?? null,
            'schedule_mode' => $mode,
            'depends_on_schedule_id' => $mode === 'depends_on' ? $data['depends_on_schedule_id'] : null,
            'lag_days' => $data['lag_days'] ?? 0,
            'revised_duration_days' => $data['duration_days'],
            'original_duration_days' => $data['duration_days'],
            'revised_start_date' => $placeholder,
            'revised_end_date' => $placeholder,
            'original_start_date' => $placeholder,
            'original_end_date' => $placeholder,
            'status' => 'not_started',
            'created_by' => $request->user()->id,
        ]);

        WorkScheduleRecalculator::recalculate($site);

        $schedule->refresh();
        $schedule->forceFill([
            'original_start_date' => $schedule->revised_start_date,
            'original_end_date' => $schedule->revised_end_date,
        ])->save();

        return back()->with('success', 'Work schedule added.');
    }

    public function update(Request $request, Site $site, SiteWorkSchedule $workSchedule): RedirectResponse
    {
        $this->authorizeSite($request->user(), $site);
        abort_unless($workSchedule->site_id === $site->id, 404);

        $data = $request->validate([
            'work_name' => ['required', 'string', 'max:255'],
            'work_details' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'revised_end_date' => ['nullable', 'date'],
            'schedule_mode' => ['required', 'in:'.implode(',', SiteWorkSchedule::MODES)],
            'depends_on_schedule_id' => ['nullable', 'integer', 'exists:site_work_schedules,id'],
            'lag_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'start_date' => ['nullable', 'date'],
            'original_start_date' => ['nullable', 'date'],
            'original_duration_days' => ['nullable', 'integer', 'min:1'],
            'actual_start_date' => ['nullable', 'date'],
            'actual_end_date' => [
                'nullable', 'date',
                function ($attribute, $value, $fail) use ($request) {
                    $start = $request->input('actual_start_date');
                    if ($start && $value && \Illuminate\Support\Carbon::parse($value)->lt(\Illuminate\Support\Carbon::parse($start))) {
                        $fail('Actual End Date must be on or after the Actual Start Date.');
                    }
                },
            ],
            'actual_progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['required', 'in:'.implode(',', SiteWorkSchedule::STATUSES)],
            'delay_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $dependsOnId = null;

        if ($data['schedule_mode'] === 'depends_on') {
            $dependsOnId = $data['depends_on_schedule_id'] ?? null;
            $dependency = $dependsOnId ? $site->workSchedules()->find($dependsOnId) : null;

            abort_unless($dependency, 422, 'Pick a work on this site for this work to depend on.');
            abort_if($dependsOnId === $workSchedule->id, 422, 'A work cannot depend on itself.');
            abort_if($dependency->id > $workSchedule->id, 422, 'A work can only depend on a work that was added before it.');
        } elseif (empty($data['start_date']) && $workSchedule->schedule_mode !== 'independent') {
            return back()->withErrors(['start_date' => 'A start date is required for an independent work.'])->withInput();
        }

        if ($data['schedule_mode'] === 'independent' && ! empty($data['start_date'])) {
            $workSchedule->revised_start_date = $data['start_date'];
        }

        // A directly-typed Revised End Date overrides the Duration field -
        // computed against this work's start date (the one just set above
        // for an independent work, otherwise its last-known revised start,
        // since a dependent work's true start isn't final until the
        // recalculation below runs).
        $durationDays = $data['duration_days'];

        if (! empty($data['revised_end_date'])) {
            $anchorStart = $workSchedule->revised_start_date;
            $computed = $anchorStart->diffInDays(\Illuminate\Support\Carbon::parse($data['revised_end_date'])) + 1;
            abort_if($computed < 1, 422, "Revised End Date must be on or after this work's start date ({$anchorStart->format('d M Y')}).");
            $durationDays = $computed;
        }

        $workSchedule->fill([
            'work_name' => $data['work_name'],
            'work_details' => $data['work_details'] ?? null,
            'schedule_mode' => $data['schedule_mode'],
            'depends_on_schedule_id' => $dependsOnId,
            'lag_days' => $data['lag_days'] ?? 0,
            'revised_duration_days' => $durationDays,
            'actual_start_date' => $data['actual_start_date'] ?? null,
            'actual_end_date' => $data['actual_end_date'] ?? null,
            'actual_progress_percent' => $data['actual_progress_percent'] ?? null,
            'status' => $data['status'],
            'delay_reason' => $data['delay_reason'] ?? null,
        ]);

        // Correcting the Original (baseline) schedule is a deliberate,
        // separate action from revising the plan - only touched when the
        // admin explicitly fills in the baseline-correction fields, e.g.
        // to fix a typo made when the work was first created. Routine
        // schedule changes never reach here, keeping variance meaningful.
        if (! empty($data['original_start_date']) || ! empty($data['original_duration_days'])) {
            $originalStart = ! empty($data['original_start_date'])
                ? \Illuminate\Support\Carbon::parse($data['original_start_date'])
                : $workSchedule->original_start_date;
            $originalDuration = $data['original_duration_days'] ?? $workSchedule->original_duration_days;

            $workSchedule->original_start_date = $originalStart;
            $workSchedule->original_duration_days = $originalDuration;
            $workSchedule->original_end_date = $originalStart->copy()->addDays(max(0, $originalDuration - 1));
        }

        $workSchedule->save();

        WorkScheduleRecalculator::recalculate($site);

        return back()->with('success', 'Work schedule updated.');
    }

    public function destroy(Request $request, Site $site, SiteWorkSchedule $workSchedule): RedirectResponse
    {
        $this->authorizeSite($request->user(), $site);
        abort_unless($workSchedule->site_id === $site->id, 404);

        $workSchedule->delete();

        WorkScheduleRecalculator::recalculate($site);

        return back()->with('success', 'Work schedule removed.');
    }

    private function overallQuery(Request $request)
    {
        $siteIds = $this->accessibleSiteIds($request->user());

        return Site::query()
            ->with(['client', 'workSchedules'])
            ->whereHas('workSchedules')
            ->when($siteIds !== null, fn ($q) => $q->whereIn('id', $siteIds))
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('site_no', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', "%{$search}%"))))
            ->orderBy('site_no');
    }

    /**
     * Sites this user may view a schedule for - Admin sees everything;
     * everyone else is scoped to sites they lead (Executive Team Leader),
     * sites of clients assigned to them (Sales), or their own site
     * (Client) - null means unscoped.
     */
    private function accessibleSiteIds(User $user): ?array
    {
        if ($user->hasRole('Admin')) {
            return null;
        }

        $siteIds = collect();

        if ($client = $user->client()) {
            $siteIds = $siteIds->merge(Site::where('client_id', $client->id)->pluck('id'));
        }

        $ledSiteIds = WorkOrder::whereHas('executiveTeams', fn ($q) => $q->whereNull('unassigned_at')
            ->whereHas('executiveTeam', fn ($q2) => $q2->where('team_leader_id', $user->id)))
            ->pluck('site_id');
        $siteIds = $siteIds->merge($ledSiteIds);

        $salesSiteIds = Site::whereHas('client', fn ($q) => $q->where('assigned_sales_user_id', $user->id))->pluck('id');
        $siteIds = $siteIds->merge($salesSiteIds);

        return $siteIds->unique()->values()->all();
    }

    private function authorizeSite(User $user, Site $site): void
    {
        $siteIds = $this->accessibleSiteIds($user);

        abort_if($siteIds !== null && ! in_array($site->id, $siteIds, true), 403);
    }
}
