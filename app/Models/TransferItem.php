<?php

namespace App\Models;

use App\Enums\TransferItemStatus;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transfer_id
 * @property int $box_id
 * @property string $expected_weight_kg
 * @property string|null $received_weight_kg
 * @property TransferItemStatus $status
 */
#[Unguarded]
class TransferItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_weight_kg' => 'decimal:3',
            'received_weight_kg' => 'decimal:3',
            'status' => TransferItemStatus::class,
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
