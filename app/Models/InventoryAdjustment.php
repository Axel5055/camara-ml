<?php

namespace App\Models;

use App\Enums\AdjustmentType;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property int|null $chamber_id
 * @property int $box_id
 * @property AdjustmentType $type
 * @property string $weight_before_kg
 * @property string $weight_after_kg
 * @property string $status_before
 * @property string $status_after
 * @property string $reason
 * @property int $requested_by
 * @property int $authorized_by
 * @property Carbon $occurred_at
 */
#[Unguarded]
class InventoryAdjustment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AdjustmentType::class,
            'weight_before_kg' => 'decimal:3',
            'weight_after_kg' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Box, $this>
     */
    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }
}
