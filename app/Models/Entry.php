<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property int $chamber_id
 * @property int $user_id
 * @property Carbon $occurred_at
 * @property int $total_boxes
 * @property string $total_weight_kg
 * @property string $total_cost_mxn
 * @property string|null $notes
 * @property DocumentStatus $status
 * @property string $client_token
 */
#[Unguarded]
class Entry extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'total_weight_kg' => 'decimal:3',
            'total_cost_mxn' => 'decimal:2',
            'status' => DocumentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Chamber, $this>
     */
    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<EntryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(EntryItem::class);
    }
}
