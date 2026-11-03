<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boxes', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 40)->unique();
            $table->enum('barcode_source', ['generated', 'manual'])->default('generated');
            $table->string('manual_barcode_origin')->nullable();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('entry_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('origin_chamber_id')->constrained('chambers')->restrictOnDelete();
            $table->decimal('initial_weight_kg', 10, 3);
            $table->decimal('current_weight_kg', 10, 3);
            $table->decimal('unit_cost_usd', 14, 2);
            $table->dateTime('entered_at');
            $table->enum('status', ['available', 'in_transit', 'dispatched', 'depleted', 'written_off'])->default('available');
            $table->foreignId('current_chamber_id')->nullable()->constrained('chambers')->restrictOnDelete();
            $table->timestamps();

            $table->index(['current_chamber_id', 'status', 'product_id']);
            $table->index(['product_id', 'entered_at']);
        });

        DB::statement('ALTER TABLE boxes ADD CONSTRAINT boxes_weights_valid CHECK (initial_weight_kg > 0 AND current_weight_kg >= 0 AND current_weight_kg <= initial_weight_kg AND unit_cost_usd >= 0)');
        DB::statement("ALTER TABLE boxes ADD CONSTRAINT boxes_location_matches_status CHECK ((status = 'available') = (current_chamber_id IS NOT NULL))");
        DB::statement("ALTER TABLE boxes ADD CONSTRAINT boxes_depleted_is_empty CHECK (status <> 'depleted' OR current_weight_kg = 0)");
        DB::statement("ALTER TABLE boxes ADD CONSTRAINT boxes_manual_origin_required CHECK (barcode_source <> 'manual' OR manual_barcode_origin IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('boxes');
    }
};
