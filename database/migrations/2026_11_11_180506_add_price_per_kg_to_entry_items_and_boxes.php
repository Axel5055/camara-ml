<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The cost of goods is priced per kilo: a group costs boxes × kg per box × price per kg. The price per kg is
 * now stored on the entry groups and on every box; "unit_cost_mxn" stays as the cost of one box
 * (weight × price per kg), so every total, exit snapshot and report keeps working with the same money values.
 * Rows that already existed get the price per kg that reproduces their current box cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entry_items', function (Blueprint $table) {
            $table->decimal('price_per_kg_mxn', 12, 2)->default(0)->after('weight_per_box_kg');
        });

        Schema::table('boxes', function (Blueprint $table) {
            $table->decimal('price_per_kg_mxn', 12, 2)->default(0)->after('current_weight_kg');
        });

        DB::statement('UPDATE entry_items SET price_per_kg_mxn = ROUND(unit_cost_mxn / weight_per_box_kg, 2) WHERE weight_per_box_kg > 0');
        DB::statement('UPDATE boxes SET price_per_kg_mxn = ROUND(unit_cost_mxn / initial_weight_kg, 2) WHERE initial_weight_kg > 0');
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->dropColumn('price_per_kg_mxn');
        });

        Schema::table('entry_items', function (Blueprint $table) {
            $table->dropColumn('price_per_kg_mxn');
        });
    }
};
