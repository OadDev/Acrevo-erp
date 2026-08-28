<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Temporary: investigating a report that WO ZIP downloads (a) still include
 * deleted attachments and (b) sometimes include attachments from a
 * different Site/Work Order. media.model_id was fixed from
 * unsignedBigInteger to uuid on 2026-08-14 for UUID-keyed models
 * (WorkOrder, Site, ApprovalRequest, Ticket) - this checks whether that fix
 * is live and whether any pre-fix corrupted/orphaned rows remain.
 */
class DiagnoseMediaCommand extends Command
{
    protected $signature = 'diagnose:media';

    public function handle(): void
    {
        $this->line('--- media.model_id column type ---');
        $this->line(Schema::getColumnType('media', 'model_id'));

        $this->line('--- media rows per model_type ---');
        DB::table('media')
            ->select('model_type', DB::raw('count(*) as c'))
            ->groupBy('model_type')
            ->orderByDesc('c')
            ->get()
            ->each(fn ($r) => $this->line("{$r->model_type}: {$r->c}"));

        $uuidModels = [
            'App\\Models\\WorkOrder' => 'work_orders',
            'App\\Models\\Site' => 'sites',
            'App\\Models\\ApprovalRequest' => 'approval_requests',
            'App\\Models\\Ticket' => 'tickets',
        ];

        foreach ($uuidModels as $modelType => $table) {
            $this->line("--- sample model_id values for {$modelType} ---");
            DB::table('media')
                ->where('model_type', $modelType)
                ->select('id', 'model_id', 'collection_name', 'created_at')
                ->orderBy('id')
                ->limit(5)
                ->get()
                ->each(fn ($r) => $this->line(json_encode($r)));

            $orphaned = DB::table('media')
                ->where('model_type', $modelType)
                ->whereNotIn('model_id', DB::table($table)->select('id'))
                ->count();
            $this->line("orphaned {$modelType} media (model_id not in {$table}.id): {$orphaned}");

            $collisions = DB::table('media')
                ->where('model_type', $modelType)
                ->select('model_id', DB::raw('count(distinct id) as media_rows'))
                ->groupBy('model_id')
                ->havingRaw('count(distinct id) > 15')
                ->get();
            $this->line("model_id values with >15 media rows for {$modelType}: {$collisions->count()}");
            $collisions->each(fn ($r) => $this->line(json_encode($r)));
        }
    }
}
