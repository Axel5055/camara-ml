<?php

use App\Enums\AdjustmentType;
use App\Enums\TransferStatus;
use App\Livewire\Admin\Adjustments;
use App\Livewire\Admin\AuditLogs;
use App\Livewire\Admin\Branches;
use App\Livewire\Admin\Categories;
use App\Livewire\Admin\Chambers;
use App\Livewire\Admin\Incidents;
use App\Livewire\Admin\Products;
use App\Livewire\Admin\Users;
use App\Livewire\Dashboard;
use App\Livewire\Inventory\Entries;
use App\Livewire\Inventory\Exits;
use App\Livewire\Inventory\Stock;
use App\Livewire\Inventory\TransferDetail;
use App\Livewire\Inventory\Transfers;
use App\Livewire\NotificationsBell;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Chamber;
use App\Models\Entry;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\AdjustInventory;
use App\Services\Inventory\CancelTransfer;
use App\Services\Inventory\LowStockAlerts;
use App\Services\Inventory\ReceiveTransfer;
use App\Services\Inventory\RegisterEntry;
use App\Services\Inventory\RegisterExit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * @return array<string, mixed>
 */
function widgetsWorld(): array
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

    $category = Category::factory()->create(['name' => 'POLLO']);
    $pollo = Product::factory()->create(['category_id' => $category->id, 'code' => 'POLLO', 'name' => 'Pollo entero']);
    $alas = Product::factory()->create(['category_id' => $category->id, 'code' => 'ALAS', 'name' => 'Alas de pollo']);

    return compact('centro', 'norte', 'sur', 'c1', 'n1', 'admin', 'centroUser', 'norteUser', 'pollo', 'alas');
}

/**
 * @param  array<string, mixed>  $w
 */
function stockUp(array $w, Product $product, int $boxes, ?Chamber $chamber = null, ?User $user = null): void
{
    app(RegisterEntry::class)->handle($user ?? $w['admin'], $chamber ?? $w['c1'], [
        ['product_id' => $product->id, 'boxes_count' => $boxes, 'weight_per_box_kg' => '10', 'unit_cost_mxn' => '100'],
    ]);
}

it('flags products with 20 boxes or fewer, but not 21', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 20);
    stockUp($w, $w['alas'], 21);

    $low = app(LowStockAlerts::class)->for($w['admin']);

    expect(LowStockAlerts::THRESHOLD)->toBe(20)
        ->and($low->pluck('product')->all())->toBe(['Pollo entero'])
        ->and($low->first()->boxes)->toBe(20)
        ->and($low->first()->chamber)->toBe('Centro 1');
});

it('reports a product that ran out, but never one that was not stocked in the chamber', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 2);
    app(RegisterExit::class)->handle($w['admin'], $w['c1'], $w['sur'], null, 'Ana', Box::query()->pluck('id')->all());

    $low = app(LowStockAlerts::class)->for($w['admin']);

    expect($low)->toHaveCount(1)
        ->and($low->first()->product)->toBe('Pollo entero')
        ->and($low->first()->boxes)->toBe(0);
});

it('leaves out inactive products and inactive chambers', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 3);
    stockUp($w, $w['alas'], 3);

    $w['alas']->update(['is_active' => false]);
    expect(app(LowStockAlerts::class)->for($w['admin'])->pluck('product')->all())->toBe(['Pollo entero']);

    $w['c1']->update(['is_active' => false]);
    expect(app(LowStockAlerts::class)->for($w['admin']))->toBeEmpty();
});

it('limits the alerts of a warehouse user to the chambers they work in', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 5, $w['c1']);
    stockUp($w, $w['alas'], 5, $w['n1']);

    $alerts = app(LowStockAlerts::class);

    expect($alerts->for($w['centroUser'])->pluck('product')->all())->toBe(['Pollo entero'])
        ->and($alerts->for($w['norteUser'])->pluck('product')->all())->toBe(['Alas de pollo'])
        ->and($alerts->for($w['admin']))->toHaveCount(2);
});

it('shows the bell with the number of low stock products and lists them', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 7);
    stockUp($w, $w['alas'], 30);

    Livewire::actingAs($w['centroUser'])->test(NotificationsBell::class)
        ->assertSee('Pollo entero')
        ->assertSee('7 cajas')
        ->assertDontSee('Alas de pollo')
        ->assertSeeHtml('data-test="bell-count"')
        ->assertSee('20 cajas o menos');

    Livewire::actingAs($w['norteUser'])->test(NotificationsBell::class)
        ->assertDontSee('Pollo entero')
        ->assertDontSeeHtml('data-test="bell-count"')
        ->assertSee('No hay productos con stock bajo.');
});

