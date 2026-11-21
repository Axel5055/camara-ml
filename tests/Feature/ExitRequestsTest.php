<?php

use App\Actions\Users\AssignChambers;
use App\Enums\BoxStatus;
use App\Enums\ExitRequestStatus;
use App\Enums\UserRole;
use App\Exceptions\InventoryException;
use App\Livewire\Admin\Users;
use App\Livewire\Dashboard;
use App\Livewire\Inventory\CreateExitRequest;
use App\Livewire\Inventory\ExitRequests;
use App\Livewire\NotificationsBell;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\ExitRequest;
use App\Models\Product;
use App\Models\StockExit;
use App\Models\User;
use App\Services\Inventory\RegisterEntry;
use App\Services\Inventory\RequestExit;
use App\Services\Inventory\ReviewExitRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/**
 * A chamber stocked with 5 boxes of one product, the requester branch (Sur, without chambers, allowed to ask that
 * chamber) and the people around it.
 *
 * @return array<string, mixed>
 */
function exitRequestWorld(): array
{
    $centro = Branch::factory()->create(['name' => 'Centro']);
    $sur = Branch::factory()->create(['name' => 'Sur']);
    $chamber = Chamber::factory()->for($centro)->create(['name' => 'Cámara Centro']);
    $otherChamber = Chamber::factory()->for($centro)->create(['name' => 'Cámara Otra']);

    $super = User::factory()->superAdmin()->create();
    $warehouse = User::factory()->warehouse($centro)->create();
    $warehouse->chambers()->attach($chamber);
    $otherWarehouse = User::factory()->warehouse($centro)->create();
    $otherWarehouse->chambers()->attach($otherChamber);
    $requester = User::factory()->requester($sur)->create();
    $requester->chambers()->attach($chamber);

    $product = Product::factory()->create(['name' => 'POLLO']);

    app(RegisterEntry::class)->handle($warehouse, $chamber, [
        ['product_id' => $product->id, 'boxes_count' => 5, 'weight_per_box_kg' => '10', 'price_per_kg_mxn' => '20.00'],
    ]);

    return compact('centro', 'sur', 'chamber', 'otherChamber', 'super', 'warehouse', 'otherWarehouse', 'requester', 'product');
}

/**
 * @param  array<string, mixed>  $w
 */
function askForBoxes(array $w, int $boxes = 3, ?User $user = null): ExitRequest
{
    return app(RequestExit::class)->handle($user ?? $w['requester'], $w['chamber'], [['product_id' => $w['product']->id, 'boxes' => $boxes]], 'Para el viernes');
}

it('lets a requester ask a chamber for product without touching the stock', function () {
    $w = exitRequestWorld();

    Livewire::actingAs($w['requester'])->test(CreateExitRequest::class)
        ->assertSet('chamberId', $w['chamber']->id)
        ->set('productId', $w['product']->id)->set('quantity', '3')->call('addProduct')
        ->set('notes', 'Para el viernes')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('exit-requests.index'));

    $request = ExitRequest::query()->with('items')->sole();

    expect($request->status)->toBe(ExitRequestStatus::Pending)
        ->and($request->branch_id)->toBe($w['sur']->id)
        ->and($request->user_id)->toBe($w['requester']->id)
        ->and($request->total_boxes_requested)->toBe(3)
        ->and($request->items->sole()->boxes_requested)->toBe(3)
        ->and(Box::query()->where('status', BoxStatus::Available)->count())->toBe(5)
        ->and(StockExit::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event', 'exit_request.created')->exists())->toBeTrue();
});

it('does not duplicate the request when it is sent twice', function () {
    $w = exitRequestWorld();

    $component = Livewire::actingAs($w['requester'])->test(CreateExitRequest::class)
        ->set('productId', $w['product']->id)->set('quantity', '2')->call('addProduct');
    $component->call('save');
    $component->call('save');

    expect(ExitRequest::query()->count())->toBe(1);
});

