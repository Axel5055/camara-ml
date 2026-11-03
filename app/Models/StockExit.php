<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property string $requester_name
 * @property int $origin_chamber_id
 * @property int $dest_branch_id
 * @property int|null $dest_chamber_id
 * @property int $user_id
 * @property Carbon $occurred_at
 * @property int $total_boxes
 * @property string $total_weight_kg
 * @property string $total_cost_mxn
 * @property string|null $notes
 * @property DocumentStatus $status
 * @property Carbon|null $reversed_at
 * @property int|null $reversed_by
 * @property string|null $reversed_reason
 * @property string $client_token
 */
#[Table('exits')]
#[Unguarded]
class StockExit extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'reversed_at' => 'datetime',
            'total_weight_kg' => 'decimal:3',
            'total_cost_mxn' => 'decimal:2',
            'status' => DocumentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Chamber, $this>
     */
    public function originChamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class, 'origin_chamber_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function destBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'dest_branch_id');
    }

    /**
     * @return BelongsTo<Chamber, $this>
     */
    public function destChamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class, 'dest_chamber_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ExitItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ExitItem::class, 'exit_id');
    }

    /**
     * @return HasOne<Transfer, $this>
     */
    public function transfer(): HasOne
    {
        return $this->hasOne(Transfer::class, 'exit_id');
    }
}
