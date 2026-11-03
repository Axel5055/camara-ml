<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chamber_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('min_boxes')->nullable();
            $table->decimal('min_weight_kg', 10, 3)->nullable();
            $table->timestamps();

            $table->unique(['chamber_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_thresholds');
    }
};
