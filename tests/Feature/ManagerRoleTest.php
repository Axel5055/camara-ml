<?php

use App\Actions\Users\AssignManagerScope;
use App\Enums\IncidentStatus;
use App\Enums\UserRole;
use App\Exceptions\InventoryException;
use App\Livewire\Admin\Chambers;
use App\Livewire\Admin\Incidents;
use App\Livewire\Admin\Products;
use App\Livewire\Admin\Users;
use App\Livewire\Inventory\Entries;
use App\Livewire\Inventory\EntryDetail;
use App\Livewire\Inventory\EntryRequests;
use App\Livewire\Reports\EntriesReport;
use App\Livewire\Reports\InventoryReport;
use App\Livewire\Reports\LowStock;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Chamber;
use App\Models\Entry;
use App\Models\EntryRequest;
use App\Models\Incident;
use App\Models\Product;
use App\Models\StockExit;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Inventory\CancelTransfer;
use App\Services\Inventory\CorrectEntry;
use App\Services\Inventory\CorrectExit;
use App\Services\Inventory\CorrectTransfer;
use App\Services\Inventory\RegisterEntry;
use App\Services\Inventory\RegisterExit;
use App\Services\Inventory\ReverseEntry;
use App\Services\Inventory\ReverseExit;
use App\Services\Inventory\ReviewEntryRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * Centro (2 chambers), Norte (2 chambers) and Sur (1 chamber), each with one entry. The manager gets all of
 * Centro plus the individual chamber Norte 1.
 *
 * @return array<string, mixed>
 */
function managerWorld(): array
{
    $centro = Branch::factory()->create(['name' => 'Centro']);
    $norte = Branch::factory()->create(['name' => 'Norte']);
    $sur = Branch::factory()->create(['name' => 'Sur']);
    $c1 = Chamber::factory()->for($centro)->create(['name' => 'Centro 1']);
    $c2 = Chamber::factory()->for($centro)->create(['name' => 'Centro 2']);
    $n1 = Chamber::factory()->for($norte)->create(['name' => 'Norte 1']);
    $n2 = Chamber::factory()->for($norte)->create(['name' => 'Norte 2']);
    $s1 = Chamber::factory()->for($sur)->create(['name' => 'Sur 1']);

    $superAdmin = User::factory()->superAdmin()->create();
    $manager = User::factory()->manager()->create();
    app(AssignManagerScope::class)->handle($superAdmin, $manager, [$centro->id], [$n1->id]);

    $product = Product::factory()->create();
    $entries = [];
    foreach ([$c1, $c2, $n1, $n2, $s1] as $chamber) {
        $worker = User::factory()->warehouse($chamber->branch)->create();
        $worker->chambers()->attach($chamber);
        $entries[$chamber->name] = app(RegisterEntry::class)->handle($worker, $chamber, [
            ['product_id' => $product->id, 'boxes_count' => 1, 'weight_per_box_kg' => '10', 'unit_cost_mxn' => '100'],
        ]);
    }

    return compact('centro', 'norte', 'sur', 'c1', 'c2', 'n1', 'n2', 's1', 'superAdmin', 'manager', 'entries');
}

it('limits a manager to the assigned branches and chambers', function () {
    $w = managerWorld();

    $visible = Chamber::query()->accessibleBy($w['manager'])->orderBy('name')->pluck('name')->all();
    $branches = Branch::query()->visibleTo($w['manager'])->orderBy('name')->pluck('name')->all();

    expect($visible)->toBe(['Centro 1', 'Centro 2', 'Norte 1'])
        ->and($branches)->toBe(['Centro', 'Norte'])
        ->and($w['manager']->canViewChamber($w['c2']))->toBeTrue()
        ->and($w['manager']->canViewChamber($w['n1']))->toBeTrue()
        ->and($w['manager']->canViewChamber($w['n2']))->toBeFalse()
        ->and($w['manager']->canViewChamber($w['s1']))->toBeFalse();
});

