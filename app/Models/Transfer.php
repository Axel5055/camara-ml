<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property int $exit_id
 * @property int $origin_chamber_id
 * @property int $dest_chamber_id
 * @property TransferStatus $status
 * @property Carbon $dispatched_at
 * @property Carbon|null $received_at
 * @property int|null $received_by
 * @property string|null $receive_notes
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancel_reason
 * @property-read StockExit $exit
 */
#[Unguarded]
class Transfer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<Chamber, $this>
     */
    public function originChamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class, 'origin_chamber_id');
    }

    /**
     * @return BelongsTo<Chamber, $this>
     */
    public function destChamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class, 'dest_chamber_id');
    }

    /**
     * @return HasMany<TransferItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }

    /**
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
