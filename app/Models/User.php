<?php

namespace App\Models;

use App\Enums\UserRole;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonInterface|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property UserRole $role
 * @property bool $is_active
 * @property int|null $branch_id
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_active && $this->role === UserRole::SuperAdmin;
    }

    /**
     * Super administrators and administrators look at the data of every chamber (the latter read-only).
     */
    public function seesAllChambers(): bool
    {
        return $this->is_active && in_array($this->role, [UserRole::SuperAdmin, UserRole::Admin], true);
    }

    /**
     * Managers read like administrators, but only within the branches and chambers assigned to them.
     */
    public function isManager(): bool
    {
        return $this->is_active && $this->role === UserRole::Manager;
    }

    /**
     * Whether the user may look at the records of a chamber (not operate it): everyone sees what they may
     * operate, administrators see all, managers see what is assigned to them.
     */
    public function canViewChamber(Chamber $chamber): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->seesAllChambers()) {
            return true;
        }

        if ($this->role === UserRole::Manager) {
            return Chamber::query()->accessibleBy($this)->whereKey($chamber->getKey())->exists();
        }

        return $this->canAccessChamber($chamber);
    }

    /**
     * Super administrators correct or cancel the entries of any chamber; managers only those of their own
     * branches and chambers.
     */
    public function canCorrectEntriesOf(Chamber $chamber): bool
    {
        return $this->isSuperAdmin() || ($this->isManager() && $this->canViewChamber($chamber));
    }

    /**
     * Correcting or cancelling exits and transfers: super administrators and administrators anywhere, managers
     * only when every chamber involved (origin and destination) is within their scope.
     */
    public function canCorrectRecordsOf(?Chamber ...$chambers): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (in_array($this->role, [UserRole::SuperAdmin, UserRole::Admin], true)) {
            return true;
        }

        return $this->role === UserRole::Manager
            && collect($chambers)->filter()->every(fn (Chamber $chamber): bool => $this->canViewChamber($chamber));
    }

    /**
     * Only super administrators operate every chamber; warehouse users only the chambers assigned to them within
     * their own branch. The read-only administrator and managers operate none.
     */
    public function canAccessChamber(Chamber $chamber): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->role === UserRole::SuperAdmin) {
            return true;
        }

        if ($this->role === UserRole::Manager) {
            return false;
        }

        return $this->branch_id === $chamber->branch_id
            && $this->chambers()->whereKey($chamber->getKey())->exists();
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Branches a manager is assigned to (all their chambers are visible).
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function managedBranches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<Chamber, $this>
     */
    public function chambers(): BelongsToMany
    {
        return $this->belongsToMany(Chamber::class)->withTimestamps();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
