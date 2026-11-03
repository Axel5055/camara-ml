<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Money columns to rename, by table.
     *
     * @var array<string, array<string, string>>
     */
    private array $columns = [
        'entries' => ['total_cost_usd' => 'total_cost_mxn'],
        'entry_items' => ['unit_cost_usd' => 'unit_cost_mxn', 'total_cost_usd' => 'total_cost_mxn'],
        'boxes' => ['unit_cost_usd' => 'unit_cost_mxn'],
        'exits' => ['total_cost_usd' => 'total_cost_mxn'],
        'exit_items' => ['unit_cost_snapshot_usd' => 'unit_cost_snapshot_mxn', 'cost_out_usd' => 'cost_out_mxn'],
    ];

    /**
     * Tables whose only-some-columns-may-change trigger lists every other column and must be rebuilt.
     *
     * @var array<string, list<string>>
     */
    private array $mutableColumns = [
        'entries' => ['status', 'updated_at'],
        'exits' => ['status', 'reversed_at', 'reversed_by', 'reversed_reason', 'updated_at'],
        'exit_items' => ['is_active', 'updated_at'],
    ];

    public function up(): void
    {
        $this->rename($this->columns, 'unit_cost_mxn');
    }

    public function down(): void
    {
        $this->rename(array_map(fn (array $map): array => array_flip($map), $this->columns), 'unit_cost_usd');
    }

    /**
     * @param  array<string, array<string, string>>  $map  table => [old column => new column]
     */
    private function rename(array $map, string $costColumn): void
    {
        foreach (array_keys($this->mutableColumns) as $table) {
            DB::getPdo()->exec("DROP TRIGGER IF EXISTS {$table}_immutable_columns");
        }

        DB::getPdo()->exec('ALTER TABLE entry_items DROP CHECK entry_items_positive');
        DB::getPdo()->exec('ALTER TABLE boxes DROP CHECK boxes_weights_valid');

        foreach ($map as $table => $renames) {
            foreach ($renames as $old => $new) {
                DB::getPdo()->exec("ALTER TABLE {$table} RENAME COLUMN {$old} TO {$new}");
            }
        }

        DB::getPdo()->exec("ALTER TABLE entry_items ADD CONSTRAINT entry_items_positive CHECK (boxes_count > 0 AND weight_per_box_kg > 0 AND {$costColumn} >= 0)");
        DB::getPdo()->exec("ALTER TABLE boxes ADD CONSTRAINT boxes_weights_valid CHECK (initial_weight_kg > 0 AND current_weight_kg >= 0 AND current_weight_kg <= initial_weight_kg AND {$costColumn} >= 0)");

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
};