it('puts the bell on every screen of the app', function () {
    $w = widgetsWorld();

    $this->actingAs($w['centroUser'])->get(route('stock'))->assertOk()->assertSee('data-test="notifications-bell"', false);
    $this->get(route('dashboard'))->assertOk()->assertSee('data-test="notifications-bell"', false);
});

it('charts entries and exits per day with zeros for quiet days', function () {
    Carbon::setTestNow('2026-10-28 12:00:00');
    $w = widgetsWorld();

    app(RegisterEntry::class)->handle($w['admin'], $w['c1'], [
        ['product_id' => $w['pollo']->id, 'boxes_count' => 10, 'weight_per_box_kg' => '5', 'unit_cost_mxn' => '10'],
    ], occurredAt: Carbon::parse('2026-10-26 09:00:00'));
    app(RegisterEntry::class)->handle($w['admin'], $w['c1'], [
        ['product_id' => $w['pollo']->id, 'boxes_count' => 4, 'weight_per_box_kg' => '5', 'unit_cost_mxn' => '10'],
    ], occurredAt: Carbon::parse('2026-10-28 09:00:00'));
    $ids = Box::query()->orderBy('id')->limit(3)->pluck('id')->all();
    app(RegisterExit::class)->handle($w['admin'], $w['c1'], $w['sur'], null, 'Ana', $ids, Carbon::parse('2026-10-27 10:00:00'));

    $component = Livewire::actingAs($w['admin'])->test(Dashboard::class)->set('days', 7);
    $series = $component->viewData('series');

    expect($series['labels'])->toBe(['22/10', '23/10', '24/10', '25/10', '26/10', '27/10', '28/10'])
        ->and($series['entries'])->toBe([0.0, 0.0, 0.0, 0.0, 10.0, 0.0, 4.0])
        ->and($series['exits'])->toBe([0.0, 0.0, 0.0, 0.0, 0.0, 3.0, 0.0]);

    $kg = Livewire::actingAs($w['admin'])->test(Dashboard::class)->set('days', 7)->set('metric', 'kg')->viewData('series');
    expect($kg['entries'][4])->toBe(50.0)->and($kg['exits'][5])->toBe(15.0);

    Carbon::setTestNow();
});

it('ignores unsupported chart ranges and metrics', function () {
    $w = widgetsWorld();

    Livewire::actingAs($w['admin'])->test(Dashboard::class)
        ->set('days', 999)->assertSet('days', 14)
        ->set('metric', 'bogus')->assertSet('metric', 'boxes');
});

it('summarizes the system on the dashboard, scoped to the chambers of the user', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 12, $w['c1']);
    stockUp($w, $w['alas'], 30, $w['n1']);

    Livewire::actingAs($w['centroUser'])->test(Dashboard::class)
        ->assertSee('Cajas disponibles')
        ->assertSee('Centro 1')
        ->assertDontSee('Norte 1')
        ->assertSee('Entradas y salidas por día')
        ->assertSee('Pollo entero')
        ->assertSee('Existencias por categoría')
        ->assertSee('Últimas entradas')
        ->assertSee('Últimas salidas')
        ->assertDontSee('Valor del inventario')
        ->assertDontSee('Valor (MXN)')
        ->assertDontSeeHtml('data-test="product-value"')
        ->assertDontSee('Incidencias abiertas');

    $admin = Livewire::actingAs($w['admin'])->test(Dashboard::class)
        ->assertSee('Valor del inventario')
        ->assertSee('Incidencias abiertas')
        ->assertSee('Valor (MXN)')
        ->assertSeeHtml('data-test="product-value"')
        ->assertSee('Centro 1')
        ->assertSee('Norte 1');

    expect($admin->viewData('totalBoxes'))->toBe(42)
        ->and((float) $admin->viewData('inventoryValue'))->toBe(4200.0);
});

it('renders the bar chart with every series and scales to the highest value', function () {
    $html = view('components.bar-chart', [
        'labels' => ['01/01', '02/01'],
        'series' => [
            ['name' => 'Entradas', 'color' => '#16a34a', 'values' => [10, 5]],
            ['name' => 'Salidas', 'color' => '#2563eb', 'values' => [0, 20]],
        ],
    ])->render();

    expect($html)->toContain('Entradas')->toContain('Salidas')->toContain('height: 100%')->toContain('height: 50%')
        ->toContain('title="02/01 · Salidas: 20"');
});

it('does not touch the database from the chart when there is no data', function () {
    $w = widgetsWorld();

    expect(DB::table('entries')->count())->toBe(0);
    Livewire::actingAs($w['admin'])->test(Dashboard::class)->assertSee('Todo en orden');
});

