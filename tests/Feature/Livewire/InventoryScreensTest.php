<?php

use App\Enums\BoxStatus;
use App\Livewire\Admin\Branches;
use App\Livewire\Admin\Products;
use App\Livewire\Admin\Users;
use App\Livewire\Dashboard;
use App\Livewire\Inventory\BoxHistory;
use App\Livewire\Inventory\CreateEntry;
use App\Livewire\Inventory\CreateExit;
use App\Livewire\Inventory\CreateTransfer;
use App\Livewire\Inventory\Entries;
use App\Livewire\Inventory\Exits;
use App\Livewire\Inventory\Stock;
use App\Livewire\Inventory\Transfers;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Chamber;
use App\Models\Product;
use App\Models\StockExit;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Inventory\RegisterEntry;
use App\Services\Inventory\RegisterExit;
use Livewire\Livewire;

/**
 * @return array<string, mixed>
 */
function screensWorld(): array
{
    $centro = Branch::factory()->create(['name' => 'Centro']);
    $norte = Branch::factory()->create(['name' => 'Norte']);
    $sur = Branch::factory()->create(['name' => 'Sur']);
    $c1 = Chamber::factory()->for($centro)->create(['name' => 'Centro 1']);
    $n1 = Chamber::factory()->for($norte)->create(['name' => 'Norte 1']);

    $admin = User::factory()->superAdmin()->create();
    $centroUser = User::factory()->warehouse($centro)->create();
    $centroUser->chambers()->attach($c1);
    $norteUser = User::factory()->warehouse($norte)->create();
    $norteUser->chambers()->attach($n1);

    $product = Product::factory()->create(['code' => 'POLLO', 'name' => 'Pollo']);
    app(RegisterEntry::class)->handle($centroUser, $c1, [
        ['product_id' => $product->id, 'boxes_count' => 3, 'weight_per_box_kg' => '20', 'unit_cost_mxn' => '123.45'],
    ]);

    return ['centro' => $centro, 'norte' => $norte, 'sur' => $sur, 'c1' => $c1, 'n1' => $n1, 'admin' => $admin,
        'centroUser' => $centroUser, 'norteUser' => $norteUser, 'product' => $product, 'boxes' => Box::query()->orderBy('id')->get()];
}

it('requires sign in and keeps administration away from warehouse users', function () {
    $w = screensWorld();

    $this->get(route('stock'))->assertRedirect(route('login'));
    $this->get(route('admin.users'))->assertRedirect(route('login'));

    $this->actingAs($w['centroUser']);
    foreach (['admin.branches', 'admin.chambers', 'admin.categories', 'admin.products', 'admin.users'] as $name) {
        $this->get(route($name))->assertForbidden();
    }
});

it('renders every inventory screen for a warehouse user', function () {
    $w = screensWorld();
    $this->actingAs($w['centroUser']);

    foreach (['dashboard', 'stock', 'entries.index', 'entries.create', 'exits.index', 'exits.create', 'transfers.index'] as $name) {
        $this->get(route($name))->assertOk();
    }

    $this->get(route('boxes.show', $w['boxes'][0]))->assertOk()->assertSee($w['boxes'][0]->barcode);
});

it('renders every administration screen for an administrator', function () {
    $w = screensWorld();
    $this->actingAs($w['admin']);

    foreach (['admin.branches', 'admin.chambers', 'admin.categories', 'admin.products', 'admin.users', 'stock', 'exits.index'] as $name) {
        $this->get(route($name))->assertOk();
    }
});

it('manages branches and leaves an audit trail', function () {
    $w = screensWorld();

    Livewire::actingAs($w['admin'])->test(Branches::class)
        ->call('create')
        ->set('code', 'est')
        ->set('name', 'Estación')
        ->call('save')
        ->assertHasNoErrors();

    $branch = Branch::query()->where('code', 'EST')->firstOrFail();

    Livewire::actingAs($w['admin'])->test(Branches::class)->call('toggle', $branch->id);

    expect($branch->fresh()->is_active)->toBeFalse()
        ->and(DB::table('audit_logs')->whereIn('event', ['branch.created', 'branch.updated'])->count())->toBe(2);
});

