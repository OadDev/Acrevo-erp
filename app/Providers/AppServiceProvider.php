<?php

namespace App\Providers;

use App\Events\WorkOrderStatusChanged;
use App\Listeners\NotifyWorkOrderStakeholders;
use App\Models\WorkOrder;
use App\Observers\MediaActivityObserver;
use App\Observers\WorkOrderObserver;
use App\Support\MailSettings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
        Media::observe(MediaActivityObserver::class);

        Event::listen(WorkOrderStatusChanged::class, NotifyWorkOrderStakeholders::class);

        // mPDF needs a writable temp dir for image/font caching; storage/ isn't
        // deployed, so it must exist at runtime.
        if (! File::isDirectory(storage_path('app/mpdf'))) {
            File::makeDirectory(storage_path('app/mpdf'), 0755, true);
        }

        // SMTP is configured by an Admin via Settings > Mail rather than by
        // editing .env directly on the server. Must run before any mail is
        // sent, so it's applied unconditionally at boot rather than only on
        // a mail-sending event (the transport is built from config as soon
        // as the mailer is first resolved, which can happen before any
        // "about to send" event fires).
        MailSettings::apply();
    }
}
