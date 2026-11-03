<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('exit_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('origin_chamber_id')->constrained('chambers')->restrictOnDelete();
            $table->foreignId('dest_chamber_id')->constrained('chambers')->restrictOnDelete();
            $table->enum('status', ['pending', 'in_transit', 'received', 'cancelled'])->default('in_transit');
            $table->dateTime('dispatched_at');
            $table->dateTime('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['dest_chamber_id', 'status']);
            $table->index(['origin_chamber_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