it('rejects duplicate branch codes and blocks non administrators', function () {
    $w = screensWorld();

    Livewire::actingAs($w['admin'])->test(Branches::class)
        ->call('create')->set('code', $w['centro']->code)->set('name', 'Otra')->call('save')
        ->assertHasErrors('code');

    Livewire::actingAs($w['centroUser'])->test(Branches::class)->call('create')->assertForbidden();
});

it('keeps the product code fixed once boxes exist', function () {
    $w = screensWorld();

    Livewire::actingAs($w['admin'])->test(Products::class)
        ->call('edit', $w['product']->id)
        ->assertSet('codeLocked', true)
        ->set('code', 'OTRO')
        ->call('save')
        ->assertHasErrors('code');

    expect($w['product']->fresh()->code)->toBe('POLLO');
});

it('creates warehouse users with chambers of their own branch only', function () {
    $w = screensWorld();
    $extra = Chamber::factory()->for($w['centro'])->create();

    Livewire::actingAs($w['admin'])->test(Users::class)
        ->call('create')
        ->set('name', 'Nuevo Almacenista')
        ->set('email', 'nuevo@example.com')
        ->set('password', 'password-123')
        ->set('role', 'warehouse')
        ->set('branch_id', $w['centro']->id)
        ->set('chamberIds', [$extra->id])
        ->call('save')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'nuevo@example.com')->firstOrFail();
    expect($user->chambers->pluck('id')->all())->toBe([$extra->id])->and($user->is_active)->toBeTrue();

    Livewire::actingAs($w['admin'])->test(Users::class)
        ->call('edit', $user->id)
        ->set('chamberIds', [$w['n1']->id])
        ->call('save')
        ->assertHasErrors('operation');

    expect($user->fresh()->chambers->pluck('id')->all())->toBe([$extra->id]);
});

it('does not let administrators demote or deactivate themselves', function () {
    $w = screensWorld();

    Livewire::actingAs($w['admin'])->test(Users::class)
        ->call('edit', $w['admin']->id)
        ->set('role', 'warehouse')
        ->set('branch_id', $w['centro']->id)
        ->call('save')
        ->assertHasErrors('operation');

    expect($w['admin']->fresh()->isSuperAdmin())->toBeTrue();
});

it('shows the price of each box in the stock table to administrators and warehouse users', function () {
    $w = screensWorld();

    Livewire::actingAs($w['admin'])->test(Stock::class)->assertSee('Precio (MXN)')->assertSee('123.45');
    Livewire::actingAs($w['centroUser'])->test(Stock::class)->assertSee('Precio (MXN)')->assertSee('123.45');
});

it('limits the stock list of a warehouse user to the boxes in their assigned chambers', function () {
    $w = screensWorld();
    $other = Chamber::factory()->for($w['centro'])->create(['name' => 'Centro 2']);

    app(RegisterEntry::class)->handle($w['admin'], $other, [
        ['product_id' => $w['product']->id, 'boxes_count' => 1, 'weight_per_box_kg' => '5', 'unit_cost_mxn' => '77.77'],
    ]);
    $foreign = Box::query()->where('current_chamber_id', $other->id)->firstOrFail();

    Livewire::actingAs($w['centroUser'])->test(Stock::class)
        ->assertSee($w['boxes'][0]->barcode)
        ->assertDontSee($foreign->barcode)
        ->assertDontSee('Centro 2')
        ->set('chamberId', $other->id)
        ->assertDontSee($foreign->barcode);

    Livewire::actingAs($w['norteUser'])->test(Stock::class)
        ->assertDontSee($w['boxes'][0]->barcode)
        ->set('status', 'all')
        ->assertDontSee($w['boxes'][0]->barcode);

    Livewire::actingAs($w['admin'])->test(Stock::class)->assertSee($foreign->barcode)->assertSee($w['boxes'][0]->barcode);
});

it('does not list boxes that left the chamber for a warehouse user', function () {
    $w = screensWorld();
    $box = $w['boxes'][0];
    app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$box->id]);

    Livewire::actingAs($w['centroUser'])->test(Stock::class)
        ->set('status', 'dispatched')
        ->assertDontSee($box->barcode);
});

