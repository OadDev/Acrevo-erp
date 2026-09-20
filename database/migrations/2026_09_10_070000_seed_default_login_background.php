<?php

use App\Models\LoginPageSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Ships a real construction-site photo as the login page's out-of-the-
     * box background, so the page looks finished on day one instead of
     * waiting on an Admin to upload something first. Only applies if no
     * background has been set yet, so it never overwrites an Admin's own
     * upload (this runs once, on the first deploy after the login page
     * customization feature shipped - by the time it runs again on a
     * fresh install, this condition is what keeps it a no-op if someone
     * already uploaded their own image in between).
     */
    public function up(): void
    {
        $path = public_path('images/login-bg-seed.jpg');

        if (! file_exists($path)) {
            return;
        }

        $setting = LoginPageSetting::current();

        if ($setting->backgroundImage()) {
            return;
        }

        $setting->addMedia($path)->preservingOriginal()->toMediaCollection('background');
    }

    public function down(): void
    {
        // Intentionally left as a no-op: rolling back shouldn't delete an
        // image that may since have been replaced by an Admin's own upload.
    }
};