it('filters the whole dashboard by chamber or shows everything combined', function () {
    $w = widgetsWorld();
    $c2 = Chamber::factory()->for($w['centro'])->create(['name' => 'Centro 2']);
    stockUp($w, $w['pollo'], 12, $w['c1']);
    stockUp($w, $w['alas'], 5, $c2);
    stockUp($w, $w['alas'], 30, $w['n1']);

    $all = Livewire::actingAs($w['admin'])->test(Dashboard::class)
        ->assertSee('Información concentrada de todas las cámaras')
        ->assertSeeHtml('data-test="chamber-filter"');
    expect($all->viewData('totalBoxes'))->toBe(47)
        ->and($all->viewData('lowStock'))->toHaveCount(2)
        ->and(array_sum($all->viewData('series')['entries']))->toBe(47.0);

    $one = Livewire::actingAs($w['admin'])->test(Dashboard::class)->set('chamberId', $c2->id)
        ->assertSee('Información de la cámara')
        ->assertSee('Centro 2');
    expect($one->viewData('totalBoxes'))->toBe(5)
        ->and($one->viewData('lowStock')->pluck('product')->all())->toBe(['Alas de pollo'])
        ->and($one->viewData('chambers'))->toHaveCount(1)
        ->and(array_sum($one->viewData('series')['entries']))->toBe(5.0)
        ->and((float) $one->viewData('inventoryValue'))->toBe(500.0)
        ->and($one->viewData('recentEntries'))->toHaveCount(1);

    $one->set('chamberId', null)->assertSee('Información concentrada');
    expect($one->viewData('totalBoxes'))->toBe(47);
});

it('adds a total row to the products table only when it lists several products', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 12, $w['c1']);

    Livewire::actingAs($w['admin'])->test(Dashboard::class)->assertDontSee('>Total<', false)->assertDontSee('Existencias por cámara');

    stockUp($w, $w['alas'], 5, $w['c1']);
    Livewire::actingAs($w['admin'])->test(Dashboard::class)->assertSee('Total');
});

it('never lets a warehouse user look at a chamber that is not theirs', function () {
    $w = widgetsWorld();
    $c2 = Chamber::factory()->for($w['centro'])->create(['name' => 'Centro 2']);
    $w['centroUser']->chambers()->attach($c2);
    stockUp($w, $w['pollo'], 12, $w['c1']);
    stockUp($w, $w['alas'], 30, $w['n1']);

    $own = Livewire::actingAs($w['centroUser'])->test(Dashboard::class)
        ->assertSee('Información concentrada de tus 2 cámaras')
        ->assertDontSee('Norte 1');
    expect($own->viewData('allChambers'))->toHaveCount(2);

    $tampered = Livewire::actingAs($w['centroUser'])->test(Dashboard::class)->set('chamberId', $w['n1']->id);
    expect($tampered->viewData('totalBoxes'))->toBe(12)
        ->and($tampered->viewData('selected'))->toBeNull()
        ->and($tampered->viewData('lowStock')->pluck('chamber')->all())->not->toContain('Norte 1');
});

it('hides the chamber selector when the user only works with one chamber', function () {
    $w = widgetsWorld();

    Livewire::actingAs($w['centroUser'])->test(Dashboard::class)->assertDontSeeHtml('data-test="chamber-filter"');
});

it('shows the total value on the stock screen and recalculates it with the filters', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 3);
    stockUp($w, $w['alas'], 2, $w['n1']);

    Livewire::actingAs($w['admin'])->test(Stock::class)
        ->assertSeeInOrder(['Valor total', '$500.00'])
        ->set('productId', $w['pollo']->id)
        ->assertSee('$300.00')
        ->set('productId', null)
        ->set('chamberId', $w['n1']->id)
        ->assertSee('$200.00');

    Livewire::actingAs($w['centroUser'])->test(Stock::class)
        ->assertDontSeeHtml('data-test="stock-value"')
        ->assertDontSee('Valor total')
        ->assertDontSee('$300.00')
        ->assertSee('$100.00');

    Livewire::actingAs($w['centroUser'])->test(Stock::class)->set('view', 'summary')
        ->assertDontSee('Valor total')
        ->assertDontSee('$300.00');
});

it('searches the stock by barcode or by product name', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 2);
    stockUp($w, $w['alas'], 3);
    $barcode = Box::query()->where('product_id', $w['alas']->id)->orderBy('id')->value('barcode');

    $component = Livewire::actingAs($w['admin'])->test(Stock::class);

    $component->set('search', 'alas');
    expect($component->viewData('summary')->boxes)->toBe(3);

    $component->set('search', $barcode);
    expect($component->viewData('summary')->boxes)->toBe(1);

    $component->set('search', 'POLLO');
    expect($component->viewData('summary')->boxes)->toBe(5);
});

