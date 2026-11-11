<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chamber of the destination branch that receives a direct delivery. It is informational only: the exit is
 * confirmed on save and never creates a transfer (those keep using "dest_chamber_id").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exits', function (Blueprint $table) {
            $table->foreignId('delivery_chamber_id')->nullable()->after('dest_chamber_id')->constrained('chambers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_chamber_id');
        });
    }
};
