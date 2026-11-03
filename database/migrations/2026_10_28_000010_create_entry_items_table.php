<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('boxes_count');
            $table->decimal('weight_per_box_kg', 10, 3);
            $table->decimal('unit_cost_usd', 14, 2);
            $table->decimal('total_weight_kg', 14, 3);
            $table->decimal('total_cost_usd', 16, 2);
            $table->timestamps();

            $table->index('product_id');
        });

        DB::statement('ALTER TABLE entry_items ADD CONSTRAINT entry_items_positive CHECK (boxes_count > 0 AND weight_per_box_kg > 0 AND unit_cost_usd >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_items');
    }
};
