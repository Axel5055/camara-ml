<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('box_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('box_id')->constrained()->restrictOnDelete();
            $table->enum('type', [
                'entry', 'exit', 'transfer_out', 'transfer_in', 'partial_withdrawal',
                'adjustment', 'reversal', 'transfer_cancel',
            ]);
            $table->foreignId('from_chamber_id')->nullable()->constrained('chambers')->restrictOnDelete();
            $table->foreignId('to_chamber_id')->nullable()->constrained('chambers')->restrictOnDelete();
            $table->foreignId('to_branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->decimal('weight_before_kg', 10, 3);
            $table->decimal('weight_after_kg', 10, 3);
            $table->string('status_before', 20)->nullable();
            $table->string('status_after', 20);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->foreignId('entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('exit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('transfer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('adjustment_id')->nullable()->constrained('inventory_adjustments')->restrictOnDelete();
            $table->foreignId('reverses_movement_id')->nullable()->constrained('box_movements')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['box_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('box_movements');
    }
};
