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
 * @property Carbon|null $validated_at
 * @property int|null $validated_by
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
            'validated_at' => 'datetime',
            'status' => ExitRequestStatus::class,
        ];
    }

    public function isPending(): bool
    {
        return $this->status === ExitRequestStatus::Pending;
    }

    /**
     * Authorized and registered as an exit, but the warehouse user has not validated it yet: its exit still
     * belongs to "Salidas por solicitud".
     */
    public function awaitsValidation(): bool
    {
        return $this->status === ExitRequestStatus::Approved && $this->exit_id !== null && $this->validated_at === null;
    }

    public function reference(): string
    {
        return 'SOLS-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Requests the user may look at: administrators see all of them, managers none (they only follow the direct exits), warehouse users those of their
     * chambers and requesters only the ones they made themselves, not those of colleagues of their branch.
     *
     * @param  Builder<ExitRequest>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role === UserRole::Manager) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($user->seesAllChambers()) {
            return;
        }

        if ($user->role === UserRole::Requester) {
            $query->where('user_id', $user->id);

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
     * @return BelongsTo<User, $this>
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
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
