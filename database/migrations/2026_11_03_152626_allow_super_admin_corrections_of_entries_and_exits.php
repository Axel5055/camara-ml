<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a super administrator correct entries and exits registered by mistake. The records stay protected by
 * the database: only the columns a correction needs can change (header data and totals; the entry groups'
 * boxes, weight and cost). Everything else, such as references, chambers, users and tokens, stays immutable.
 * Boxes created by mistake are never deleted: they are written off and marked with "voided_at".
 */
return new class extends Migration
{
    /**
     * Columns that may change per table after the correction feature (everything else is immutable).
     *
     * @var array<string, list<string>>
     */
    private array $mutableColumns = [
        'entries' => ['status', 'updated_at', 'occurred_at', 'notes', 'total_boxes', 'total_weight_kg', 'total_cost_mxn'],
        'entry_items' => ['boxes_count', 'weight_per_box_kg', 'unit_cost_mxn', 'total_weight_kg', 'total_cost_mxn', 'updated_at'],
        'exits' => [
            'status', 'reversed_at', 'reversed_by', 'reversed_reason', 'updated_at',
            'requester_name', 'occurred_at', 'notes', 'dest_branch_id', 'total_boxes', 'total_weight_kg', 'total_cost_mxn',
        ],
    ];

    /**
     * What each table allowed before this migration.
     *
     * @var array<string, list<string>|null> null means "no update at all"
     */
    private array $previousMutableColumns = [
        'entries' => ['status', 'updated_at'],
        'entry_items' => null,
        'exits' => ['status', 'reversed_at', 'reversed_by', 'reversed_reason', 'updated_at'],
    ];

    public function up(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->dateTime('voided_at')->nullable()->after('entered_at');
        });

        DB::getPdo()->exec('DROP TRIGGER IF EXISTS entry_items_no_update');

        foreach ($this->mutableColumns as $table => $allowed) {
            $this->recreateImmutableColumnsTrigger($table, $allowed);
        }
    }

    public function down(): void
    {
        foreach ($this->previousMutableColumns as $table => $allowed) {
            if ($allowed === null) {
                DB::getPdo()->exec("DROP TRIGGER IF EXISTS {$table}_immutable_columns");
                DB::getPdo()->exec("CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in {$table} cannot be updated'");

                continue;
            }

            $this->recreateImmutableColumnsTrigger($table, $allowed);
        }

        Schema::table('boxes', function (Blueprint $table) {
            $table->dropColumn('voided_at');
        });
    }

    /**
     * @param  list<string>  $allowed
     */
    private function recreateImmutableColumnsTrigger(string $table, array $allowed): void
    {
        DB::getPdo()->exec("DROP TRIGGER IF EXISTS {$table}_immutable_columns");

        $columns = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->select('column_name as name')
            ->pluck('name')
            ->map(fn (string $name): string => strtolower($name))
            ->reject(fn (string $name): bool => in_array($name, $allowed, true))
            ->map(fn (string $name): string => "NOT (OLD.`{$name}` <=> NEW.`{$name}`)")
            ->implode(' OR ');

        DB::getPdo()->exec("CREATE TRIGGER {$table}_immutable_columns BEFORE UPDATE ON {$table} FOR EACH ROW BEGIN IF {$columns} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable columns of {$table} cannot be changed'; END IF; END");
    }
};