it('summarises and filters the entries list', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 3);
    stockUp($w, $w['alas'], 2, $w['n1']);
    $reference = Entry::query()->orderBy('id')->value('reference');

    $component = Livewire::actingAs($w['admin'])->test(Entries::class);
    expect((int) $component->viewData('summary')->boxes)->toBe(5)
        ->and((string) $component->viewData('summary')->cost)->toBe('500.00');

    $component->set('chamberId', $w['n1']->id);
    expect((int) $component->viewData('summary')->boxes)->toBe(2);

    $component->call('clearFilters')->set('search', $reference);
    expect((int) $component->viewData('summary')->entries)->toBe(1);

    $component->call('clearFilters')->set('from', now()->addDay()->toDateString());
    expect((int) $component->viewData('summary')->entries)->toBe(0);
    $component->assertSee('No hay entradas que coincidan');

    $storekeeper = Livewire::actingAs($w['centroUser'])->test(Entries::class)->assertDontSee('Importe')->assertSeeHtml('data-test="entry-unit-cost"')->assertSee('Precio por caja')->assertDontSee('Norte 1');
    expect((int) $storekeeper->viewData('summary')->boxes)->toBe(3);
});

it('summarises and filters the exits list', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 6);
    $boxIds = Box::query()->orderBy('id')->pluck('id')->all();
    app(RegisterExit::class)->handle($w['admin'], $w['c1'], $w['sur'], null, 'Ana', array_slice($boxIds, 0, 2));
    app(RegisterExit::class)->handle($w['admin'], $w['c1'], $w['norte'], $w['n1'], 'Luis', array_slice($boxIds, 2, 3));

    $component = Livewire::actingAs($w['admin'])->test(Exits::class);
    expect((int) $component->viewData('summary')->exits)->toBe(2)
        ->and((int) $component->viewData('summary')->boxes)->toBe(5)
        ->and((string) $component->viewData('summary')->cost)->toBe('500.00');

    $component->set('type', 'transfer');
    expect((int) $component->viewData('summary')->boxes)->toBe(3);

    $component->set('type', 'delivery')->set('search', 'ana');
    expect((int) $component->viewData('summary')->boxes)->toBe(2);

    $component->call('clearFilters')->set('search', 'nadie')->assertSee('No hay salidas que coincidan');

    Livewire::actingAs($w['norteUser'])->test(Exits::class)->assertDontSee('Costo histórico');
});

it('counts transfers by status and filters the transfers list', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 6);
    $boxIds = Box::query()->orderBy('id')->pluck('id')->all();
    app(RegisterExit::class)->handle($w['admin'], $w['c1'], $w['norte'], $w['n1'], 'Luis', array_slice($boxIds, 0, 2));
    $second = app(RegisterExit::class)->handle($w['admin'], $w['c1'], $w['norte'], $w['n1'], 'Marta', array_slice($boxIds, 2, 1));
    app(CancelTransfer::class)->handle($w['admin'], $second->transfer, 'Error de destino');

    $component = Livewire::actingAs($w['admin'])->test(Transfers::class);
    expect($component->viewData('counts'))->toBe(['in_transit' => 1, 'received' => 0, 'cancelled' => 1, 'incidents' => 0]);

    $component->set('status', 'cancelled');
    expect($component->viewData('transfers')->total())->toBe(1)
        ->and($component->viewData('counts')['in_transit'])->toBe(1);

    $component->call('clearFilters')->set('search', 'marta');
    expect($component->viewData('transfers')->total())->toBe(1);

    $component->call('clearFilters')->set('search', 'nadie')->assertSee('No hay transferencias que coincidan');
});

it('counts branches and chambers and filters the branches list', function () {
    $w = widgetsWorld();
    $w['norte']->update(['is_active' => false]);

    $component = Livewire::actingAs($w['admin'])->test(Branches::class);
    expect($component->viewData('totals'))->toBe(['total' => 3, 'active' => 2, 'inactive' => 1, 'chambers' => 2]);

    $component->set('status', 'inactive');
    expect($component->viewData('branches')->total())->toBe(1)
        ->and($component->viewData('totals')['total'])->toBe(3);

    $component->call('clearFilters')->set('search', 'sur');
    expect($component->viewData('branches')->total())->toBe(1)
        ->and($component->viewData('totals'))->toBe(['total' => 1, 'active' => 1, 'inactive' => 0, 'chambers' => 0]);

    $component->call('clearFilters')->set('search', 'nadie')->assertSee('No hay sucursales que coincidan');
});

