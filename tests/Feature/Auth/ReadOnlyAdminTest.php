<?php

use App\Enums\AdjustmentType;
use App\Enums\UserRole;
use App\Livewire\Admin\Branches;
use App\Livewire\Admin\Categories;
use App\Livewire\Admin\Chambers;
use App\Livewire\Admin\Products;
use App\Livewire\Admin\Users;
use App\Livewire\Dashboard;
use App\Livewire\Inventory\BoxHistory;
use App\Livewire\Inventory\Exits;
use App\Livewire\Inventory\Transfers;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\AdjustInventory;
use App\Services\Inventory\RegisterEntry;
use App\Services\Inventory\RegisterExit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * @return array<string, mixed>
 */
function readOnlyWorld(): array
{
    $centro = Branch::factory()->create(['name' => 'Centro']);
    $norte = Branch::factory()->create(['name' => 'Norte']);
    $sur = Branch::factory()->create(['name' => 'Sur']);
    $c1 = Chamber::factory()->for($centro)->create(['name' => 'Centro 1']);
    $n1 = Chamber::factory()->for($norte)->create(['name' => 'Norte 1']);

    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();
    $centroUser = User::factory()->warehouse($centro)->create();
    $centroUser->chambers()->attach($c1);
    $norteUser = User::factory()->warehouse($norte)->create();
    $norteUser->chambers()->attach($n1);

    $product = Product::factory()->create(['code' => 'POLLO', 'name' => 'Pollo']);
    $entry = app(RegisterEntry::class)->handle($centroUser, $c1, [
        ['product_id' => $product->id, 'boxes_count' => 4, 'weight_per_box_kg' => '10', 'unit_cost_mxn' => '123.45'],
    ]);
    app(RegisterEntry::class)->handle($norteUser, $n1, [
        ['product_id' => $product->id, 'boxes_count' => 2, 'weight_per_box_kg' => '10', 'unit_cost_mxn' => '99.99'],
    ]);
    $boxes = Box::query()->orderBy('id')->get();
    $exit = app(RegisterExit::class)->handle($centroUser, $c1, $sur, null, 'Ana', [$boxes[0]->id]);

    return compact('centro', 'norte', 'sur', 'c1', 'n1', 'superAdmin', 'admin', 'centroUser', 'norteUser', 'product', 'entry', 'exit', 'boxes');
}

it('stores the three roles', function () {
    foreach (UserRole::cases() as $role) {
        expect(User::factory()->create(['role' => $role])->fresh()->role)->toBe($role);
    }

    expect(array_map(fn (UserRole $role): string => $role->value, UserRole::cases()))->toBe(['super_admin', 'admin', 'warehouse']);
});

it('lets the administrator open the panel, stock, entry and exit records and the catalogs', function () {
    $w = readOnlyWorld();
    $this->actingAs($w['admin']);

    foreach ([
        route('dashboard'), route('stock'), route('boxes.show', $w['boxes'][0]),
        route('entries.index'), route('entries.show', $w['entry']),
        route('exits.index'), route('exits.show', $w['exit']),
        route('admin.branches'), route('admin.chambers'), route('admin.categories'), route('admin.products'),
        route('profile.edit'),
    ] as $url) {
        $this->get($url)->assertOk();
    }

    $this->get(route('entries.export', [$w['entry'], 'xlsx']))->assertOk();
    $this->get(route('exits.export', [$w['exit'], 'pdf']))->assertOk();
});

it('keeps the administrator out of every route that registers or manages anything', function () {
    $w = readOnlyWorld();
    $this->actingAs($w['admin']);

    foreach ([
        'entries.create', 'exits.create',
        'admin.users', 'admin.incidents', 'admin.adjustments', 'admin.audit',
        'reports.inventory', 'reports.entries', 'reports.exits', 'reports.movements', 'reports.low-stock',
    ] as $name) {
        $this->get(route($name))->assertForbidden();
    }
});

it('keeps warehouse users out of the catalogs and super administrators in everywhere', function () {
    $w = readOnlyWorld();

    $this->actingAs($w['centroUser'])->get(route('admin.products'))->assertForbidden();

    $this->actingAs($w['superAdmin']);
    foreach (['entries.create', 'exits.create', 'transfers.index', 'admin.users', 'admin.branches', 'reports.inventory', 'admin.audit'] as $name) {
        $this->get(route($name))->assertOk();
    }
});

