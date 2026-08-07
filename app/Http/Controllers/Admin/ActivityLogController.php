<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(): View
    {
        $activities = Activity::with('causer')->latest()->paginate(25);

        return view('admin.activity-logs.index', compact('activities'));
    }
}
