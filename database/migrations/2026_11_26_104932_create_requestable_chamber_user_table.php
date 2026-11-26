<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chambers a warehouse user may ask for product, apart from the ones they operate (chamber_user).
     */
    public function up(): void
    {
        Schema::create('requestable_chamber_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('chamber_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'chamber_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requestable_chamber_user');
    }
};
