<?php

namespace App\Models;

use App\Enums\ExitItemKind;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $exit_id
 * @property int $box_id
 * @property ExitItemKind $kind
 * @property string $weight_before_kg
 * @property string $weight_out_kg
 * @property string $weight_after_kg
 * @property string $unit_cost_snapshot_mxn
 * @property string $cost_out_mxn
 * @property bool $is_active
 * @property bool $holds_box
 */
#[Unguarded]
class ExitItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ExitItemKind::class,
            'weight_before_kg' => 'decimal:3',
            'weight_out_kg' => 'decimal:3',
            'weight_after_kg' => 'decimal:3',
            'unit_cost_snapshot_mxn' => 'decimal:2',
            'cost_out_mxn' => 'decimal:2',
            'is_active' => 'boolean',
            'holds_box' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<StockExit, $this>
     */
    public function exit(): BelongsTo
    {
        return $this->belongsTo(StockExit::class, 'exit_id');
    }

    /**
     * @return BelongsTo<Box, $this>
     */
    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }
}