it('keeps requesters from asking a chamber that was not assigned to them', function () {
    $w = exitRequestWorld();

    expect(fn () => app(RequestExit::class)->handle($w['requester'], $w['otherChamber'], [['product_id' => $w['product']->id, 'boxes' => 1]]))
        ->toThrow(AuthorizationException::class);

    Livewire::actingAs($w['requester'])->test(CreateExitRequest::class)
        ->set('chamberId', $w['otherChamber']->id)
        ->set('productId', $w['product']->id)->set('quantity', '1')->call('addProduct')
        ->call('save')
        ->assertForbidden();

    expect(ExitRequest::query()->count())->toBe(0);
});

it('only lets requesters ask, never other roles', function () {
    $w = exitRequestWorld();

    foreach (['warehouse', 'super'] as $who) {
        expect(fn () => askForBoxes($w, 1, $w[$who]))->toThrow(AuthorizationException::class);
    }

    $this->actingAs($w['warehouse'])->get(route('exit-requests.create'))->assertForbidden();
});

it('validates the lines of a request', function () {
    $w = exitRequestWorld();
    $service = app(RequestExit::class);

    expect(fn () => $service->handle($w['requester'], $w['chamber'], []))->toThrow(InventoryException::class)
        ->and(fn () => $service->handle($w['requester'], $w['chamber'], [['product_id' => $w['product']->id, 'boxes' => 0]]))->toThrow(InventoryException::class)
        ->and(fn () => $service->handle($w['requester'], $w['chamber'], [['product_id' => 999999, 'boxes' => 1]]))->toThrow(InventoryException::class);
});

it('keeps requesters out of everything except their requests', function () {
    $w = exitRequestWorld();

    $this->actingAs($w['requester'])->get(route('exit-requests.index'))->assertOk();
    $this->actingAs($w['requester'])->get(route('exit-requests.create'))->assertOk();

    foreach (['stock', 'entries.index', 'exits.index', 'transfers.index', 'entry-requests.index', 'admin.products', 'printers'] as $route) {
        $this->actingAs($w['requester'])->get(route($route))->assertForbidden();
    }

    Livewire::actingAs($w['requester'])->test(Dashboard::class)->assertRedirect(route('exit-requests.index'));
    expect(Chamber::query()->accessibleBy($w['requester'])->count())->toBe(0)
        ->and($w['requester']->canAccessChamber($w['chamber']))->toBeFalse()
        ->and($w['requester']->canViewChamber($w['chamber']))->toBeFalse();
});

it('shows each requester only the requests of their own branch', function () {
    $w = exitRequestWorld();
    $mine = askForBoxes($w);
    $otherBranch = Branch::factory()->create();
    $other = User::factory()->requester($otherBranch)->create();
    $other->chambers()->attach($w['chamber']);
    $theirs = askForBoxes($w, 1, $other);

    Livewire::actingAs($w['requester'])->test(ExitRequests::class)
        ->assertSee($mine->reference())
        ->assertDontSee($theirs->reference());

    expect($w['requester']->can('view', $theirs))->toBeFalse()
        ->and($w['requester']->can('view', $mine))->toBeTrue();
});

it('lets the warehouse user authorize with the oldest boxes preselected and registers the exit to the requesting branch', function () {
    $w = exitRequestWorld();
    $request = askForBoxes($w, 3);

    Livewire::actingAs($w['warehouse'])->test(ExitRequests::class)
        ->call('openRequest', $request->id)
        ->assertSeeHtml('data-test="request-selected-'.$w['product']->id.'">3</div>')
        ->assertSee('Completo: enviarás todo lo que pidieron.')
        ->call('approve')
        ->assertHasNoErrors();

    $request->refresh();
    $exit = StockExit::query()->sole();

    expect($request->status)->toBe(ExitRequestStatus::Approved)
        ->and($request->decided_by)->toBe($w['warehouse']->id)
        ->and($request->exit_id)->toBe($exit->id)
        ->and($request->total_boxes_approved)->toBe(3)
        ->and($request->items()->sole()->boxes_approved)->toBe(3)
        ->and($exit->dest_branch_id)->toBe($w['sur']->id)
        ->and($exit->requester_name)->toBe($w['requester']->name)
        ->and($exit->total_boxes)->toBe(3)
        ->and(Box::query()->where('status', BoxStatus::Dispatched)->count())->toBe(3)
        ->and(Box::query()->where('status', BoxStatus::Available)->count())->toBe(2)
        ->and(AuditLog::query()->where('event', 'exit_request.approved')->exists())->toBeTrue();
});

