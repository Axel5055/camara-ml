<?php

use App\Enums\EntryRequestStatus;
use App\Livewire\ApprovedEntryPrompt;
use App\Livewire\Inventory\CreateEntry;
use App\Livewire\Inventory\EntryRequests;
use App\Livewire\NotificationsBell;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\Entry;
use App\Models\EntryRequest;
use App\Models\Product;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * @return array<string, mixed>
 */
function requestWorld(): array
{
    $branch = Branch::factory()->create();
    $chamber = Chamber::factory()->for($branch)->create();
    $warehouse = User::factory()->warehouse($branch)->create();
    $warehouse->chambers()->attach($chamber);

    return [
        'chamber' => $chamber,
        'warehouse' => $warehouse,
        'admin' => User::factory()->admin()->create(),
        'super' => User::factory()->superAdmin()->create(),
        'product' => Product::factory()->create(),
    ];
}

/**
 * @param  array<string, mixed>  $w
 */
function captureEntry(User $user, array $w): Testable
{
    return Livewire::actingAs($user)->test(CreateEntry::class)
        ->set('chamberId', $w['chamber']->id)
        ->set('productId', $w['product']->id)
        ->set('boxesCount', '2')->set('weight', '10')->set('cost', '50')
        ->call('addLine');
}

it('turns the entry of a warehouse user into a request without creating boxes', function () {
    $w = requestWorld();

    captureEntry($w['warehouse'], $w)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('entry-requests.index'));

    $request = EntryRequest::query()->sole();

    expect($request->status)->toBe(EntryRequestStatus::Pending)
        ->and($request->user_id)->toBe($w['warehouse']->id)
        ->and($request->total_boxes)->toBe(2)
        ->and($request->groups[0]['boxes_count'])->toBe(2)
        ->and(Entry::query()->count())->toBe(0)
        ->and(Box::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event', 'entry_request.created')->exists())->toBeTrue();
});

it('does not duplicate the request when the form is submitted twice', function () {
    $w = requestWorld();

    $component = captureEntry($w['warehouse'], $w);
    $component->call('save');
    $component->call('save');

    expect(EntryRequest::query()->count())->toBe(1);
});

it('still registers right away the entries of a super administrator', function () {
    $w = requestWorld();

    captureEntry($w['super'], $w)->call('save')->assertHasNoErrors()->assertRedirect(route('entries.index'));

    expect(Entry::query()->count())->toBe(1)->and(EntryRequest::query()->count())->toBe(0);
});

it('notifies administrators and super administrators of the pending request in the bell', function () {
    $w = requestWorld();
    captureEntry($w['warehouse'], $w)->call('save');

    foreach (['admin', 'super'] as $who) {
        Livewire::actingAs($w[$who])->test(NotificationsBell::class)
            ->assertSeeHtml('data-test="bell-count"')
            ->assertSee('pide registrar 2 cajas');
    }

    Livewire::actingAs($w['warehouse'])->test(NotificationsBell::class)->assertDontSeeHtml('data-test="bell-count"');
});

it('lets an administrator authorize the request and registers the entry for the requester', function () {
    $w = requestWorld();
    captureEntry($w['warehouse'], $w)->call('save');
    $request = EntryRequest::query()->sole();

    Livewire::actingAs($w['admin'])->test(EntryRequests::class)
        ->call('openRequest', $request->id)
        ->call('approve')
        ->assertHasNoErrors();

    $request->refresh();
    $entry = Entry::query()->sole();

    expect($request->status)->toBe(EntryRequestStatus::Approved)
        ->and($request->decided_by)->toBe($w['admin']->id)
        ->and($request->entry_id)->toBe($entry->id)
        ->and($entry->user_id)->toBe($w['warehouse']->id)
        ->and(Box::query()->count())->toBe(2)
        ->and(AuditLog::query()->where('event', 'entry_request.approved')->exists())->toBeTrue();

    $this->actingAs($w['super'])->get(route('entries.show', $entry))->assertSee('Autorizada por '.$w['admin']->name);
});

it('lets a super administrator authorize and registers the entry only once', function () {
    $w = requestWorld();
    captureEntry($w['warehouse'], $w)->call('save');
    $request = EntryRequest::query()->sole();

    $component = Livewire::actingAs($w['super'])->test(EntryRequests::class)->call('openRequest', $request->id);
    $component->call('approve')->assertHasNoErrors();
    $component->call('approve')->assertHasErrors('operation');

    expect(Entry::query()->count())->toBe(1)->and(Box::query()->count())->toBe(2);
});

it('rejects a request with a reason and creates nothing', function () {
    $w = requestWorld();
    captureEntry($w['warehouse'], $w)->call('save');
    $request = EntryRequest::query()->sole();

    $component = Livewire::actingAs($w['admin'])->test(EntryRequests::class)->call('openRequest', $request->id);
    $component->call('reject')->assertHasErrors('rejectReason');
    $component->set('rejectReason', 'Faltan las facturas')->call('reject')->assertHasNoErrors();

    $request->refresh();

    expect($request->status)->toBe(EntryRequestStatus::Rejected)
        ->and($request->decision_notes)->toBe('Faltan las facturas')
        ->and(Entry::query()->count())->toBe(0);

    Livewire::actingAs($w['warehouse'])->test(NotificationsBell::class)->assertSee('Rechazada');
});