it('counts chambers and stored boxes and filters the chambers list', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 4);
    stockUp($w, $w['alas'], 2, $w['n1']);
    $w['n1']->update(['is_active' => false]);

    $component = Livewire::actingAs($w['admin'])->test(Chambers::class);
    expect($component->viewData('totals'))->toBe(['total' => 2, 'active' => 1, 'inactive' => 1, 'boxes' => 6]);

    $component->set('status', 'inactive');
    expect($component->viewData('chambers')->total())->toBe(1)
        ->and($component->viewData('totals')['total'])->toBe(2);

    $component->call('clearFilters')->set('branchFilter', $w['centro']->id);
    expect($component->viewData('totals'))->toBe(['total' => 1, 'active' => 1, 'inactive' => 0, 'boxes' => 4]);

    $component->call('clearFilters')->set('search', 'nadie')->assertSee('No hay cámaras que coincidan');
});

it('counts categories and products and filters the categories list', function () {
    $w = widgetsWorld();
    Category::factory()->create(['name' => 'RES', 'is_active' => false]);

    $component = Livewire::actingAs($w['admin'])->test(Categories::class);
    expect($component->viewData('totals'))->toBe(['total' => 2, 'active' => 1, 'inactive' => 1, 'products' => 2]);

    $component->set('status', 'inactive');
    expect($component->viewData('categories')->total())->toBe(1)
        ->and($component->viewData('totals')['total'])->toBe(2);

    $component->call('clearFilters')->set('search', 'pollo');
    expect($component->viewData('totals'))->toBe(['total' => 1, 'active' => 1, 'inactive' => 0, 'products' => 2]);

    $component->call('clearFilters')->set('search', 'nadie')->assertSee('No hay categorías que coincidan');
});

it('counts products and stocked products and filters the products list', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 3);
    $w['alas']->update(['is_active' => false]);

    $component = Livewire::actingAs($w['admin'])->test(Products::class);
    expect($component->viewData('totals'))->toBe(['total' => 2, 'active' => 1, 'inactive' => 1, 'stocked' => 1]);

    $component->set('status', 'inactive');
    expect($component->viewData('products')->total())->toBe(1)
        ->and($component->viewData('totals')['total'])->toBe(2);

    $component->call('clearFilters')->set('search', 'pollo entero');
    expect($component->viewData('totals'))->toBe(['total' => 1, 'active' => 1, 'inactive' => 0, 'stocked' => 1])
        ->and($component->viewData('products')->first()->stocked_boxes_count)->toBe(3);

    $component->call('clearFilters')->set('categoryFilter', Category::factory()->create()->id);
    expect($component->viewData('totals')['total'])->toBe(0);
    $component->assertSee('No hay productos que coincidan');
});

it('counts users by role and filters the users list', function () {
    $w = widgetsWorld();
    $w['norteUser']->forceFill(['is_active' => false])->save();
    $viewer = User::factory()->admin()->create(['name' => 'Consulta Demo', 'email' => 'consulta.demo@example.com']);

    $component = Livewire::actingAs($w['admin'])->test(Users::class);
    expect($component->viewData('totals'))->toBe(['total' => 4, 'super_admin' => 1, 'admin' => 1, 'manager' => 0, 'warehouse' => 2, 'inactive' => 1]);

    $component->set('roleFilter', 'warehouse');
    expect($component->viewData('users')->total())->toBe(2)
        ->and($component->viewData('totals')['total'])->toBe(4);

    $component->call('clearFilters')->set('status', 'inactive');
    expect($component->viewData('users')->total())->toBe(1);

    $component->call('clearFilters')->set('search', 'consulta.demo');
    expect($component->viewData('users')->pluck('id')->all())->toBe([$viewer->id])
        ->and($component->viewData('totals')['total'])->toBe(1);

    $component->call('clearFilters')->set('search', 'nadie')->assertSee('No hay usuarios que coincidan');
});

it('counts incidents and filters the incidents list', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 4);
    $boxIds = Box::query()->orderBy('id')->pluck('id')->all();
    $exit = app(RegisterExit::class)->handle($w['admin'], $w['c1'], $w['norte'], $w['n1'], 'Luis', array_slice($boxIds, 0, 3));
    $itemIds = $exit->transfer->items()->pluck('box_id')->all();
    app(ReceiveTransfer::class)->handle($w['admin'], $exit->transfer, array_slice($itemIds, 0, 2), [$itemIds[0] => '9.500']);

    $component = Livewire::actingAs($w['admin'])->test(Incidents::class);
    expect($component->viewData('totals'))->toBe(['open' => 2, 'resolved' => 0, 'missing' => 1, 'weight' => 1]);

    $component->set('type', 'missing');
    expect($component->viewData('incidents')->total())->toBe(1)
        ->and($component->viewData('totals')['open'])->toBe(1);

    $component->call('clearFilters')->set('search', $exit->transfer->reference);
    expect($component->viewData('incidents')->total())->toBe(2);

    $component->call('clearFilters')->set('status', 'resolved')->assertSee('No hay incidencias que coincidan');
});

