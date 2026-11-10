<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chamber_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->unsignedInteger('total_boxes');
            $table->decimal('total_weight_kg', 14, 3);
            $table->decimal('total_cost_mxn', 16, 2);
            $table->text('notes')->nullable();
            $table->json('groups');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('client_token', 64)->unique();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['chamber_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_requests');
    }
};