it('does not let warehouse users decide and lets them cancel only their own pending request', function () {
    $w = requestWorld();
    captureEntry($w['warehouse'], $w)->call('save');
    $request = EntryRequest::query()->sole();

    Livewire::actingAs($w['warehouse'])->test(EntryRequests::class)
        ->call('openRequest', $request->id)
        ->call('approve')
        ->assertForbidden();

    $other = User::factory()->warehouse($w['chamber']->branch)->create();
    $other->chambers()->attach($w['chamber']);

    Livewire::actingAs($other)->test(EntryRequests::class)->call('cancelRequest', $request->id)->assertForbidden();

    Livewire::actingAs($w['warehouse'])->test(EntryRequests::class)->call('cancelRequest', $request->id);

    expect($request->refresh()->status)->toBe(EntryRequestStatus::Cancelled)->and(Entry::query()->count())->toBe(0);
});

it('keeps the requests page visible to every role', function () {
    $w = requestWorld();

    foreach (['warehouse', 'admin', 'super'] as $who) {
        $this->actingAs($w[$who])->get(route('entry-requests.index'))->assertOk()->assertSee('Solicitudes de entrada');
    }
});

it('requires signing in to see the requests page', function () {
    $this->get(route('entry-requests.index'))->assertRedirect(route('login'));
});

it('offers the warehouse user to print the ticket once the request is authorized, only once', function () {
    $w = requestWorld();
    captureEntry($w['warehouse'], $w)->call('save');
    $request = EntryRequest::query()->sole();

    Livewire::actingAs($w['warehouse'])->test(ApprovedEntryPrompt::class)->assertDontSeeHtml('data-test="approved-entry-prompt"');

    Livewire::actingAs($w['admin'])->test(EntryRequests::class)->call('openRequest', $request->id)->call('approve');
    $entry = Entry::query()->sole();

    Livewire::actingAs($w['admin'])->test(ApprovedEntryPrompt::class)->assertDontSeeHtml('data-test="approved-entry-prompt"');

    Livewire::actingAs($w['warehouse'])->test(ApprovedEntryPrompt::class)
        ->assertSeeHtml('data-test="approved-entry-prompt"')
        ->assertSee($entry->reference)
        ->set('ticketCopies', '2')
        ->call('printTicket')
        ->assertDispatched('print-ticket', url: route('entries.ticket', ['entry' => $entry, 'auto' => 1, 'copies' => 2]))
        ->assertDontSeeHtml('data-test="approved-entry-prompt"');

    expect($request->refresh()->ticket_prompted_at)->not->toBeNull();

    Livewire::actingAs($w['warehouse'])->test(ApprovedEntryPrompt::class)->assertDontSeeHtml('data-test="approved-entry-prompt"');
});

it('lets the warehouse user dismiss the prompt and print the ticket later from the requests list', function () {
    $w = requestWorld();
    captureEntry($w['warehouse'], $w)->call('save');
    $request = EntryRequest::query()->sole();
    Livewire::actingAs($w['super'])->test(EntryRequests::class)->call('openRequest', $request->id)->call('approve');
    $entry = Entry::query()->sole();

    Livewire::actingAs($w['warehouse'])->test(ApprovedEntryPrompt::class)
        ->call('dismiss')
        ->assertDontSeeHtml('data-test="approved-entry-prompt"')
        ->assertNotDispatched('print-ticket');

    Livewire::actingAs($w['warehouse'])->test(EntryRequests::class)
        ->call('openRequest', $request->id)
        ->assertSeeHtml('data-test="request-print-ticket"')
        ->set('ticketCopies', '3')
        ->call('printTicket')
        ->assertDispatched('print-ticket', url: route('entries.ticket', ['entry' => $entry, 'auto' => 1, 'copies' => 3]));
});

it('does not offer a ticket for a request that is still pending', function () {
    $w = requestWorld();
    captureEntry($w['warehouse'], $w)->call('save');
    $request = EntryRequest::query()->sole();

    Livewire::actingAs($w['warehouse'])->test(EntryRequests::class)
        ->call('openRequest', $request->id)
        ->assertDontSeeHtml('data-test="request-print-ticket"')
        ->call('printTicket')
        ->assertHasErrors('operation')
        ->assertNotDispatched('print-ticket');
});

it('tells an administrator when the data of the request no longer allows registering it', function () {
    $w = requestWorld();
    captureEntry($w['warehouse'], $w)->call('save');
    $request = EntryRequest::query()->sole();
    $w['product']->update(['is_active' => false]);

    Livewire::actingAs($w['admin'])->test(EntryRequests::class)
        ->call('openRequest', $request->id)
        ->call('approve')
        ->assertHasErrors('operation');

    expect($request->refresh()->status)->toBe(EntryRequestStatus::Pending);
});
