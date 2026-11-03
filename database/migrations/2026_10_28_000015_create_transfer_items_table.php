<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->restrictOnDelete();
            $table->foreignId('box_id')->constrained()->restrictOnDelete();
            $table->decimal('expected_weight_kg', 10, 3);
            $table->decimal('received_weight_kg', 10, 3)->nullable();
            $table->enum('status', ['expected', 'received', 'missing'])->default('expected');
            $table->timestamps();

            $table->unique(['transfer_id', 'box_id']);
            $table->index('box_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_items');
    }
};
