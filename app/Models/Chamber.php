<?php

namespace App\Models;

use Database\Factories\ChamberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $branch_id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property-read Branch $branch
 */
#[Fillable(['branch_id', 'code', 'name', 'is_active'])]
class Chamber extends Model
{
    /** @use HasFactory<ChamberFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Chambers the given user may work with: every one for administrators, only the assigned ones otherwise.
     *
     * @param  Builder<Chamber>  $query
     */
    public function scopeAccessibleBy(Builder $query, User $user): void
    {
        if ($user->seesAllChambers()) {
            return;
        }

        $query->where('branch_id', $user->branch_id)
            ->whereIn('id', $user->chambers()->select('chambers.id'));
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Boxes currently stored in this chamber.
     *
     * @return HasMany<Box, $this>
     */
    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class, 'current_chamber_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
