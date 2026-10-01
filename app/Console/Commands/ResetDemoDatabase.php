<?php

namespace App\Console\Commands;

use App\Support\DemoDatabase;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes the demo_-prefixed tables back to a blank slate and reseeds fresh
 * sample data - for when a client's demo session leaves it messy.
 *
 * Deliberately does NOT use migrate:fresh/db:wipe: those drop every table
 * in the database with no concept of this connection's "demo_" prefix,
 * which would take the real tables with them since this is the same
 * database. Instead this only ever DELETEs rows from tables it can prove
 * are named "demo_*", skipping the migrations tracking table itself so a
 * later demo:provision still sees the schema as already migrated.
 */
class ResetDemoDatabase extends Command
{
    protected $signature = 'demo:reset {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe the demo_-prefixed tables and reseed fresh sample data';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will permanently wipe all data in the demo tables and reseed fresh sample data. Continue?')) {
            return self::SUCCESS;
        }

        $connection = DB::connection('demo');
        $prefix = $connection->getTablePrefix();
        $migrationsTable = $prefix.config('database.migrations.table', 'migrations');

        $tables = Schema::connection('demo')->getTableListing(schemaQualified: false);

        $driver = $connection->getDriverName();
        $driver === 'sqlite'
            ? $connection->statement('PRAGMA foreign_keys = OFF')
            : $connection->statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table) {
            // Extra safety net on top of this connection's own prefix: never
            // touch a table whose name doesn't actually start with it, and
            // never wipe migration tracking itself.
            if (! str_starts_with($table, $prefix) || $table === $migrationsTable) {
                continue;
            }

            // $table is already the full physical name (e.g. "demo_clients"),
            // so wrapTable() must not add the connection's prefix a second time.
            $connection->statement('DELETE FROM '.$connection->getQueryGrammar()->wrapTable($table, ''));
        }

        $driver === 'sqlite'
            ? $connection->statement('PRAGMA foreign_keys = ON')
            : $connection->statement('SET FOREIGN_KEY_CHECKS=1');

        DemoDatabase::mirrorDemoUser();
        $this->call('db:seed', ['--class' => DemoDataSeeder::class, '--database' => 'demo', '--force' => true]);

        $this->info('Demo tables reset to fresh sample data.');

        return self::SUCCESS;
    }
}