it('shows the administrator the records of every chamber with their costs', function () {
    $w = readOnlyWorld();

    $this->actingAs($w['admin'])->get(route('entries.index'))->assertSee('Centro 1')->assertSee('Norte 1')->assertSee('493.80');
    $this->get(route('stock'))->assertSee($w['boxes'][1]->barcode)->assertSee($w['boxes'][5]->barcode)->assertSee('99.99');
    $this->get(route('exits.index'))->assertSee('SAL-');
    $this->get(route('exits.show', $w['exit']))->assertSee('Costo histórico');
});

it('hides the create buttons and the reversal from the administrator', function () {
    $w = readOnlyWorld();
    $this->actingAs($w['admin']);

    $this->get(route('entries.index'))->assertDontSee('Nueva entrada')->assertDontSee(route('entries.create'), false);
    $this->get(route('exits.index'))->assertDontSee('Nueva salida')->assertDontSee(route('exits.create'), false)->assertDontSee('askReverse', false);
    foreach (['admin.branches', 'admin.chambers', 'admin.categories', 'admin.products'] as $name) {
        $this->get(route($name))->assertOk()->assertDontSee('wire:click="create"', false)->assertDontSee('wire:click="edit(', false)->assertDontSee('wire:click="toggle(', false);
    }

    $this->actingAs($w['superAdmin']);
    $this->get(route('entries.index'))->assertSee('Nueva entrada');
    $this->get(route('exits.index'))->assertSee('Nueva salida')->assertSee('Revertir');
    $this->get(route('admin.products'))->assertSee('wire:click="create"', false);
});

it('shows each role only its own menu', function () {
    $w = readOnlyWorld();

    $admin = $this->actingAs($w['admin'])->get(route('dashboard'));
    $admin->assertSee('Existencias')->assertSee('Entradas')->assertSee('Salidas')->assertSee('Sucursales')->assertSee('Cámaras')->assertSee('Categorías')->assertSee('Productos')
        ->assertSee('Transferencias')->assertDontSee('Usuarios')->assertDontSee('Auditoría')->assertDontSee('Ajustes')->assertDontSee('Incidencias')->assertDontSee('Reportes');

    $this->actingAs($w['superAdmin'])->get(route('dashboard'))
        ->assertSee('Transferencias')->assertSee('Usuarios')->assertSee('Auditoría')->assertSee('Reportes')->assertSee('Productos');

    $this->actingAs($w['centroUser'])->get(route('dashboard'))
        ->assertSee('Transferencias')->assertDontSee('Administración')->assertDontSee('Usuarios')->assertDontSee('Reportes');
});

it('blocks every write action of the catalogs on the server', function () {
    $w = readOnlyWorld();

    Livewire::actingAs($w['admin'])->test(Branches::class)->call('create')->assertForbidden();
    Livewire::actingAs($w['admin'])->test(Branches::class)->call('toggle', $w['centro']->id)->assertForbidden();
    Livewire::actingAs($w['admin'])->test(Chambers::class)->call('edit', $w['c1']->id)->assertForbidden();
    Livewire::actingAs($w['admin'])->test(Categories::class)->call('create')->assertForbidden();
    Livewire::actingAs($w['admin'])->test(Products::class)->call('edit', $w['product']->id)->assertForbidden();
    Livewire::actingAs($w['admin'])->test(Products::class)->call('toggle', $w['product']->id)->assertForbidden();

    expect($w['product']->fresh()->is_active)->toBeTrue();
});

it('blocks reversing exits and withdrawing boxes for the administrator', function () {
    $w = readOnlyWorld();

    Livewire::actingAs($w['admin'])->test(Exits::class)->call('askReverse', $w['exit']->id)->assertForbidden();

    $component = Livewire::actingAs($w['admin'])->test(BoxHistory::class, ['box' => $w['boxes'][1]]);
    $component->assertDontSee('Retiro parcial');
    $component->set('weightOut', '2')->set('destBranchId', $w['sur']->id)->set('requester', 'Ana')->call('withdraw')->assertForbidden();

    expect($w['boxes'][1]->fresh()->current_weight_kg)->toBe('10.000');
});