it('counts adjustments by kind and filters the adjustments list', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 3);
    $boxes = Box::query()->orderBy('id')->get();
    $adjust = app(AdjustInventory::class);
    $adjust->handle($w['admin'], $boxes[0], AdjustmentType::WeightCorrection, 'Báscula descalibrada', '9.500');
    $adjust->handle($w['admin'], $boxes[1], AdjustmentType::Lost, 'Se perdió en el traslado');
    $adjust->handle($w['admin'], $boxes[2], AdjustmentType::Damaged, 'Caja aplastada');

    $component = Livewire::actingAs($w['admin'])->test(Adjustments::class);
    expect($component->viewData('totals'))->toBe(['total' => 3, 'weight' => 1, 'losses' => 2, 'found' => 0]);

    $component->set('typeFilter', 'lost');
    expect($component->viewData('adjustments')->total())->toBe(1)
        ->and($component->viewData('totals')['total'])->toBe(3);

    $component->call('clearFilters')->set('search', 'aplastada');
    expect($component->viewData('adjustments')->total())->toBe(1)
        ->and($component->viewData('totals')['total'])->toBe(1);

    $component->call('clearFilters')->set('from', now()->addDay()->toDateString())->assertSee('No hay ajustes que coincidan');
});

it('summarises the audit log, shows changes as readable lines and filters it', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 2);
    $box = Box::query()->orderBy('id')->first();
    app(AdjustInventory::class)->handle($w['admin'], $box, AdjustmentType::WeightCorrection, 'Báscula descalibrada', '9.000');
    app(AdjustInventory::class)->handle($w['admin'], $box->fresh(), AdjustmentType::Damaged, 'Caja aplastada');

    $component = Livewire::actingAs($w['admin'])->test(AuditLogs::class);
    $total = $component->viewData('totals');
    expect($total['total'])->toBeGreaterThanOrEqual(2)
        ->and($total['today'])->toBe($total['total'])
        ->and($total['users'])->toBe(1);
    $component->assertSee('Inventario ajustado');

    $component->set('event', 'inventory.adjusted');
    expect($component->viewData('totals')['total'])->toBe(2)
        ->and($component->viewData('totals')['events'])->toBe(1);

    $component->call('clearFilters')->set('userId', $w['centroUser']->id)->assertSee('No hay registros de auditoría que coincidan');
});

it('notifies the destination of incoming transfers and the origin of its pending shipments, not the super administrator', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 25);
    $boxIds = Box::query()->orderBy('id')->pluck('id')->all();
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Luis', array_slice($boxIds, 0, 2));
    $reference = $exit->transfer->reference;

    Livewire::actingAs($w['norteUser'])->test(NotificationsBell::class)
        ->assertSee($reference)->assertSee('Centro te envió 2 cajas')->assertSee('Por recibir')->assertSeeHtml('data-test="bell-tab-transfers"');

    Livewire::actingAs($w['centroUser'])->test(NotificationsBell::class)
        ->assertSee('Enviaste 2 cajas a Norte')->assertSee('Esperando recepción');

    $superAdmin = Livewire::actingAs($w['admin'])->test(NotificationsBell::class)->assertDontSeeHtml('data-test="bell-tab-transfers"');
    expect($superAdmin->viewData('transfers'))->toHaveCount(0);

    app(ReceiveTransfer::class)->handle($w['norteUser'], $exit->transfer, $exit->transfer->items()->pluck('box_id')->all());
    expect(Livewire::actingAs($w['norteUser'])->test(NotificationsBell::class)->viewData('transfers'))->toHaveCount(0)
        ->and(Livewire::actingAs($w['centroUser'])->test(NotificationsBell::class)->viewData('transfers'))->toHaveCount(0);
});

