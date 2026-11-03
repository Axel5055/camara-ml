<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exits', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('requester_name');
            $table->foreignId('origin_chamber_id')->constrained('chambers')->restrictOnDelete();
            $table->foreignId('dest_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('dest_chamber_id')->nullable()->constrained('chambers')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->unsignedInteger('total_boxes');
            $table->decimal('total_weight_kg', 14, 3);
            $table->decimal('total_cost_usd', 16, 2);
            $table->text('notes')->nullable();
            $table->enum('status', ['confirmed', 'reversed'])->default('confirmed');
            $table->dateTime('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reversed_reason')->nullable();
            $table->string('client_token', 64)->unique();
            $table->timestamps();

            $table->index(['origin_chamber_id', 'occurred_at']);
            $table->index(['dest_branch_id', 'occurred_at']);
            $table->index('requester_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exits');
    }
};