it('shows a manager only the records of their scope', function () {
    $w = managerWorld();
    $this->actingAs($w['manager']);

    $this->get(route('entries.show', $w['entries']['Centro 2']))->assertOk();
    $this->get(route('entries.show', $w['entries']['Norte 1']))->assertOk();
    $this->get(route('entries.show', $w['entries']['Norte 2']))->assertForbidden();
    $this->get(route('entries.show', $w['entries']['Sur 1']))->assertForbidden();

    $outsideBox = Box::query()->where('current_chamber_id', $w['s1']->id)->firstOrFail();
    $this->get(route('boxes.show', $outsideBox))->assertForbidden();

    Livewire::test(Entries::class)
        ->assertSee($w['entries']['Centro 1']->reference)
        ->assertSee($w['entries']['Norte 1']->reference)
        ->assertDontSee($w['entries']['Norte 2']->reference)
        ->assertDontSee($w['entries']['Sur 1']->reference);
});

it('shows a manager only their branches and chambers in the catalogs', function () {
    $w = managerWorld();
    $this->actingAs($w['manager']);

    $this->get(route('admin.branches'))->assertForbidden();
    $this->get(route('admin.categories'))->assertOk();
    $this->get(route('admin.chambers'))->assertOk()->assertDontSee(route('admin.branches'), false);

    Livewire::test(Chambers::class)->assertSee('Centro 2')->assertSee('Norte 1')->assertDontSee('Norte 2')->assertDontSee('Sur 1');
});

it('keeps managers read-only and away from super administrator screens', function () {
    $w = managerWorld();
    $this->actingAs($w['manager']);

    expect(Gate::allows('view-costs'))->toBeTrue()
        ->and(Gate::allows('operate-inventory'))->toBeFalse()
        ->and(Gate::allows('create', [Entry::class, $w['c1']]))->toBeFalse()
        ->and(Gate::allows('reverse', $w['entries']['Centro 1']))->toBeTrue()
        ->and(Gate::allows('reverse', $w['entries']['Sur 1']))->toBeFalse();

    $this->get(route('entries.create'))->assertForbidden();
    $this->get(route('admin.users'))->assertForbidden();
    $this->get(route('admin.audit'))->assertForbidden();
    expect(fn () => app(RegisterEntry::class)->handle($w['manager'], $w['c1'], []))->toThrow(AuthorizationException::class);
});

it('lets a manager decide entry requests only inside their scope', function () {
    $w = managerWorld();
    $worker = User::factory()->warehouse($w['norte'])->create();
    $worker->chambers()->attach([$w['n1']->id, $w['n2']->id]);

    $inside = EntryRequest::factory()->create(['user_id' => $worker->id, 'chamber_id' => $w['n1']->id]);
    $outside = EntryRequest::factory()->create(['user_id' => $worker->id, 'chamber_id' => $w['n2']->id]);

    expect($w['manager']->can('decide', $inside))->toBeTrue()
        ->and($w['manager']->can('decide', $outside))->toBeFalse()
        ->and($w['manager']->can('view', $outside))->toBeFalse();
});

it('assigns a manager scope from the users screen', function () {
    $w = managerWorld();
    $this->actingAs($w['superAdmin']);

    Livewire::test(Users::class)
        ->call('create')
        ->set('name', 'Gerente Nuevo')
        ->set('email', 'gerente@example.com')
        ->set('password', 'password-123')
        ->set('role', UserRole::Manager->value)
        ->set('branchIds', [$w['sur']->id])
        ->set('chamberIds', [$w['c1']->id])
        ->call('save')
        ->assertHasNoErrors();

    $created = User::query()->where('email', 'gerente@example.com')->firstOrFail();

    expect($created->role)->toBe(UserRole::Manager)
        ->and($created->branch_id)->toBeNull()
        ->and($created->managedBranches()->pluck('branches.id')->all())->toBe([$w['sur']->id])
        ->and($created->chambers()->pluck('chambers.id')->all())->toBe([$w['c1']->id]);
});

it('requires at least one branch or chamber for a manager and only a super administrator can assign them', function () {
    $w = managerWorld();
    $other = User::factory()->manager()->create();

    expect(fn () => app(AssignManagerScope::class)->handle($w['superAdmin'], $other, [], []))->toThrow(InventoryException::class)
        ->and(fn () => app(AssignManagerScope::class)->handle(User::factory()->admin()->create(), $other, [$w['sur']->id], []))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AssignManagerScope::class)->handle($w['superAdmin'], User::factory()->admin()->create(), [$w['sur']->id], []))->toThrow(InventoryException::class);
});

