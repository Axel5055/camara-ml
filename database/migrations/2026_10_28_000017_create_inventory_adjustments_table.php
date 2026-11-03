<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('chamber_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('box_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['weight_correction', 'found', 'lost', 'damaged', 'status_fix']);
            $table->decimal('weight_before_kg', 10, 3);
            $table->decimal('weight_after_kg', 10, 3);
            $table->string('status_before', 20);
            $table->string('status_after', 20);
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['box_id', 'occurred_at']);
            $table->index(['chamber_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