it('stores the reception comments and shows the whole story of a transfer in the detail page', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 4);
    $boxIds = Box::query()->orderBy('id')->pluck('id')->all();
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Luis', array_slice($boxIds, 0, 3));
    $transfer = $exit->transfer;
    $itemIds = $transfer->items()->pluck('box_id')->all();

    Livewire::actingAs($w['norteUser'])->test(TransferDetail::class, ['transfer' => $transfer])
        ->assertSee('En tránsito desde hace')
        ->call('askReceive', $transfer->id)
        ->set('received', [$itemIds[0], $itemIds[1]])
        ->set('weights', [$itemIds[0] => '9.500'])
        ->set('receiveNotes', 'La caja tres no venía en el camión')
        ->call('receive')
        ->assertHasNoErrors();

    expect($transfer->fresh()->receive_notes)->toBe('La caja tres no venía en el camión');

    $detail = Livewire::actingAs($w['admin'])->test(TransferDetail::class, ['transfer' => $transfer])
        ->assertSee('Recibida con 2 incidencias')
        ->assertSee('La caja tres no venía en el camión')
        ->assertSee('Diferencia de peso')
        ->assertSee('Faltante')
        ->assertSee('Peso esperado 10.000 kg, recibido 9.500 kg');
    expect($detail->viewData('incidents'))->toHaveCount(2);

    $complete = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Ana', [$boxIds[3]])->transfer;
    app(ReceiveTransfer::class)->handle($w['norteUser'], $complete, [$boxIds[3]]);
    Livewire::actingAs($w['norteUser'])->test(TransferDetail::class, ['transfer' => $complete])->assertSee('Recibida completa y sin incidencias');

    Livewire::actingAs($w['norteUser'])->test(Transfers::class)->assertSee('2 incidencias');
});

it('keeps the transfer detail from users whose chambers are not involved', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 1);
    $transfer = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Luis', Box::query()->pluck('id')->all())->transfer;

    $sur = Chamber::factory()->for($w['sur'])->create();
    $surUser = User::factory()->warehouse($w['sur'])->create();
    $surUser->chambers()->attach($sur);

    Livewire::actingAs($surUser)->test(TransferDetail::class, ['transfer' => $transfer])->assertForbidden();
    $this->actingAs($surUser)->get(route('transfers.ticket', $transfer))->assertForbidden();
    $this->actingAs($surUser)->get(route('transfers.export', [$transfer, 'pdf']))->assertForbidden();
});

it('shows the cancel button of a transfer only to the super administrator', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 2);
    $boxIds = Box::query()->orderBy('id')->pluck('id')->all();
    $transfer = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Luis', $boxIds)->transfer;
    $cancel = 'wire:click="askCancel('.$transfer->id.')"';
    $receive = 'wire:click="askReceive('.$transfer->id.')"';

    Livewire::actingAs($w['centroUser'])->test(Transfers::class)->assertDontSeeHtml($cancel)->assertDontSeeHtml($receive);
    Livewire::actingAs($w['norteUser'])->test(Transfers::class)->assertSeeHtml($receive)->assertDontSeeHtml($cancel);
    Livewire::actingAs($w['admin'])->test(Transfers::class)->assertSeeHtml($cancel)->assertSeeHtml($receive);

    app(ReceiveTransfer::class)->handle($w['norteUser'], $transfer, $boxIds);

    foreach (['centroUser', 'norteUser'] as $role) {
        Livewire::actingAs($w[$role])->test(Transfers::class)->assertDontSeeHtml($cancel)->assertDontSeeHtml($receive);
    }
    Livewire::actingAs($w['norteUser'])->test(Transfers::class)->call('askCancel', $transfer->id)->assertForbidden();

    Livewire::actingAs($w['admin'])->test(Transfers::class)
        ->assertSeeHtml($cancel)->assertDontSeeHtml($receive)
        ->call('askCancel', $transfer->id)
        ->assertSee('Esta transferencia ya fue recibida')
        ->set('reason', 'Se recibió por equivocación')
        ->call('cancel')
        ->assertHasNoErrors();

    expect($transfer->fresh()->status)->toBe(TransferStatus::Cancelled);
});

it('shows the products of one chamber at a time, starting with a default chamber', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 12, $w['c1']);
    stockUp($w, $w['alas'], 30, $w['n1']);

    $first = Livewire::actingAs($w['admin'])->test(Dashboard::class)->assertSeeHtml('data-test="products-chamber-filter"');
    expect($first->viewData('tableChamber')->id)->toBe($w['c1']->id)
        ->and($first->viewData('productStock')->pluck('product')->all())->toBe(['Pollo entero'])
        ->and((int) $first->viewData('productStock')[0]->boxes)->toBe(12)
        ->and((float) $first->viewData('productStock')[0]->value)->toBe(1200.0);

    $other = $first->set('tableChamberId', $w['n1']->id);
    expect($other->viewData('productStock')->pluck('product')->all())->toBe(['Alas de pollo'])
        ->and($other->viewData('productStock')->sum('boxes'))->toBe(30);

    $tampered = Livewire::actingAs($w['centroUser'])->test(Dashboard::class)->set('tableChamberId', $w['n1']->id);
    expect($tampered->viewData('tableChamber')->id)->toBe($w['c1']->id)
        ->and($tampered->viewData('productStock')->pluck('product')->all())->not->toContain('Alas de pollo');
});