it('offers a password suggestion button in the user form', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(Users::class)
        ->call('create')
        ->assertSeeHtml('data-test="suggest-password"');
});

it('lets a manager open the reports limited to their scope', function () {
    $w = managerWorld();
    $this->actingAs($w['manager']);

    foreach (['reports.inventory', 'reports.entries', 'reports.exits', 'reports.movements', 'reports.low-stock'] as $name) {
        $this->get(route($name))->assertOk();
    }

    $entries = Livewire::test(EntriesReport::class);
    expect($entries->viewData('rows')->pluck('chamber')->unique()->sort()->values()->all())->toBe(['Centro 1', 'Centro 2', 'Norte 1'])
        ->and($entries->viewData('chambers')->pluck('name')->sort()->values()->all())->toBe(['Centro 1', 'Centro 2', 'Norte 1']);

    $inventory = Livewire::test(InventoryReport::class);
    expect($inventory->viewData('rows')->pluck('label')->sort()->values()->all())->toBe(['Centro 1', 'Centro 2', 'Norte 1'])
        ->and((int) $inventory->viewData('totals')->boxes)->toBe(3);

    $lowStock = Livewire::test(LowStock::class);
    expect($lowStock->viewData('rows')->pluck('chamber')->unique()->sort()->values()->all())->toBe(['Centro 1', 'Centro 2', 'Norte 1']);
});

it('keeps the reports closed to the read-only administrator and the thresholds closed to managers', function () {
    $w = managerWorld();

    $this->actingAs(User::factory()->admin()->create());
    $this->get(route('reports.inventory'))->assertForbidden();

    $this->actingAs($w['manager']);
    Livewire::test(LowStock::class)
        ->set('thresholdChamberId', $w['c1']->id)
        ->set('thresholdProductId', Product::query()->value('id'))
        ->set('minBoxes', '5')
        ->call('saveThreshold')
        ->assertForbidden();
});

it('lets a manager authorize an entry request of their scope and registers the entry for the requester', function () {
    $w = managerWorld();
    $worker = User::factory()->warehouse($w['norte'])->create();
    $worker->chambers()->attach($w['n1']);
    $request = EntryRequest::factory()->create(['user_id' => $worker->id, 'chamber_id' => $w['n1']->id]);

    Livewire::actingAs($w['manager'])->test(EntryRequests::class)
        ->call('openRequest', $request->id)
        ->call('approve')
        ->assertHasNoErrors();

    $request->refresh();
    $entry = Entry::query()->findOrFail($request->entry_id);

    expect($request->status->value)->toBe('approved')
        ->and($request->decided_by)->toBe($w['manager']->id)
        ->and($entry->user_id)->toBe($worker->id)
        ->and($entry->chamber_id)->toBe($w['n1']->id);

    $this->actingAs($w['manager'])->get(route('entries.ticket', $entry))->assertOk();
});

it('lets a manager reject a request of their scope but never see or decide one outside it', function () {
    $w = managerWorld();
    $worker = User::factory()->warehouse($w['norte'])->create();
    $worker->chambers()->attach([$w['n1']->id, $w['n2']->id]);
    $inside = EntryRequest::factory()->create(['user_id' => $worker->id, 'chamber_id' => $w['n1']->id]);
    $outside = EntryRequest::factory()->create(['user_id' => $worker->id, 'chamber_id' => $w['n2']->id]);

    Livewire::actingAs($w['manager'])->test(EntryRequests::class)
        ->call('openRequest', $inside->id)
        ->set('rejectReason', 'Faltan las facturas')
        ->call('reject')
        ->assertHasNoErrors();

    expect($inside->refresh()->status->value)->toBe('rejected');

    Livewire::actingAs($w['manager'])->test(EntryRequests::class)
        ->assertDontSee($outside->chamber->name)
        ->call('openRequest', $outside->id)
        ->assertNotFound();

    expect(fn () => app(ReviewEntryRequest::class)->approve($w['manager'], $outside))
        ->toThrow(AuthorizationException::class)
        ->and($outside->refresh()->status->value)->toBe('pending')
        ->and(Entry::query()->where('chamber_id', $w['n2']->id)->count())->toBe(1);
});

