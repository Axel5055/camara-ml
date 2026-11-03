<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A box may only be in one standing full exit at a time. Until now that guard stayed on forever for boxes sent
 * by transfer, so a box could never leave the chamber that received it. The new "holds_box" flag says whether
 * an exit still keeps its box out of the stock; it is released when the box comes back (transfer received,
 * found or corrected by an adjustment). "is_active" keeps meaning "the exit was not reversed", so reports and
 * history are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropImmutableColumnsTrigger();

        Schema::table('exit_items', function (Blueprint $table) {
            $table->dropUnique(['active_full_box_id']);
        });
        Schema::table('exit_items', function (Blueprint $table) {
            $table->dropColumn('active_full_box_id');
        });
        Schema::table('exit_items', function (Blueprint $table) {
            $table->boolean('holds_box')->default(true)->after('is_active');
        });

        // Boxes of transfers that were already received are back in stock.
        DB::statement("UPDATE exit_items
            JOIN exits ON exits.id = exit_items.exit_id
            JOIN transfers ON transfers.exit_id = exits.id
            JOIN transfer_items ON transfer_items.transfer_id = transfers.id AND transfer_items.box_id = exit_items.box_id
            SET exit_items.holds_box = 0
            WHERE transfer_items.status = 'received'");

        Schema::table('exit_items', function (Blueprint $table) {
            $table->unsignedBigInteger('active_full_box_id')
                ->nullable()
                ->virtualAs("IF(kind = 'full' AND is_active = 1 AND holds_box = 1, box_id, NULL)")
                ->after('holds_box');
            $table->unique('active_full_box_id');
        });

        $this->createImmutableColumnsTrigger(['is_active', 'holds_box', 'updated_at']);
    }

    public function down(): void
    {
        $this->dropImmutableColumnsTrigger();

        Schema::table('exit_items', function (Blueprint $table) {
            $table->dropUnique(['active_full_box_id']);
        });
        Schema::table('exit_items', function (Blueprint $table) {
            $table->dropColumn('active_full_box_id');
        });
        Schema::table('exit_items', function (Blueprint $table) {
            $table->dropColumn('holds_box');
        });
        Schema::table('exit_items', function (Blueprint $table) {
            $table->unsignedBigInteger('active_full_box_id')
                ->nullable()
                ->virtualAs("IF(kind = 'full' AND is_active = 1, box_id, NULL)")
                ->after('is_active');
            $table->unique('active_full_box_id');
        });

        $this->createImmutableColumnsTrigger(['is_active', 'updated_at']);
    }

    private function dropImmutableColumnsTrigger(): void
    {
        DB::getPdo()->exec('DROP TRIGGER IF EXISTS exit_items_immutable_columns');
    }

    /**
     * @param  list<string>  $allowed
     */
    private function createImmutableColumnsTrigger(array $allowed): void
    {
        $columns = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'exit_items')
            ->select('column_name as name')
            ->pluck('name')
            ->map(fn (string $name): string => strtolower($name))
            ->reject(fn (string $name): bool => in_array($name, [...$allowed, 'active_full_box_id'], true))
            ->map(fn (string $name): string => "NOT (OLD.`{$name}` <=> NEW.`{$name}`)")
            ->implode(' OR ');

        DB::getPdo()->exec("CREATE TRIGGER exit_items_immutable_columns BEFORE UPDATE ON exit_items FOR EACH ROW BEGIN IF {$columns} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable columns of exit_items cannot be changed'; END IF; END");
    }
};