it('keeps the value of the products table away from warehouse users', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 12, $w['c1']);

    Livewire::actingAs($w['admin'])->test(Dashboard::class)->assertSeeHtml('data-test="product-value"');
    $warehouse = Livewire::actingAs($w['centroUser'])->test(Dashboard::class)->assertSee('Pollo entero')->assertDontSeeHtml('data-test="product-value"');
    expect($warehouse->viewData('productStock')[0])->not->toHaveProperty('value');
});

it('filters the products table by category and by product name or code', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 12, $w['c1']);
    stockUp($w, $w['alas'], 5, $w['c1']);

    $component = Livewire::actingAs($w['admin'])->test(Dashboard::class);
    expect($component->viewData('productStock'))->toHaveCount(2)
        ->and($component->viewData('tableCategories'))->not->toBeEmpty();

    $categoryId = $w['pollo']->category_id;
    $byCategory = $component->set('tableCategoryId', $categoryId);
    expect($byCategory->viewData('productStock')->pluck('category_id')->unique()->all())->toBe([$categoryId]);

    $byName = Livewire::actingAs($w['admin'])->test(Dashboard::class)->set('tableSearch', 'alas');
    expect($byName->viewData('productStock')->pluck('product')->all())->toBe(['Alas de pollo']);
    $byName->set('tableSearch', 'zzz')->assertSee('Ningún producto coincide con los filtros.');

    $byCode = Livewire::actingAs($w['admin'])->test(Dashboard::class)->set('tableSearch', strtolower($w['pollo']->code));
    expect($byCode->viewData('productStock')->pluck('product')->all())->toContain('Pollo entero');

    $stale = Livewire::actingAs($w['admin'])->test(Dashboard::class)->set('tableCategoryId', 999999);
    expect($stale->viewData('productStock'))->toHaveCount(2);
});

it('concentrates every chamber in the products table when "all chambers" is chosen', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 12, $w['c1']);
    stockUp($w, $w['alas'], 30, $w['n1']);

    $all = Livewire::actingAs($w['admin'])->test(Dashboard::class)->set('tableChamberId', 0)
        ->assertSeeHtml('data-test="chamber-summary"')
        ->assertSeeHtml('data-test="chamber-value"')
        ->assertSee('Todas las cámaras (concentrado)');
    expect($all->viewData('tableAll'))->toBeTrue()
        ->and($all->viewData('chamberSummary')->pluck('boxes')->map(fn ($boxes) => (int) $boxes)->all())->toBe([12, 30]);

    $byCategory = $all->set('tableCategoryId', $w['alas']->category_id);
    expect($byCategory->viewData('chamberSummary')->sum('boxes'))->toBe(42)
        ->and($byCategory->viewData('tableCategoryId'))->toBe($w['alas']->category_id);

    $bySearch = $all->set('tableCategoryId', null)->set('tableSearch', 'pollo entero');
    expect($bySearch->viewData('chamberSummary')->sum('boxes'))->toBe(12);

    Livewire::actingAs($w['centroUser'])->test(Dashboard::class)->set('tableChamberId', 0)
        ->assertDontSee('Todas las cámaras (concentrado)')
        ->assertDontSeeHtml('data-test="chamber-summary"');
});

it('draws a pie chart of the boxes of the products table and follows its filters', function () {
    $w = widgetsWorld();
    stockUp($w, $w['pollo'], 12, $w['c1']);
    stockUp($w, $w['alas'], 8, $w['c1']);
    stockUp($w, $w['alas'], 30, $w['n1']);

    Livewire::actingAs($w['admin'])->test(Dashboard::class)
        ->assertSeeHtml('data-test="chamber-pie"')
        ->assertSee('Cajas por producto')
        ->assertSeeHtml('Pollo entero: 12 cajas (60.0%)')
        ->assertSeeHtml('Alas de pollo: 8 cajas (40.0%)')
        ->set('tableSearch', 'alas')
        ->assertDontSeeHtml('Pollo entero: 12 cajas')
        ->assertSeeHtml('Alas de pollo: 8 cajas (100.0%)')
        ->set('tableSearch', '')
        ->set('tableChamberId', 0)
        ->assertSee('Cajas por cámara')
        ->assertSeeHtml('Centro 1: 20 cajas (40.0%)')
        ->assertSeeHtml('Norte 1: 30 cajas (60.0%)');

    Livewire::actingAs($w['admin'])->test(Dashboard::class)->set('tableSearch', 'zzz')->assertSee('Sin datos para graficar.');
});