it('starts the sidebar submenus collapsed on every reload for managers only', function () {
    $w = managerWorld();

    $this->actingAs($w['manager'])->get(route('dashboard'))
        ->assertSee('open: (window.navOpen ??= {}).entries ?? false', false)
        ->assertDontSee('$persist(', false);

    $this->actingAs($w['superAdmin'])->get(route('dashboard'))
        ->assertSee('$persist(true)', false)
        ->assertDontSee('open: (window.navOpen', false);
});

it('repeats the pending counters on the collapsible sidebar headings', function () {
    $w = managerWorld();
    $worker = User::factory()->warehouse($w['norte'])->create();
    $worker->chambers()->attach($w['n1']);
    EntryRequest::factory()->create(['user_id' => $worker->id, 'chamber_id' => $w['n1']->id]);
    EntryRequest::factory()->create(['user_id' => $worker->id, 'chamber_id' => $w['n2']->id]);

    $this->actingAs($w['manager'])->get(route('dashboard'))
        ->assertSeeHtml('data-test="nav-entries-badge"')
        ->assertDontSeeHtml('data-test="nav-transfers-badge"');

    $this->actingAs($w['superAdmin'])->get(route('dashboard'))
        ->assertSeeHtml('data-test="nav-entries-badge"');
});

it('lets a manager correct and cancel the entries of their scope', function () {
    $w = managerWorld();
    $this->actingAs($w['manager']);

    Livewire::test(EntryDetail::class, ['entry' => $w['entries']['Norte 1']])
        ->call('openEdit')
        ->set('editNotes', 'Nota corregida por gerencia')
        ->set('editReason', 'Se capturó mal la nota')
        ->call('saveEdit')
        ->assertHasNoErrors();

    expect($w['entries']['Norte 1']->refresh()->notes)->toBe('Nota corregida por gerencia');

    Livewire::test(EntryDetail::class, ['entry' => $w['entries']['Centro 1']])
        ->call('openReverse')
        ->set('reverseReason', 'Entrada duplicada')
        ->call('reverse')
        ->assertHasNoErrors();

    expect($w['entries']['Centro 1']->refresh()->status->value)->toBe('reversed');
});

it('never lets a manager correct or cancel an entry outside their scope', function () {
    $w = managerWorld();
    $this->actingAs($w['manager']);

    expect(fn () => app(ReverseEntry::class)->handle($w['manager'], $w['entries']['Sur 1'], 'Intento fuera de alcance'))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(CorrectEntry::class)->handle($w['manager'], $w['entries']['Norte 2'], 'Intento fuera de alcance', null, 'x'))
        ->toThrow(AuthorizationException::class)
        ->and($w['entries']['Sur 1']->refresh()->status->value)->toBe('confirmed');

    $this->get(route('entries.show', $w['entries']['Sur 1']))->assertForbidden();
    expect(Gate::allows('update', $w['entries']['Norte 2']))->toBeFalse();

    $admin = User::factory()->admin()->create();
    expect($admin->can('update', $w['entries']['Centro 1']))->toBeFalse()
        ->and($admin->can('reverse', $w['entries']['Centro 1']))->toBeFalse();
});

