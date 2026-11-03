<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->restrictOnDelete();
            $table->foreignId('box_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('type', ['missing', 'unexpected', 'weight_difference', 'damaged', 'other']);
            $table->decimal('expected_weight_kg', 10, 3)->nullable();
            $table->decimal('received_weight_kg', 10, 3)->nullable();
            $table->text('description');
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index('transfer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