it('lets the warehouse user send fewer boxes than were requested', function () {
    $w = exitRequestWorld();
    $request = askForBoxes($w, 4);
    $keep = Box::query()->orderBy('id')->limit(2)->pluck('id')->map(fn (int $id): string => (string) $id)->all();

    Livewire::actingAs($w['warehouse'])->test(ExitRequests::class)
        ->call('openRequest', $request->id)
        ->set('selected', [$w['product']->id => $keep])
        ->call('approve')
        ->assertHasNoErrors();

    $request->refresh();

    expect($request->status)->toBe(ExitRequestStatus::Approved)
        ->and($request->total_boxes_requested)->toBe(4)
        ->and($request->total_boxes_approved)->toBe(2)
        ->and(StockExit::query()->sole()->total_boxes)->toBe(2);
});

it('does not send more boxes than requested nor boxes of another product, and needs at least one', function () {
    $w = exitRequestWorld();
    $request = askForBoxes($w, 2);
    $review = app(ReviewExitRequest::class);
    $ids = Box::query()->pluck('id')->all();
    $otherProduct = Product::factory()->create();

    expect(fn () => $review->approve($w['warehouse'], $request, [$w['product']->id => $ids]))->toThrow(InventoryException::class)
        ->and(fn () => $review->approve($w['warehouse'], $request, [$w['product']->id => []]))->toThrow(InventoryException::class)
        ->and(fn () => $review->approve($w['warehouse'], $request, [$otherProduct->id => [$ids[0]]]))->toThrow(InventoryException::class);

    expect(StockExit::query()->count())->toBe(0)->and($request->fresh()->isPending())->toBeTrue();
});

it('resolves a request only once', function () {
    $w = exitRequestWorld();
    $request = askForBoxes($w, 1);

    $component = Livewire::actingAs($w['warehouse'])->test(ExitRequests::class)->call('openRequest', $request->id);
    $component->call('approve')->assertHasNoErrors();
    $component->call('approve')->assertHasErrors('operation');

    expect(StockExit::query()->count())->toBe(1);
});

it('does not let anyone but the warehouse user of that chamber or a super administrator decide', function () {
    $w = exitRequestWorld();
    $request = askForBoxes($w, 1);
    $boxId = Box::query()->value('id');
    $selection = [$w['product']->id => [$boxId]];

    expect(fn () => app(ReviewExitRequest::class)->approve($w['otherWarehouse'], $request, $selection))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ReviewExitRequest::class)->approve($w['requester'], $request, $selection))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ReviewExitRequest::class)->reject($w['otherWarehouse'], $request, 'No hay existencias'))->toThrow(AuthorizationException::class);

    foreach (['admin' => User::factory()->admin()->create(), 'manager' => User::factory()->manager()->create()] as $reader) {
        expect(fn () => app(ReviewExitRequest::class)->reject($reader, $request, 'No hay existencias'))->toThrow(AuthorizationException::class)
            ->and($reader->can('decide', $request))->toBeFalse();
    }

    app(ReviewExitRequest::class)->approve($w['super'], $request, $selection);

    expect($request->fresh()->status)->toBe(ExitRequestStatus::Approved);
});

it('rejects with a mandatory reason that the requester can read', function () {
    $w = exitRequestWorld();
    $request = askForBoxes($w, 2);

    $component = Livewire::actingAs($w['warehouse'])->test(ExitRequests::class)->call('openRequest', $request->id);
    $component->set('rejectReason', 'no')->call('reject')->assertHasErrors('rejectReason');
    $component->set('rejectReason', 'Ese producto está reservado')->call('reject')->assertHasNoErrors();

    $request->refresh();

    expect($request->status)->toBe(ExitRequestStatus::Rejected)
        ->and($request->decision_notes)->toBe('Ese producto está reservado')
        ->and(StockExit::query()->count())->toBe(0)
        ->and(Box::query()->where('status', BoxStatus::Available)->count())->toBe(5)
        ->and(AuditLog::query()->where('event', 'exit_request.rejected')->exists())->toBeTrue();

    Livewire::actingAs($w['requester'])->test(ExitRequests::class)
        ->call('openRequest', $request->id)
        ->assertSee('Rechazada')
        ->assertSee('Ese producto está reservado')
        ->assertSee($w['warehouse']->name)
        ->assertDontSeeHtml('data-test="request-approve"');
});