it('lets administrators and managers look at and resolve incidents, managers only within their scope', function () {
    $w = managerWorld();
    $worker = User::factory()->warehouse($w['norte'])->create();
    $worker->chambers()->attach([$w['n1']->id, $w['n2']->id]);

    $makeIncident = function (Chamber $origin, Chamber $dest, string $description) use ($worker): Incident {
        $box = Box::query()->where('current_chamber_id', $origin->id)->firstOrFail();
        $sender = User::factory()->warehouse($origin->branch)->create();
        $sender->chambers()->attach($origin);
        $exit = app(RegisterExit::class)->handle($sender, $origin, $dest->branch, $dest, 'Ana', [$box->id]);

        return Incident::query()->create([
            'transfer_id' => $exit->transfer->id, 'box_id' => $box->id, 'type' => 'missing', 'description' => $description,
            'status' => IncidentStatus::Open, 'reported_by' => $worker->id,
        ]);
    };

    $inside = $makeIncident($w['c1'], $w['c2'], 'Incidencia dentro del alcance');
    $outside = $makeIncident($w['n2'], $w['s1'], 'Incidencia fuera del alcance');

    $this->actingAs($w['manager'])->get(route('admin.incidents'))->assertOk();

    Livewire::actingAs($w['manager'])->test(Incidents::class)
        ->assertSee('Incidencia dentro del alcance')
        ->assertDontSee('Incidencia fuera del alcance')
        ->assertSeeHtml('data-test="incident-resolve"')
        ->call('askResolve', $inside->id)
        ->assertSeeHtml('data-test="resolve-summary"')
        ->assertSee('Incidencia dentro del alcance')
        ->set('resolution', 'Se localizó la caja')
        ->call('resolve')
        ->assertHasNoErrors();

    expect($inside->refresh()->status->value)->toBe('resolved')
        ->and($inside->resolved_by)->toBe($w['manager']->id)
        ->and($w['manager']->can('resolve', $outside))->toBeFalse();

    Livewire::actingAs($w['manager'])->test(Incidents::class)
        ->call('askResolve', $outside->id)
        ->assertForbidden();

    $admin = User::factory()->admin()->create();
    Livewire::actingAs($admin)->test(Incidents::class)
        ->assertSee('Incidencia fuera del alcance')
        ->call('askResolve', $outside->id)
        ->set('resolution', 'Resuelta por administración')
        ->call('resolve')
        ->assertHasNoErrors();

    expect($outside->refresh()->status->value)->toBe('resolved')
        ->and($outside->resolved_by)->toBe($admin->id);

    $this->actingAs($worker)->get(route('admin.incidents'))->assertForbidden();
    expect($worker->can('resolve', $inside))->toBeFalse();
});

it('shows the count of open incidents next to the sidebar entry, within each role scope', function () {
    $w = managerWorld();
    $worker = User::factory()->warehouse($w['norte'])->create();

    $makeIncident = function (Chamber $origin, Chamber $dest) use ($worker): void {
        $box = Box::query()->where('current_chamber_id', $origin->id)->firstOrFail();
        $sender = User::factory()->warehouse($origin->branch)->create();
        $sender->chambers()->attach($origin);
        $exit = app(RegisterExit::class)->handle($sender, $origin, $dest->branch, $dest, 'Ana', [$box->id]);

        Incident::query()->create([
            'transfer_id' => $exit->transfer->id, 'box_id' => $box->id, 'type' => 'missing', 'description' => 'Faltante',
            'status' => IncidentStatus::Open, 'reported_by' => $worker->id,
        ]);
    };

    $this->actingAs($w['manager'])->get(route('dashboard'))->assertDontSeeHtml('data-test="nav-open-incidents"');

    $makeIncident($w['c1'], $w['c2']);
    $makeIncident($w['n2'], $w['s1']);

    $inScope = $this->actingAs($w['manager'])->get(route('dashboard'))->assertSeeHtml('data-test="nav-open-incidents"')->getContent();
    $all = $this->actingAs($w['superAdmin'])->get(route('dashboard'))->assertSeeHtml('data-test="nav-open-incidents"')->getContent();

    expect(preg_match('/nav-open-incidents.*?<\/a>/s', $inScope, $manager))->toBe(1)
        ->and(preg_match('/nav-open-incidents.*?<\/a>/s', $all, $super))->toBe(1)
        ->and($manager[0])->toContain('>1<')
        ->and($super[0])->toContain('>2<');
});

/**
 * A plain exit from $origin to a branch, and a transfer from $origin to $dest, both made by a warehouse user.
 *
 * @return array{exit: StockExit, transfer: Transfer}
 */
function recordsFrom(Chamber $origin, Chamber $dest): array
{
    $sender = User::factory()->warehouse($origin->branch)->create();
    $sender->chambers()->attach($origin);
    $boxes = Box::query()->where('current_chamber_id', $origin->id)->get();
    $product = Product::factory()->create();
    app(RegisterEntry::class)->handle($sender, $origin, [
        ['product_id' => $product->id, 'boxes_count' => 2, 'weight_per_box_kg' => '10', 'unit_cost_mxn' => '100'],
    ]);
    $fresh = Box::query()->where('current_chamber_id', $origin->id)->where('product_id', $product->id)->orderBy('id')->get();

    return [
        'exit' => app(RegisterExit::class)->handle($sender, $origin, $dest->branch, null, 'Ana', [$fresh[0]->id]),
        'transfer' => app(RegisterExit::class)->handle($sender, $origin, $dest->branch, $dest, 'Luis', [$fresh[1]->id])->transfer,
    ];
}

