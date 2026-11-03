<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_id')->constrained()->restrictOnDelete();
            $table->foreignId('box_id')->constrained()->restrictOnDelete();
            $table->enum('kind', ['full', 'partial'])->default('full');
            $table->decimal('weight_before_kg', 10, 3);
            $table->decimal('weight_out_kg', 10, 3);
            $table->decimal('weight_after_kg', 10, 3);
            $table->decimal('unit_cost_snapshot_usd', 14, 2);
            $table->decimal('cost_out_usd', 16, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('active_full_box_id')
                ->nullable()
                ->virtualAs("IF(kind = 'full' AND is_active = 1, box_id, NULL)");
            $table->timestamps();

            $table->unique(['exit_id', 'box_id']);
            $table->unique('active_full_box_id');
            $table->index('box_id');
        });

        DB::statement('ALTER TABLE exit_items ADD CONSTRAINT exit_items_weights_valid CHECK (weight_out_kg > 0 AND weight_after_kg >= 0 AND weight_after_kg = weight_before_kg - weight_out_kg)');
        DB::statement("ALTER TABLE exit_items ADD CONSTRAINT exit_items_full_empties_box CHECK (kind <> 'full' OR weight_after_kg = 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_items');
    }
};