it('captures several groups in the list and registers the entry', function () {
    $w = screensWorld();
    $before = Box::query()->count();

    Livewire::actingAs($w['centroUser'])->test(CreateEntry::class)
        ->set('chamberId', $w['c1']->id)
        ->set('productId', $w['product']->id)
        ->set('boxesCount', '2')
        ->set('weight', '10')
        ->set('cost', '50')
        ->call('addLine')
        ->assertHasNoErrors()
        ->assertSet('weight', '')
        ->assertSet('boxesCount', '1')
        ->set('productId', $w['product']->id)
        ->set('boxesCount', '1')
        ->set('weight', '12.5')
        ->set('cost', '60')
        ->call('addLine')
        ->assertDontSee('Importe')
        ->assertDontSee('$160.00')
        ->assertSee('32.500')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('entries.index'));

    expect(Box::query()->count())->toBe($before + 3);
});

it('removes a group from the list before saving', function () {
    $w = screensWorld();

    $component = Livewire::actingAs($w['centroUser'])->test(CreateEntry::class)
        ->set('chamberId', $w['c1']->id)
        ->set('productId', $w['product']->id)
        ->set('weight', '10')
        ->set('cost', '50')
        ->call('addLine')
        ->call('removeLine', 0);

    expect($component->get('lines'))->toBe([]);
    $component->call('save')->assertHasErrors('operation');
});

it('shows validation and business errors on the entry form', function () {
    $w = screensWorld();

    Livewire::actingAs($w['centroUser'])->test(CreateEntry::class)
        ->call('addLine')
        ->assertHasErrors(['productId', 'weight', 'cost']);

    Livewire::actingAs($w['centroUser'])->test(CreateEntry::class)
        ->set('productId', $w['product']->id)->set('weight', '10')->set('cost', '10')
        ->set('manual', "ETIQ-1\nETIQ-2")->set('boxesCount', '1')
        ->call('addLine')
        ->assertHasErrors('manual');

    Livewire::actingAs($w['centroUser'])->test(CreateEntry::class)
        ->set('productId', $w['product']->id)->set('weight', '10')->set('cost', '10')
        ->set('manual', 'ETIQ-1')
        ->call('addLine')
        ->assertHasErrors('origin');

    Livewire::actingAs($w['norteUser'])->test(CreateEntry::class)
        ->set('chamberId', $w['c1']->id)
        ->set('productId', $w['product']->id)->set('weight', '10')->set('cost', '10')
        ->call('addLine')
        ->call('save')
        ->assertForbidden();
});

it('accepts manual barcodes with their origin from the entry form', function () {
    $w = screensWorld();

    Livewire::actingAs($w['centroUser'])->test(CreateEntry::class)
        ->set('chamberId', $w['c1']->id)
        ->set('productId', $w['product']->id)->set('weight', '10')->set('cost', '10')
        ->set('manual', 'etiq-77')->set('origin', 'Proveedor X')
        ->call('addLine')
        ->assertHasNoErrors()
        ->call('save')
        ->assertHasNoErrors();

    expect(Box::query()->where('barcode', 'ETIQ-77')->exists())->toBeTrue();
});

it('adds boxes to an exit by quantity, oldest first, and registers it', function () {
    $w = screensWorld();
    [$first, $second, $third] = $w['boxes']->all();

    $component = Livewire::actingAs($w['centroUser'])->test(CreateExit::class)
        ->set('originChamberId', $w['c1']->id)
        ->set('productId', $w['product']->id)
        ->set('quantity', '2')
        ->call('addProduct')
        ->assertHasNoErrors()
        ->assertSet('quantity', '');

    expect($component->get('lines'))->toHaveCount(1)
        ->and($component->get('lines')[0]['box_ids'])->toBe([$first->id, $second->id])
        ->and($component->get('lines')[0]['weight'])->toBe('20.000');

    $component->set('destBranchId', $w['sur']->id)
        ->set('requester', 'Ana López')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('exits.index'));

    expect(Box::query()->whereIn('id', [$first->id, $second->id])->pluck('status')->unique()->all())->toBe([BoxStatus::Dispatched])
        ->and($third->fresh()->status)->toBe(BoxStatus::Available);
});

