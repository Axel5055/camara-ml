<?php

namespace App\Models;

use App\Enums\EntryRequestStatus;
use Database\Factories\EntryRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An entry a warehouse user wants to register; it only becomes a real entry (boxes, barcodes, stock) when an
 * administrator or super administrator authorizes it.
 *
 * @property int $id
 * @property int $chamber_id
 * @property int $user_id
 * @property Carbon $occurred_at
 * @property int $total_boxes
 * @property string $total_weight_kg
 * @property string $total_cost_mxn
 * @property string|null $notes
 * @property list<array{product_id: int, boxes_count: int, weight_per_box_kg: string, price_per_kg_mxn: string, unit_cost_mxn: string, manual_barcodes: list<string>, manual_barcode_origin: string|null}> $groups
 * @property EntryRequestStatus $status
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_notes
 * @property int|null $entry_id
 * @property Carbon|null $ticket_prompted_at
 * @property string $client_token
 */
#[Unguarded]
class EntryRequest extends Model
{
    /** @use HasFactory<EntryRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'decided_at' => 'datetime',
            'ticket_prompted_at' => 'datetime',
            'total_weight_kg' => 'decimal:3',
            'total_cost_mxn' => 'decimal:2',
            'groups' => 'array',
            'status' => EntryRequestStatus::class,
        ];
    }

    public function isPending(): bool
    {
        return $this->status === EntryRequestStatus::Pending;
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
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}