it('lets the requester cancel their pending request and nobody else', function () {
    $w = exitRequestWorld();
    $request = askForBoxes($w, 1);

    expect($w['warehouse']->can('cancel', $request))->toBeFalse()
        ->and($w['super']->can('cancel', $request))->toBeFalse();

    Livewire::actingAs($w['requester'])->test(ExitRequests::class)->call('cancelRequest', $request->id)->assertHasNoErrors();

    expect($request->fresh()->status)->toBe(ExitRequestStatus::Cancelled)
        ->and($w['requester']->can('cancel', $request->fresh()))->toBeFalse()
        ->and(AuditLog::query()->where('event', 'exit_request.cancelled')->exists())->toBeTrue();
});

it('lets administrators and scoped managers read the requests without deciding them', function () {
    $w = exitRequestWorld();
    $request = askForBoxes($w);
    $admin = User::factory()->admin()->create();
    $managerIn = User::factory()->manager()->create();
    $managerIn->managedBranches()->attach($w['centro']);
    $managerOut = User::factory()->manager()->create();

    Livewire::actingAs($admin)->test(ExitRequests::class)->assertSee($request->reference());
    Livewire::actingAs($managerIn)->test(ExitRequests::class)->assertSee($request->reference());
    Livewire::actingAs($managerOut)->test(ExitRequests::class)->assertDontSee($request->reference());

    expect($admin->can('view', $request))->toBeTrue()
        ->and($managerIn->can('view', $request))->toBeTrue()
        ->and($managerOut->can('view', $request))->toBeFalse()
        ->and($admin->can('decide', $request))->toBeFalse()
        ->and($managerIn->can('decide', $request))->toBeFalse();
});

it('keeps the warehouse users of other chambers from seeing the request', function () {
    $w = exitRequestWorld();
    $request = askForBoxes($w);

    Livewire::actingAs($w['otherWarehouse'])->test(ExitRequests::class)->assertDontSee($request->reference());
    Livewire::actingAs($w['warehouse'])->test(ExitRequests::class)->assertSee($request->reference());
});

it('notifies the warehouse user of pending requests and the requester of the answers in the bell', function () {
    $w = exitRequestWorld();
    $request = askForBoxes($w, 2);

    Livewire::actingAs($w['warehouse'])->test(NotificationsBell::class)
        ->assertSeeHtml('data-test="bell-count"')
        ->assertSee('Sur pide 2 cajas');

    Livewire::actingAs($w['requester'])->test(NotificationsBell::class)->assertDontSeeHtml('data-test="bell-count"');

    app(ReviewExitRequest::class)->reject($w['warehouse'], $request, 'No hay existencias');

    Livewire::actingAs($w['requester'])->test(NotificationsBell::class)
        ->assertSeeHtml('data-test="bell-count"')
        ->assertSee($request->reference())
        ->assertDontSeeHtml('data-test="bell-tab-stock"');
});

it('shows requesters the stock of nothing and no costs', function () {
    $w = exitRequestWorld();

    expect($w['requester']->can('view-costs'))->toBeFalse()
        ->and($w['requester']->can('view-catalogs'))->toBeFalse()
        ->and($w['requester']->can('operate-inventory'))->toBeFalse()
        ->and($w['requester']->can('use-inventory-area'))->toBeFalse()
        ->and($w['requester']->can('request-exits'))->toBeTrue()
        ->and($w['warehouse']->can('use-inventory-area'))->toBeTrue();
});

