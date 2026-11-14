<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Boxes added by a super administrator straight to a chamber have no entry behind them.
     */
    public function up(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->unsignedBigInteger('entry_item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->unsignedBigInteger('entry_item_id')->nullable(false)->change();
        });
    }
};
