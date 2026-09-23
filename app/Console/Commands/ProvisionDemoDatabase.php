<?php

namespace App\Console\Commands;

use App\Support\DemoDatabase;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the demo tables usable after a deploy: migrates the "demo_"
 * prefixed tables (safe and additive - see config/database.php's warning
 * on why this must stay plain migrate, never migrate:fresh) and seeds
 * sample data only if they're still empty. Safe to run on every deploy.
 */
class ProvisionDemoDatabase extends Command
{
    protected $signature = 'demo:provision';

    protected $description = 'Migrate and seed the demo_-prefixed tables';

    public function handle(): int
    {
        $this->call('migrate', ['--database' => 'demo', '--force' => true]);

        DemoDatabase::mirrorDemoUser();

        if (Schema::connection('demo')->hasTable('clients') && DB::connection('demo')->table('clients')->count() > 0) {
            $this->info('Demo tables already have data - left as-is.');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => DemoDataSeeder::class, '--database' => 'demo', '--force' => true]);
        $this->info('Demo sample data seeded.');

        return self::SUCCESS;
    }
}
