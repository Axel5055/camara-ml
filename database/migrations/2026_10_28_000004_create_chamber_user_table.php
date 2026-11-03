<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chamber_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('chamber_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'chamber_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chamber_user');
    }
};