it('lets administrators and managers correct and reverse exits and correct and cancel transfers, managers only inside their scope', function () {
    $w = managerWorld();
    $inside = recordsFrom($w['c1'], $w['c2']);
    $crossing = recordsFrom($w['c1'], $w['n2']);
    $outside = recordsFrom($w['n2'], $w['s1']);
    $admin = User::factory()->admin()->create();

    foreach ([$w['manager'], $admin] as $user) {
        expect($user->can('update', $inside['exit']))->toBeTrue()
            ->and($user->can('reverse', $inside['exit']))->toBeTrue()
            ->and($user->can('correct', $inside['transfer']))->toBeTrue()
            ->and($user->can('cancel', $inside['transfer']))->toBeTrue();
    }

    expect($w['manager']->can('update', $outside['exit']))->toBeFalse()
        ->and($w['manager']->can('reverse', $outside['exit']))->toBeFalse()
        ->and($w['manager']->can('correct', $outside['transfer']))->toBeFalse()
        ->and($w['manager']->can('cancel', $outside['transfer']))->toBeFalse()
        ->and($w['manager']->can('cancel', $crossing['transfer']))->toBeFalse()
        ->and($admin->can('cancel', $outside['transfer']))->toBeTrue();

    app(CorrectExit::class)->handle($w['manager'], $inside['exit'], 'Se capturó mal el solicitante', 'Beto');
    expect($inside['exit']->refresh()->requester_name)->toBe('Beto');

    app(CorrectTransfer::class)->handle($admin, $outside['transfer'], 'Se capturó mal el solicitante', 'Carla');
    expect($outside['transfer']->refresh()->exit->requester_name)->toBe('Carla');

    app(ReverseExit::class)->handle($w['manager'], $inside['exit'], 'Salida capturada por error');
    expect($inside['exit']->refresh()->status->value)->toBe('reversed');

    app(CancelTransfer::class)->handle($w['manager'], $inside['transfer'], 'Transferencia capturada por error');
    expect($inside['transfer']->refresh()->status->value)->toBe('cancelled');

    $this->actingAs($w['manager'])->get(route('exits.show', $crossing['exit']))->assertOk();

    expect(fn () => app(ReverseExit::class)->handle($w['manager'], $outside['exit'], 'Intento fuera de alcance'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CorrectExit::class)->handle($w['manager'], $outside['exit'], 'Intento fuera de alcance', 'X'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CorrectTransfer::class)->handle($w['manager'], $crossing['transfer'], 'Intento fuera de alcance', 'X'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CancelTransfer::class)->handle($w['manager'], $crossing['transfer'], 'Intento fuera de alcance'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CorrectTransfer::class)->handle($w['manager'], recordsFrom($w['c1'], $w['c2'])['transfer'], 'Destino fuera de alcance', 'X', null, null, $w['s1']))->toThrow(AuthorizationException::class);
});

it('lets administrators and managers register products for every chamber, but not edit them', function () {
    $w = managerWorld();
    $product = Product::factory()->create();

    foreach ([$w['manager'], User::factory()->admin()->create()] as $user) {
        expect($user->can('create', Product::class))->toBeTrue()
            ->and($user->can('update', $product))->toBeFalse();

        $this->actingAs($user)->get(route('admin.products'))->assertOk();
    }

    $category = Category::factory()->create();

    Livewire::actingAs($w['manager'])->test(Products::class)
        ->call('create')
        ->set('category_id', $category->id)
        ->set('name', 'Producto del gerente')
        ->call('save')
        ->assertHasNoErrors()
        ->call('edit', $product->id)
        ->assertForbidden();

    expect(Product::query()->where('name', 'PRODUCTO DEL GERENTE')->where('is_active', true)->exists())->toBeTrue();
});

it('shows the system version in the sidebar logo', function () {
    config(['app.version' => '2.3.4']);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('dashboard'))
        ->assertSeeHtml('data-test="app-version"')
        ->assertSee('v2.3.4');
});

it('shows the copyright legend at the bottom of the sidebar', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('dashboard'))
        ->assertSeeHtml('data-test="app-footer"')
        ->assertSee('Todos los derechos reservados')
        ->assertSee('Mycletch');
});