it('warns when the requested quantity exceeds the available boxes', function () {
    $w = screensWorld();

    Livewire::actingAs($w['centroUser'])->test(CreateExit::class)
        ->set('originChamberId', $w['c1']->id)
        ->set('productId', $w['product']->id)
        ->set('quantity', '4')
        ->call('addProduct')
        ->assertHasErrors('quantity')
        ->assertSet('lines', [])
        ->assertSee('solo hay 3 disponibles');
});

it('does not offer again the boxes already added to the exit', function () {
    $w = screensWorld();

    $component = Livewire::actingAs($w['centroUser'])->test(CreateExit::class)
        ->set('originChamberId', $w['c1']->id)
        ->set('productId', $w['product']->id)
        ->set('quantity', '2')
        ->call('addProduct')
        ->set('productId', $w['product']->id)
        ->set('quantity', '2')
        ->call('addProduct')
        ->assertHasErrors('quantity');

    expect($component->get('lines'))->toHaveCount(1)
        ->and($component->get('lines')[0]['box_ids'])->toHaveCount(2);

    $component->set('quantity', '1')->call('addProduct')->assertHasNoErrors();
    expect($component->get('lines'))->toHaveCount(1)->and($component->get('lines')[0]['box_ids'])->toHaveCount(3);
});

it('asks which weight and price variant to send when a product has several', function () {
    $w = screensWorld();
    app(RegisterEntry::class)->handle($w['centroUser'], $w['c1'], [
        ['product_id' => $w['product']->id, 'boxes_count' => 2, 'weight_per_box_kg' => '22', 'unit_cost_mxn' => '150'],
    ]);

    $component = Livewire::actingAs($w['centroUser'])->test(CreateExit::class)
        ->set('originChamberId', $w['c1']->id)
        ->set('productId', $w['product']->id)
        ->set('quantity', '4')
        ->call('addProduct')
        ->assertSet('lines', [])
        ->assertSet('pendingQuantity', 4);

    expect($component->get('variants'))->toBe([
        ['weight' => '20.000', 'cost' => '123.45', 'available' => 3],
        ['weight' => '22.000', 'cost' => '150.00', 'available' => 2],
    ]);

    $component->set('variantQuantities', [0 => '3', 1 => '2'])->call('confirmVariants')->assertHasErrors('variants');
    $component->set('variantQuantities', [0 => '4', 1 => '0'])->call('confirmVariants')->assertHasErrors('variants');
    $component->set('variantQuantities', [0 => '2'])->call('fillVariant', 1)->assertSet('variantQuantities.1', '2');

    $component->set('variantQuantities', [0 => '2', 1 => '2'])->call('confirmVariants')->assertHasNoErrors();

    expect($component->get('lines'))->toHaveCount(2)
        ->and(array_map(fn (array $line): int => count($line['box_ids']), $component->get('lines')))->toBe([2, 2])
        ->and($component->get('lines')[1]['cost'])->toBe('150.00');
});

it('only lets administrators set the date of an exit', function () {
    $w = screensWorld();
    $form = fn (User $user, string $date) => Livewire::actingAs($user)->test(CreateExit::class)
        ->set('originChamberId', $w['c1']->id)
        ->set('productId', $w['product']->id)
        ->set('quantity', '1')
        ->call('addProduct')
        ->set('destBranchId', $w['sur']->id)
        ->set('requester', 'Ana')
        ->set('occurredAt', $date)
        ->call('save');

    $form($w['centroUser'], '2020-01-01T08:00');
    expect(StockExit::query()->latest('id')->first()->occurred_at->year)->toBe((int) now()->year);

    $form($w['admin'], '2020-01-01T08:00');
    expect(StockExit::query()->latest('id')->first()->occurred_at->year)->toBe(2020);
});

