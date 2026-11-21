<?php

namespace App\Models;

use App\Enums\ExitRequestStatus;
use App\Enums\UserRole;
use Database\Factories\ExitRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Product a branch without chambers asks a chamber for. It only becomes a real exit (boxes leaving the chamber)
 * when the warehouse user of that chamber, or a super administrator, authorizes it.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $chamber_id
 * @property int $user_id
 * @property int $total_boxes_requested
 * @property int|null $total_boxes_approved
 * @property string|null $notes
 * @property ExitRequestStatus $status
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_notes
 * @property int|null $exit_id
 * @property string $client_token
 * @property Carbon|null $created_at
 * @property-read Branch $branch
 * @property-read Chamber $chamber
 * @property-read User $user
 */
#[Unguarded]
class ExitRequest extends Model
{
    /** @use HasFactory<ExitRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'status' => ExitRequestStatus::class,
        ];
    }

    public function isPending(): bool
    {
        return $this->status === ExitRequestStatus::Pending;
    }

    public function reference(): string
    {
        return 'SOLS-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Requests the user may look at: administrators see all of them, managers and warehouse users those of their
     * chambers and requesters those of their own branch.
     *
     * @param  Builder<ExitRequest>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesAllChambers()) {
            return;
        }

        if ($user->role === UserRole::Requester) {
            $query->where('branch_id', $user->branch_id);

            return;
        }

        $query->whereIn('chamber_id', Chamber::query()->accessibleBy($user)->select('chambers.id'));
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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
     * @return BelongsTo<StockExit, $this>
     */
    public function exit(): BelongsTo
    {
        return $this->belongsTo(StockExit::class, 'exit_id');
    }

    /**
     * @return HasMany<ExitRequestItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ExitRequestItem::class);
    }
}