it('lets the super administrator create requesters with a branch and the chambers they may ask', function () {
    $w = exitRequestWorld();

    Livewire::actingAs($w['super'])->test(Users::class)
        ->call('create')
        ->set('name', 'Sucursal Sur')->set('email', 'sur@example.com')->set('password', 'Contraseña-Segura-123!')
        ->set('role', UserRole::Requester->value)
        ->set('branch_id', $w['sur']->id)
        ->set('chamberIds', [$w['chamber']->id])
        ->call('save')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'sur@example.com')->sole();

    expect($user->role)->toBe(UserRole::Requester)
        ->and($user->branch_id)->toBe($w['sur']->id)
        ->and($user->chambers()->pluck('chambers.id')->all())->toBe([$w['chamber']->id]);

    Livewire::actingAs($w['super'])->test(Users::class)
        ->call('create')
        ->set('name', 'Sin sucursal')->set('email', 'sin@example.com')->set('password', 'Contraseña-Segura-123!')
        ->set('role', UserRole::Requester->value)
        ->call('save')
        ->assertHasErrors('branch_id');
});

it('lets requesters be assigned chambers of other branches but warehouse users not', function () {
    $w = exitRequestWorld();
    $assign = app(AssignChambers::class);
    $foreign = Chamber::factory()->for($w['sur'])->create();

    $assign->handle($w['super'], $w['requester'], [$w['chamber']->id, $foreign->id]);

    expect($w['requester']->chambers()->count())->toBe(2)
        ->and(fn () => $assign->handle($w['super'], $w['warehouse'], [$foreign->id]))->toThrow(InventoryException::class);
});

it('checks the chosen boxes before asking for the confirmation and names the product that goes over', function () {
    $w = exitRequestWorld();
    $request = askForBoxes($w, 1);
    $ids = Box::query()->orderBy('id')->pluck('id')->map(fn (int $id): string => (string) $id)->all();

    $component = Livewire::actingAs($w['warehouse'])->test(ExitRequests::class)->call('openRequest', $request->id)
        ->assertSeeHtml('data-test="request-summary"')
        ->assertSeeHtml('data-test="request-selected-'.$w['product']->id.'">1</div>')
        ->set('selected', [$w['product']->id => array_slice($ids, 0, 2)])
        ->call('askToApprove')
        ->assertSet('confirming', false)
        ->assertHasErrors('operation')
        ->assertSee('Solo se pidió 1 caja de POLLO; quita 1');

    $component->set('selected', [$w['product']->id => []])->call('askToApprove')->assertSet('confirming', false)->assertHasErrors('operation');

    $component->set('selected', [$w['product']->id => [$ids[3]]])
        ->call('askToApprove')
        ->assertSet('confirming', true)
        ->assertHasNoErrors()
        ->assertSeeHtml('data-test="request-confirm-step"')
        ->call('backToReview')->assertSet('confirming', false);

    expect(StockExit::query()->count())->toBe(0);
});

it('offers to print the ticket of the exit right after authorizing and prints the chosen copies', function () {
    $w = exitRequestWorld();
    $request = askForBoxes($w, 2);

    $component = Livewire::actingAs($w['warehouse'])->test(ExitRequests::class)
        ->call('openRequest', $request->id)
        ->call('askToApprove')->assertSet('confirming', true)
        ->call('approve')
        ->assertHasNoErrors()
        ->assertSet('confirming', false)
        ->assertSeeHtml('data-test="request-print-step"');

    $exit = StockExit::query()->sole();

    $component->assertSet('printExitId', $exit->id)
        ->set('ticketCopies', 3)
        ->call('printTicket')
        ->assertDispatched('print-ticket', url: route('exits.ticket', ['exit' => $exit->id, 'auto' => 1, 'copies' => 3]))
        ->assertSet('printExitId', null);
});

it('lets the user skip the ticket after authorizing and keeps requesters from printing it', function () {
    $w = exitRequestWorld();
    $request = askForBoxes($w, 1);

    Livewire::actingAs($w['super'])->test(ExitRequests::class)
        ->call('openRequest', $request->id)
        ->call('approve')
        ->call('skipTicket')
        ->assertSet('printExitId', null)
        ->assertNotDispatched('print-ticket');

    Livewire::actingAs($w['requester'])->test(ExitRequests::class)
        ->call('openRequest', $request->id)
        ->set('printExitId', StockExit::query()->value('id'))
        ->call('printTicket')
        ->assertNotDispatched('print-ticket');
});
