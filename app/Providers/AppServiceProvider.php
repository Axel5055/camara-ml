<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Chamber;
use App\Models\Entry;
use App\Models\Product;
use App\Models\StockExit;
use App\Models\Transfer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Inactive users can do nothing; administrators can do everything; warehouse users fall through to the
     * policies, which scope them to their assigned chambers. Costs are visible to administrators only.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            if (! $user->is_active) {
                return false;
            }

            return match ($user->role) {
                // Receiving and cancelling depend on the state of the transfer, so the policy decides them.
                UserRole::SuperAdmin => in_array($ability, ['receive', 'cancel'], true) ? null : true,
                UserRole::Admin => $this->readOnlyAdminMay($ability, $arguments) ? true : null,
                UserRole::Warehouse => null,
            };
        });

        // Super administrators pass every gate through Gate::before; these decide for the other roles.
        Gate::define('view-costs', fn (User $user): bool => $user->role === UserRole::Admin);
        Gate::define('view-catalogs', fn (User $user): bool => $user->role === UserRole::Admin);
        Gate::define('operate-inventory', fn (User $user): bool => $user->role === UserRole::Warehouse);
    }

    /**
     * The read-only administrator can only open (view / viewAny) these records; every other ability, such as
     * creating or changing anything, is left to the policies, which deny it.
     *
     * @param  array<int, mixed>  $arguments
     */
    private function readOnlyAdminMay(string $ability, array $arguments): bool
    {
        if (! in_array($ability, ['view', 'viewAny'], true)) {
            return false;
        }

        $subject = $arguments[0] ?? null;

        return in_array(is_object($subject) ? $subject::class : $subject, [
            Entry::class, StockExit::class, Transfer::class, Box::class, Chamber::class, Branch::class, Category::class, Product::class,
        ], true);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