it('shows the historic cost of the exit only to administrators', function () {
    $w = screensWorld();
    $add = fn (User $user) => Livewire::actingAs($user)->test(CreateExit::class)
        ->set('originChamberId', $w['c1']->id)
        ->set('productId', $w['product']->id)
        ->set('quantity', '3')
        ->call('addProduct');

    $add($w['admin'])->assertSee('370.35');
    $add($w['centroUser'])->assertDontSee('370.35');
});

it('does not use a chamber the user cannot access when the origin is tampered with', function () {
    $w = screensWorld();

    $component = Livewire::actingAs($w['norteUser'])->test(CreateExit::class)
        ->set('originChamberId', $w['c1']->id)
        ->set('productId', $w['product']->id)
        ->set('quantity', '1')
        ->call('addProduct')
        ->assertForbidden();

    Livewire::actingAs($w['norteUser'])->test(CreateExit::class)
        ->set('originChamberId', $w['c1']->id)
        ->set('lines', [['product_id' => $w['product']->id, 'product' => 'Pollo', 'weight' => '20.000', 'cost' => '123.45', 'box_ids' => [$w['boxes'][0]->id]]])
        ->set('destBranchId', $w['sur']->id)
        ->set('requester', 'Intruso')
        ->call('save')
        ->assertForbidden();

    expect($w['boxes'][0]->fresh()->status)->toBe(BoxStatus::Available);
});

it('sends a transfer, then receives it from the destination chamber screen', function () {
    $w = screensWorld();
    $box = $w['boxes'][0];

    app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Norte', [$box->id]);
    $transfer = Transfer::query()->firstOrFail();

    Livewire::actingAs($w['centroUser'])->test(Transfers::class)->call('askReceive', $transfer->id)->assertForbidden();

    Livewire::actingAs($w['norteUser'])->test(Transfers::class)
        ->call('askReceive', $transfer->id)
        ->assertSet('received', [$box->id])
        ->call('receive')
        ->assertHasNoErrors();

    expect($box->fresh()->current_chamber_id)->toBe($w['n1']->id);
});

it('lets only the super administrator cancel a transfer in transit', function () {
    $w = screensWorld();
    $box = $w['boxes'][0];

    app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Norte', [$box->id]);
    $transfer = Transfer::query()->firstOrFail();

    foreach (['norteUser', 'centroUser'] as $role) {
        Livewire::actingAs($w[$role])->test(Transfers::class)->call('askCancel', $transfer->id)->assertForbidden();
    }

    Livewire::actingAs($w['admin'])->test(Transfers::class)
        ->call('askCancel', $transfer->id)
        ->set('reason', 'Destino equivocado')
        ->call('cancel')
        ->assertHasNoErrors();

    expect($box->fresh()->status)->toBe(BoxStatus::Available);
});

it('lets only administrators reverse an exit from the exits screen', function () {
    $w = screensWorld();
    $box = $w['boxes'][0];
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$box->id]);

    Livewire::actingAs($w['centroUser'])->test(Exits::class)->call('askReverse', $exit->id)->assertForbidden();

    Livewire::actingAs($w['admin'])->test(Exits::class)
        ->call('askReverse', $exit->id)
        ->set('reason', 'Captura equivocada')
        ->call('reverse')
        ->assertHasNoErrors();

    expect($box->fresh()->status)->toBe(BoxStatus::Available)
        ->and(StockExit::query()->find($exit->id)->status->value)->toBe('reversed');
});

it('registers a partial withdrawal from the box page and blocks other branches', function () {
    $w = screensWorld();
    $box = $w['boxes'][0];

    Livewire::actingAs($w['norteUser'])->test(BoxHistory::class, ['box' => $box])->assertForbidden();

    Livewire::actingAs($w['centroUser'])->test(BoxHistory::class, ['box' => $box])
        ->set('weightOut', '5')
        ->set('destBranchId', $w['sur']->id)
        ->set('requester', 'Ana')
        ->call('withdraw')
        ->assertHasNoErrors();

    expect($box->fresh()->current_weight_kg)->toBe('15.000');

    Livewire::actingAs($w['centroUser'])->test(BoxHistory::class, ['box' => $box])
        ->set('weightOut', '99')
        ->set('destBranchId', $w['sur']->id)
        ->set('requester', 'Ana')
        ->call('withdraw')
        ->assertHasErrors('operation');
});

