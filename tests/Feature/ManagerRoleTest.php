<?php

use App\Actions\Users\AssignManagerScope;
use App\Enums\UserRole;
use App\Exceptions\InventoryException;
use App\Livewire\Admin\Branches;
use App\Livewire\Admin\Chambers;
use App\Livewire\Admin\Users;
use App\Livewire\Inventory\Entries;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\Entry;
use App\Models\EntryRequest;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\RegisterEntry;
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

    $this->get(route('admin.branches'))->assertOk();
    $this->get(route('admin.categories'))->assertOk();

    Livewire::test(Branches::class)->assertSee('Centro')->assertSee('Norte')->assertDontSee('Sur');
    Livewire::test(Chambers::class)->assertSee('Centro 2')->assertSee('Norte 1')->assertDontSee('Norte 2')->assertDontSee('Sur 1');
});

it('keeps managers read-only and away from super administrator screens', function () {
    $w = managerWorld();
    $this->actingAs($w['manager']);

    expect(Gate::allows('view-costs'))->toBeTrue()
        ->and(Gate::allows('operate-inventory'))->toBeFalse()
        ->and(Gate::allows('create', [Entry::class, $w['c1']]))->toBeFalse()
        ->and(Gate::allows('reverse', $w['entries']['Centro 1']))->toBeFalse();

    $this->get(route('entries.create'))->assertForbidden();
    $this->get(route('admin.users'))->assertForbidden();
    $this->get(route('reports.inventory'))->assertForbidden();
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
