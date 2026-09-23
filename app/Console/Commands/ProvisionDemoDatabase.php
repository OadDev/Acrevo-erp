<?php

namespace App\Console\Commands;

use App\Support\DemoDatabase;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Throwable;

/**
 * Makes the isolated demo database usable after a deploy: creates it if it
 * doesn't exist yet (best-effort - shared hosting often restricts this to
 * the hosting panel), migrates its schema, and seeds sample data only if
 * it's still empty. Safe to run on every deploy.
 */
class ProvisionDemoDatabase extends Command
{
    protected $signature = 'demo:provision';

    protected $description = 'Create (if possible), migrate, and seed the isolated demo database';

    public function handle(): int
    {
        $demoDatabase = config('database.connections.demo.database');
        $main = config('database.connections.'.config('database.main_connection'));

        if (($main['driver'] ?? null) === 'mysql') {
            try {
                $pdo = new PDO(
                    "mysql:host={$main['host']};port={$main['port']}",
                    $main['username'],
                    $main['password']
                );
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$demoDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $this->info("Demo database `{$demoDatabase}` ready.");
            } catch (Throwable $e) {
                $this->warn("Could not auto-create the demo database: {$e->getMessage()}");
                $this->warn("Create a database named exactly \"{$demoDatabase}\" via your hosting panel, grant the same DB user access to it, then re-run: php artisan demo:provision");

                return self::FAILURE;
            }
        }

        $this->call('migrate', ['--database' => 'demo', '--force' => true]);

        DemoDatabase::mirrorDemoUser();

        if (Schema::connection('demo')->hasTable('clients') && DB::connection('demo')->table('clients')->count() > 0) {
            $this->info('Demo database already has data - left as-is.');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => DemoDataSeeder::class, '--database' => 'demo', '--force' => true]);
        $this->info('Demo sample data seeded.');

        return self::SUCCESS;
    }
}