it('summarizes only the chambers of the signed in user on the dashboard', function () {
    $w = screensWorld();

    Livewire::actingAs($w['centroUser'])->test(Dashboard::class)->assertSee('Centro 1')->assertSee('60.000')->assertDontSee('Norte 1');
    Livewire::actingAs($w['centroUser'])->test(Entries::class)->assertSee('ENT-');
    Livewire::actingAs($w['norteUser'])->test(Entries::class)->assertDontSee('ENT-');
    expect(Category::query()->count())->toBeGreaterThan(0);
});

it('confirms every exit right away, even to a branch that has chambers, and never creates a transfer', function () {
    $w = screensWorld();

    Livewire::actingAs($w['centroUser'])->test(CreateExit::class)
        ->assertDontSee('Cámara destino')
        ->set('originChamberId', $w['c1']->id)
        ->set('productId', $w['product']->id)
        ->set('quantity', '1')
        ->call('addProduct')
        ->set('destBranchId', $w['norte']->id)
        ->set('requester', 'Ana López')
        ->call('save')
        ->assertHasNoErrors();

    expect(Transfer::query()->count())->toBe(0)
        ->and(StockExit::query()->sole()->status->value)->toBe('confirmed')
        ->and(Box::query()->where('status', BoxStatus::Dispatched)->count())->toBe(1);
});

it('creates a chamber to chamber transfer that stays in transit until received', function () {
    $w = screensWorld();

    $component = Livewire::actingAs($w['centroUser'])->test(CreateTransfer::class)
        ->set('originChamberId', $w['c1']->id)
        ->set('productId', $w['product']->id)
        ->set('quantity', '2')
        ->call('addProduct')
        ->set('requester', 'Ana López')
        ->call('save')
        ->assertHasErrors(['destChamberId' => 'required']);

    $component->set('destChamberId', $w['n1']->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('transfers.index'));

    $transfer = Transfer::query()->sole();

    expect($transfer->origin_chamber_id)->toBe($w['c1']->id)
        ->and($transfer->dest_chamber_id)->toBe($w['n1']->id)
        ->and($transfer->status->value)->toBe('in_transit')
        ->and(Box::query()->where('status', BoxStatus::InTransit)->count())->toBe(2);
});

it('offers only other chambers as transfer destination and keeps the read-only admin out', function () {
    $w = screensWorld();

    Livewire::actingAs($w['centroUser'])->test(CreateTransfer::class)
        ->set('originChamberId', $w['c1']->id)
        ->assertSee('Norte · Norte 1')
        ->assertViewHas('destChambers', fn ($chambers): bool => $chambers->pluck('id')->all() === [$w['n1']->id]);

    $this->actingAs($w['centroUser'])->get(route('transfers.create'))->assertOk();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('transfers.create'))->assertForbidden();
});

it('keeps the entries, exits and transfers submenus always open for warehouse users', function () {
    $w = screensWorld();

    $html = $this->actingAs($w['centroUser'])->get(route('dashboard'))->assertOk()->getContent();

    expect(substr_count($html, 'x-data=\'{ open: true }\''))->toBe(3);

    $this->actingAs($w['admin'])->get(route('dashboard'))->assertOk()
        ->assertSee('$persist(true).as(', false);
});

it('shows the computed amounts of a new entry only to users who may see costs', function () {
    $w = screensWorld();

    $capture = fn (User $user) => Livewire::actingAs($user)->test(CreateEntry::class)
        ->set('chamberId', $w['c1']->id)
        ->set('productId', $w['product']->id)
        ->set('boxesCount', '2')
        ->set('weight', '10')
        ->set('cost', '50')
        ->call('addLine');

    $capture($w['centroUser'])->assertDontSee('Importe')->assertDontSee('$100.00')->assertSee('$50.00')->assertSee('20.000');
    $capture($w['admin'])->assertSee('Importe')->assertSee('$100.00');
});
