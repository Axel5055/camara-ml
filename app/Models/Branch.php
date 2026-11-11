<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $address
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'address', 'is_active'])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Branches a manager may see: the assigned ones and those holding an assigned chamber. Everyone else sees
     * them all (access to the catalog is decided by the gates).
     *
     * @param  Builder<Branch>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role !== UserRole::Manager) {
            return;
        }

        $query->where(fn (Builder $scope) => $scope
            ->whereIn('id', $user->managedBranches()->select('branches.id'))
            ->orWhereIn('id', Chamber::query()->accessibleBy($user)->select('branch_id')));
    }

    /**
     * @return HasMany<Chamber, $this>
     */
    public function chambers(): HasMany
    {
        return $this->hasMany(Chamber::class);
    }
}
