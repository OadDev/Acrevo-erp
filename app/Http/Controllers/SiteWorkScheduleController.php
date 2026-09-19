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

        $isFirst = $site->workSchedules()->count() === 0;

        $data = $request->validate([
            'work_name' => ['required', 'string', 'max:255'],
            'work_details' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'is_parallel' => ['nullable', 'boolean'],
            'lag_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'start_date' => [$isFirst ? 'required' : 'nullable', 'date'],
        ]);

        $sequenceOrder = ($site->workSchedules()->max('sequence_order') ?? 0) + 1;
        $placeholder = $data['start_date'] ?? now()->toDateString();

        $schedule = $site->workSchedules()->create([
            'sequence_order' => $sequenceOrder,
            'work_name' => $data['work_name'],
            'work_details' => $data['work_details'] ?? null,
            'is_parallel' => $isFirst ? false : (bool) ($data['is_parallel'] ?? false),
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

        $isFirst = $workSchedule->sequence_order === (int) $site->workSchedules()->min('sequence_order');

        $data = $request->validate([
            'work_name' => ['required', 'string', 'max:255'],
            'work_details' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'is_parallel' => ['nullable', 'boolean'],
            'lag_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'start_date' => ['nullable', 'date'],
            'actual_start_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date'],
            'actual_progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['required', 'in:'.implode(',', SiteWorkSchedule::STATUSES)],
            'delay_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $workSchedule->fill([
            'work_name' => $data['work_name'],
            'work_details' => $data['work_details'] ?? null,
            'is_parallel' => $isFirst ? false : (bool) ($data['is_parallel'] ?? false),
            'lag_days' => $data['lag_days'] ?? 0,
            'revised_duration_days' => $data['duration_days'],
            'actual_start_date' => $data['actual_start_date'] ?? null,
            'actual_end_date' => $data['actual_end_date'] ?? null,
            'actual_progress_percent' => $data['actual_progress_percent'] ?? null,
            'status' => $data['status'],
            'delay_reason' => $data['delay_reason'] ?? null,
        ]);

        if ($isFirst && ! empty($data['start_date'])) {
            $workSchedule->revised_start_date = $data['start_date'];
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
