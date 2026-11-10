<?php

use App\Actions\Users\AssignChambers;
use App\Exceptions\InventoryException;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\Entry;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\StockExit;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Inventory\RegisterEntry;
use App\Services\Inventory\RegisterExit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * @return array<string, mixed>
 */
function accessWorld(): array
{
    $centro = Branch::factory()->create();
    $norte = Branch::factory()->create();
    $c1 = Chamber::factory()->for($centro)->create();
    $c2 = Chamber::factory()->for($centro)->create();
    $n1 = Chamber::factory()->for($norte)->create();

    $admin = User::factory()->superAdmin()->create();
    $centroUser = User::factory()->warehouse($centro)->create();
    $centroUser->chambers()->attach($c1);
    $norteUser = User::factory()->warehouse($norte)->create();
    $norteUser->chambers()->attach($n1);

    $product = Product::factory()->create();
    $entry = app(RegisterEntry::class)->handle($centroUser, $c1, [
        ['product_id' => $product->id, 'boxes_count' => 2, 'weight_per_box_kg' => '10', 'unit_cost_mxn' => '100'],
    ]);
    $boxes = Box::query()->orderBy('id')->get();
    $exit = app(RegisterExit::class)->handle($centroUser, $c1, $norte, $n1, 'Norte', [$boxes[0]->id]);

    return compact('centro', 'norte', 'c1', 'c2', 'n1', 'admin', 'centroUser', 'norteUser', 'entry', 'boxes', 'exit');
}

test('public registration is disabled', function () {
    expect(Route::has('register'))->toBeFalse();

    $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertStatus(404);

    $this->assertGuest();
});

test('deactivated users cannot sign in', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->assertGuest();
});

test('a user deactivated during a session is signed out on the next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('the admin middleware only lets administrators through', function () {
    Route::middleware(['web', 'auth', 'active', 'super-admin'])->get('/_admin-only', fn () => 'ok');

    $this->get('/_admin-only')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->warehouse()->create())->get('/_admin-only')->assertForbidden();
    $this->actingAs(User::factory()->superAdmin()->create())->get('/_admin-only')->assertOk();
});

test('administrators may do everything and see costs', function () {
    $w = accessWorld();

    expect($w['admin']->can('create', Branch::class))->toBeTrue()
        ->and($w['admin']->can('update', $w['c1']))->toBeTrue()
        ->and($w['admin']->can('viewAny', User::class))->toBeTrue()
        ->and($w['admin']->can('viewAny', AuditLog::class))->toBeTrue()
        ->and($w['admin']->can('viewAny', InventoryAdjustment::class))->toBeTrue()
        ->and($w['admin']->can('reverse', $w['exit']))->toBeTrue()
        ->and($w['admin']->can('view', $w['boxes'][0]))->toBeTrue()
        ->and(Gate::forUser($w['admin'])->allows('view-costs'))->toBeTrue();
});

test('warehouse users cannot administer, adjust, audit or see costs', function () {
    $w = accessWorld();
    $user = $w['centroUser'];

    expect($user->can('create', Branch::class))->toBeFalse()
        ->and($user->can('update', Product::class))->toBeFalse()
        ->and($user->can('update', $w['c1']))->toBeFalse()
        ->and($user->can('viewAny', User::class))->toBeFalse()
        ->and($user->can('create', User::class))->toBeFalse()
        ->and($user->can('viewAny', AuditLog::class))->toBeFalse()
        ->and($user->can('viewAny', InventoryAdjustment::class))->toBeFalse()
        ->and($user->can('reverse', $w['exit']))->toBeFalse()
        ->and(Gate::forUser($user)->allows('view-costs'))->toBeFalse();
});

test('warehouse users only reach data of their own chambers', function () {
    $w = accessWorld();
    $centro = $w['centroUser'];
    $norte = $w['norteUser'];
    $transfer = Transfer::query()->firstOrFail();

    expect($centro->can('view', $w['c1']))->toBeTrue()
        ->and($centro->can('view', $w['c2']))->toBeFalse()
        ->and($centro->can('view', $w['n1']))->toBeFalse()
        ->and($centro->can('view', $w['entry']))->toBeTrue()
        ->and($norte->can('view', $w['entry']))->toBeFalse()
        ->and($centro->can('view', $w['boxes'][1]))->toBeTrue()
        ->and($norte->can('view', $w['boxes'][1]))->toBeFalse()
        ->and($centro->can('view', $w['exit']))->toBeTrue()
        ->and($norte->can('view', $w['exit']))->toBeTrue()
        ->and($centro->can('create', [Entry::class, $w['c1']]))->toBeTrue()
        ->and($centro->can('create', [Entry::class, $w['c2']]))->toBeFalse()
        ->and($norte->can('create', [StockExit::class, $w['c1']]))->toBeFalse()
        ->and($centro->can('view', $transfer))->toBeTrue();
});

test('only the destination can receive a transfer and only a super administrator can cancel it', function () {
    $w = accessWorld();
    $transfer = Transfer::query()->firstOrFail();

    expect($w['norteUser']->can('receive', $transfer))->toBeTrue()
        ->and($w['centroUser']->can('receive', $transfer))->toBeFalse()
        ->and($w['centroUser']->can('cancel', $transfer))->toBeFalse()
        ->and($w['norteUser']->can('cancel', $transfer))->toBeFalse()
        ->and($w['admin']->can('cancel', $transfer))->toBeTrue();
});

test('inactive administrators lose every permission', function () {
    $w = accessWorld();
    $w['admin']->forceFill(['is_active' => false])->save();

    expect($w['admin']->fresh()->can('create', Branch::class))->toBeFalse()
        ->and(Gate::forUser($w['admin']->fresh())->allows('view-costs'))->toBeFalse();
});

test('the chamber scope limits warehouse users to their assigned chambers', function () {
    $w = accessWorld();

    expect(Chamber::query()->accessibleBy($w['centroUser'])->pluck('id')->all())->toBe([$w['c1']->id])
        ->and(Chamber::query()->accessibleBy($w['admin'])->count())->toBe(3);
});

test('administrators assign chambers only inside the user branch', function () {
    $w = accessWorld();
    $assign = app(AssignChambers::class);

    $assign->handle($w['admin'], $w['centroUser'], [$w['c1']->id, $w['c2']->id]);
    expect($w['centroUser']->chambers()->count())->toBe(2);

    expect(fn () => $assign->handle($w['admin'], $w['centroUser'], [$w['n1']->id]))->toThrow(InventoryException::class)
        ->and(fn () => $assign->handle($w['admin'], $w['centroUser'], [$w['c1']->id, 999999]))->toThrow(InventoryException::class)
        ->and(fn () => $assign->handle($w['centroUser'], $w['centroUser'], [$w['c1']->id]))->toThrow(AuthorizationException::class)
        ->and(fn () => $assign->handle($w['admin'], $w['admin'], [$w['c1']->id]))->toThrow(InventoryException::class);

    expect($w['centroUser']->chambers()->count())->toBe(2);
});
