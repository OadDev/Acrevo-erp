<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ledger of how much of an asset sits where and in what state -
     * "20 Steel Sheets" can be 10 Available at Company Store, 7 Available
     * at WO202608-0052, and 3 In Transit between them, all at once. This is
     * the source of truth for quantity; Asset::quantity is just the sum of
     * these rows, kept in sync by AssetStock::adjust().
     *
     * No DB-level unique constraint on (asset_id, location, work_order_id,
     * status) - work_order_id is nullable and MySQL/SQLite both treat NULL
     * as distinct-from-itself in a unique index, so it wouldn't actually
     * stop duplicate company-store rows. AssetStock::bucket() enforces
     * uniqueness in code instead (firstOrCreate before every adjustment).
     */
    public function up(): void
    {
        Schema::create('asset_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('location');
            // work_orders.id is a UUID (WorkOrder uses HasUuids) - see the
            // matching note in the assets table migration.
            $table->foreignUuid('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->string('status');
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->index(['asset_id', 'location', 'work_order_id', 'status'], 'asset_stocks_bucket_index');
        });

        // Every asset that already exists gets one starting bucket that
        // mirrors its current singular location/status, so the ledger
        // isn't empty for equipment entered before this feature existed.
        $now = now();
        DB::table('assets')->whereNull('deleted_at')->orderBy('id')->chunk(200, function ($assets) use ($now) {
            $rows = $assets->map(fn ($asset) => [
                'asset_id' => $asset->id,
                'location' => $asset->current_location,
                'work_order_id' => $asset->current_work_order_id,
                'status' => match ($asset->status) {
                    'under_repair' => 'under_repair',
                    'damaged' => 'damaged',
                    'missing' => 'missing',
                    default => 'available',
                },
                'quantity' => $asset->quantity ?? 1,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            DB::table('asset_stocks')->insert($rows);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_stocks');
    }
};
