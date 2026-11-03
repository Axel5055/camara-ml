<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tables whose rows can never be deleted.
     *
     * @var list<string>
     */
    private array $noDelete = [
        'boxes', 'box_movements', 'audit_logs', 'entries', 'entry_items',
        'exits', 'exit_items', 'transfers', 'transfer_items', 'incidents', 'inventory_adjustments',
    ];

    /**
     * Tables whose rows can never be updated at all.
     *
     * @var list<string>
     */
    private array $noUpdate = ['box_movements', 'audit_logs', 'entry_items', 'inventory_adjustments'];

    /**
     * Tables where only the listed columns may change after insertion.
     *
     * @var array<string, list<string>>
     */
    private array $mutableColumns = [
        'entries' => ['status', 'updated_at'],
        'exits' => ['status', 'reversed_at', 'reversed_by', 'reversed_reason', 'updated_at'],
        'exit_items' => ['is_active', 'updated_at'],
        'transfers' => ['status', 'received_at', 'received_by', 'cancelled_at', 'cancelled_by', 'cancel_reason', 'updated_at'],
        'transfer_items' => ['received_weight_kg', 'status', 'updated_at'],
        'incidents' => ['status', 'resolved_by', 'resolved_at', 'resolution', 'updated_at'],
    ];

    public function up(): void
    {
        foreach ($this->noDelete as $table) {
            DB::getPdo()->exec("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in {$table} cannot be deleted'");
        }

        foreach ($this->noUpdate as $table) {
            DB::getPdo()->exec("CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in {$table} cannot be updated'");
        }

        foreach ($this->mutableColumns as $table => $allowed) {
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
    }

    public function down(): void
    {
        foreach ($this->noDelete as $table) {
            DB::getPdo()->exec("DROP TRIGGER IF EXISTS {$table}_no_delete");
        }

        foreach ($this->noUpdate as $table) {
            DB::getPdo()->exec("DROP TRIGGER IF EXISTS {$table}_no_update");
        }

        foreach (array_keys($this->mutableColumns) as $table) {
            DB::getPdo()->exec("DROP TRIGGER IF EXISTS {$table}_immutable_columns");
        }
    }
};
