<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $activities = $this->filtered($request)->paginate(25)->withQueryString();
        $logNames = Activity::query()->whereNotNull('log_name')->distinct()->orderBy('log_name')->pluck('log_name');

        return view('admin.activity-logs.index', compact('activities', 'logNames'));
    }

    public function pdf(Request $request)
    {
        $activities = $this->filtered($request)->get();

        $pdf = Pdf::loadView('admin.activity-logs.pdf', compact('activities'));

        return $pdf->download('activity-log.pdf');
    }

    private function filtered(Request $request)
    {
        return Activity::query()
            ->with('causer')
            ->when($request->get('log_name'), fn ($q, $v) => $q->where('log_name', $v))
            ->when($request->get('subject_type'), fn ($q, $v) => $q->where('subject_type', $v))
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('description', 'like', "%{$search}%")
                ->orWhereHas('causer', fn ($q3) => $q3->where('name', 'like', "%{$search}%"))))
            ->when($request->get('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->get('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest();
    }
}
