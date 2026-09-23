<?php

namespace App\Console\Commands;

use App\Support\DemoDatabase;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;

/**
 * Wipes the demo database back to a blank slate and reseeds fresh sample
 * data - for when a client's demo session leaves it messy. Never runs
 * automatically on deploy (see ProvisionDemoDatabase); this is deliberate,
 * on-demand only.
 */
class ResetDemoDatabase extends Command
{
    protected $signature = 'demo:reset {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe the isolated demo database and reseed it with fresh sample data';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will permanently wipe all data in the demo database and reseed fresh sample data. Continue?')) {
            return self::SUCCESS;
        }

        $this->call('migrate:fresh', ['--database' => 'demo', '--force' => true]);
        DemoDatabase::mirrorDemoUser();
        $this->call('db:seed', ['--class' => DemoDataSeeder::class, '--database' => 'demo', '--force' => true]);

        $this->info('Demo database reset to fresh sample data.');

        return self::SUCCESS;
    }
}
