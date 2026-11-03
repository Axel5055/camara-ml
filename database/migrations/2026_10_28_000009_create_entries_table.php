<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('chamber_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->unsignedInteger('total_boxes');
            $table->decimal('total_weight_kg', 14, 3);
            $table->decimal('total_cost_usd', 16, 2);
            $table->text('notes')->nullable();
            $table->enum('status', ['confirmed', 'reversed'])->default('confirmed');
            $table->string('client_token', 64)->unique();
            $table->timestamps();

            $table->index(['chamber_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
