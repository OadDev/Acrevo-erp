<?php

namespace App\Providers;

use App\Events\WorkOrderStatusChanged;
use App\Listeners\NotifyWorkOrderStakeholders;
use App\Models\WorkOrder;
use App\Observers\WorkOrderObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        WorkOrder::observe(WorkOrderObserver::class);

        Event::listen(WorkOrderStatusChanged::class, NotifyWorkOrderStakeholders::class);

        // dompdf writes generated font metrics for @font-face fonts (e.g. the Tamil
        // PDF font) here; storage/ isn't deployed, so it must exist at runtime.
        if (! File::isDirectory(storage_path('fonts'))) {
            File::makeDirectory(storage_path('fonts'), 0755, true);
        }
    }
}