it('denies the administrator in the services, not only on the screens', function () {
    $w = readOnlyWorld();

    expect(fn () => app(RegisterEntry::class)->handle($w['admin'], $w['c1'], [
        ['product_id' => $w['product']->id, 'boxes_count' => 1, 'weight_per_box_kg' => '10', 'unit_cost_mxn' => '1'],
    ]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RegisterExit::class)->handle($w['admin'], $w['c1'], $w['sur'], null, 'Ana', [$w['boxes'][1]->id]))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(AdjustInventory::class)->handle($w['admin'], $w['boxes'][1], AdjustmentType::Lost, 'Intento de baja'))
        ->toThrow(AuthorizationException::class);

    expect($w['admin']->canAccessChamber($w['c1']))->toBeFalse()
        ->and($w['admin']->seesAllChambers())->toBeTrue()
        ->and($w['admin']->isSuperAdmin())->toBeFalse();
});

it('only allows the administrator to view records, nothing else, in the policies', function () {
    $w = readOnlyWorld();
    $gate = Gate::forUser($w['admin']);

    expect($gate->allows('view', $w['entry']))->toBeTrue()
        ->and($gate->allows('view', $w['exit']))->toBeTrue()
        ->and($gate->allows('view', $w['boxes'][0]))->toBeTrue()
        ->and($gate->allows('viewAny', Product::class))->toBeTrue()
        ->and($gate->allows('view-costs'))->toBeTrue()
        ->and($gate->allows('view-catalogs'))->toBeTrue()
        ->and($gate->allows('operate-inventory'))->toBeFalse()
        ->and($gate->allows('create', Product::class))->toBeFalse()
        ->and($gate->allows('update', $w['c1']))->toBeFalse()
        ->and($gate->allows('reverse', $w['exit']))->toBeFalse()
        ->and($gate->allows('view', $w['centroUser']))->toBeFalse()
        ->and($gate->allows('viewAny', AuditLog::class))->toBeFalse();

    $w['admin']->forceFill(['is_active' => false])->save();
    expect(Gate::forUser($w['admin']->fresh())->allows('view', $w['entry']))->toBeFalse();
});

it('gives the administrator the whole picture on the panel, without the transfers or incidents', function () {
    $w = readOnlyWorld();

    $component = Livewire::actingAs($w['admin'])->test(Dashboard::class)
        ->assertSee('Información concentrada de todas las cámaras')
        ->assertSee('Valor del inventario')
        ->assertDontSee('Transferencias por recibir')
        ->assertDontSee('Incidencias abiertas');

    expect($component->viewData('totalBoxes'))->toBe(5)
        ->and($component->viewData('allChambers'))->toHaveCount(2);

    $component->set('chamberId', $w['n1']->id);
    expect($component->viewData('totalBoxes'))->toBe(2);
});

it('lets the super administrator create read-only administrators from the users screen', function () {
    $w = readOnlyWorld();

    Livewire::actingAs($w['superAdmin'])->test(Users::class)
        ->call('create')
        ->set('name', 'Dirección')
        ->set('email', 'direccion@example.com')
        ->set('password', 'password-123')
        ->set('role', 'admin')
        ->set('branch_id', $w['centro']->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Administrador');

    $created = User::query()->where('email', 'direccion@example.com')->firstOrFail();
    expect($created->role)->toBe(UserRole::Admin)
        ->and($created->branch_id)->toBeNull()
        ->and($created->chambers()->count())->toBe(0);

    Livewire::actingAs($w['superAdmin'])->test(Users::class)
        ->call('create')->set('name', 'X')->set('email', 'x@example.com')->set('password', 'password-123')->set('role', 'root')
        ->call('save')->assertHasErrors('role');
});

it('does not let the administrator see or use the users screen', function () {
    $w = readOnlyWorld();

    Livewire::actingAs($w['admin'])->test(Users::class)->assertForbidden();
});

it('lets the administrator list every transfer but not receive or cancel any', function () {
    $w = readOnlyWorld();
    $transfer = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Luis', [$w['boxes'][1]->id])->transfer;

    $this->actingAs($w['admin'])->get(route('transfers.index'))->assertOk()->assertSee('Transferencias');

    $component = Livewire::actingAs($w['admin'])->test(Transfers::class);
    expect($component->viewData('transfers')->total())->toBe(1);
    $component->assertDontSee('askReceive', false)->assertDontSee('askCancel', false);
    $component->call('askReceive', $transfer->id)->assertForbidden();
    Livewire::actingAs($w['admin'])->test(Transfers::class)->call('showDetail', $transfer->id)->assertOk()->assertSee('Salida')->assertSee('En tránsito desde hace');

    $gate = Gate::forUser($w['admin']);
    expect($gate->allows('view', $transfer))->toBeTrue()
        ->and($gate->allows('receive', $transfer))->toBeFalse()
        ->and($gate->allows('cancel', $transfer))->toBeFalse();
});
