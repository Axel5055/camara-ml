<?php

namespace App\Models;

use App\Enums\BoxStatus;
use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only ledger row. The database rejects updates and deletes.
 *
 * @property int $id
 * @property int $box_id
 * @property MovementType $type
 * @property int|null $from_chamber_id
 * @property int|null $to_chamber_id
 * @property int|null $to_branch_id
 * @property string $weight_before_kg
 * @property string $weight_after_kg
 * @property string|null $status_before
 * @property BoxStatus $status_after
 * @property int $user_id
 * @property Carbon $occurred_at
 * @property int|null $entry_id
 * @property int|null $exit_id
 * @property int|null $transfer_id
 * @property int|null $adjustment_id
 * @property int|null $reverses_movement_id
 * @property string|null $notes
 */
#[Unguarded]
class BoxMovement extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'status_after' => BoxStatus::class,
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
