<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a super administrator correct a transfer. While it is in transit the destination chamber, the dispatch date
 * and the boxes can change (a box taken out is marked with "removed_at" and never deleted); requester and notes live
 * in the exit. Everything else stays immutable in the database.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $mutableColumns = [
        'transfers' => ['status', 'received_at', 'received_by', 'cancelled_at', 'cancelled_by', 'cancel_reason', 'updated_at', 'receive_notes', 'dest_chamber_id', 'dispatched_at'],
        'transfer_items' => ['received_weight_kg', 'status', 'updated_at', 'removed_at'],
        'exits' => [
            'status', 'reversed_at', 'reversed_by', 'reversed_reason', 'updated_at',
            'requester_name', 'occurred_at', 'notes', 'dest_branch_id', 'total_boxes', 'total_weight_kg', 'total_cost_mxn', 'dest_chamber_id',
        ],
    ];

    /**
     * @var array<string, list<string>>
     */
    private array $previousMutableColumns = [
        'transfers' => ['status', 'received_at', 'received_by', 'cancelled_at', 'cancelled_by', 'cancel_reason', 'updated_at', 'receive_notes'],
        'transfer_items' => ['received_weight_kg', 'status', 'updated_at'],
        'exits' => [
            'status', 'reversed_at', 'reversed_by', 'reversed_reason', 'updated_at',
            'requester_name', 'occurred_at', 'notes', 'dest_branch_id', 'total_boxes', 'total_weight_kg', 'total_cost_mxn',
        ],
    ];

    public function up(): void
    {
        Schema::table('transfer_items', function (Blueprint $table) {
            $table->dateTime('removed_at')->nullable()->after('status');
        });

        foreach ($this->mutableColumns as $table => $allowed) {
            $this->recreateImmutableColumnsTrigger($table, $allowed);
        }
    }

    public function down(): void
    {
        foreach ($this->previousMutableColumns as $table => $allowed) {
            $this->recreateImmutableColumnsTrigger($table, $allowed);
        }

        Schema::table('transfer_items', function (Blueprint $table) {
            $table->dropColumn('removed_at');
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
            ->reject(fn (string $name): bool => $table === 'exit_items' && $name === 'active_full_box_id')
            ->map(fn (string $name): string => "NOT (OLD.`{$name}` <=> NEW.`{$name}`)")
            ->implode(' OR ');

        DB::getPdo()->exec("CREATE TRIGGER {$table}_immutable_columns BEFORE UPDATE ON {$table} FOR EACH ROW BEGIN IF {$columns} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable columns of {$table} cannot be changed'; END IF; END");
    }
};
