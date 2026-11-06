<?php

namespace App\Models;

use App\Enums\TransferItemStatus;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $transfer_id
 * @property int $box_id
 * @property string $expected_weight_kg
 * @property string|null $received_weight_kg
 * @property TransferItemStatus $status
 * @property Carbon|null $removed_at
 */
#[Unguarded]
class TransferItem extends Model
{
    /**
     * Boxes taken out of a transfer by a correction are kept for the record but are no longer part of it.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('present', fn (Builder $query) => $query->whereNull($query->getModel()->getTable().'.removed_at'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_weight_kg' => 'decimal:3',
            'received_weight_kg' => 'decimal:3',
            'status' => TransferItemStatus::class,
            'removed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Transfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * @return BelongsTo<Box, $this>
     */
    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }
}
