<?php

use App\Livewire\Inventory\BoxHistory;
use App\Livewire\Inventory\CreateEntry;
use App\Livewire\Inventory\CreateExit;
use App\Livewire\Inventory\EntryDetail;
use App\Livewire\Inventory\ExitDetail;
use App\Livewire\Inventory\Stock;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\Entry;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\RegisterEntry;
use App\Services\Inventory\RegisterExit;
use App\Services\Inventory\ReverseExit;
use App\Services\Inventory\WithdrawPartial;
use Livewire\Livewire;

/**
 * @return array<string, mixed>
 */
function detailsWorld(): array
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
    $entry = app(RegisterEntry::class)->handle($centroUser, $c1, [
        ['product_id' => $product->id, 'boxes_count' => 3, 'weight_per_box_kg' => '20', 'unit_cost_mxn' => '123.45'],
        ['product_id' => $product->id, 'boxes_count' => 1, 'weight_per_box_kg' => '22', 'unit_cost_mxn' => '150'],
    ], notes: 'Lote de la mañana');

    return compact('centro', 'norte', 'sur', 'c1', 'n1', 'admin', 'centroUser', 'norteUser', 'product', 'entry') + ['boxes' => Box::query()->orderBy('id')->get()];
}

it('shows the detail of an entry with its groups and boxes', function () {
    $w = detailsWorld();

    $this->actingAs($w['centroUser'])->get(route('entries.show', $w['entry']))
        ->assertOk()
        ->assertSee('ENT-')
        ->assertSee('Lote de la mañana')
        ->assertSee('Centro 1')
        ->assertSee($w['boxes'][0]->barcode)
        ->assertSee($w['boxes'][3]->barcode)
        ->assertSee('22.000 kg');
});

it('shows the amounts of an entry only to administrators', function () {
    $w = detailsWorld();

    Livewire::actingAs($w['admin'])->test(EntryDetail::class, ['entry' => $w['entry']])->assertSee('123.45')->assertSee('Importe');
    Livewire::actingAs($w['centroUser'])->test(EntryDetail::class, ['entry' => $w['entry']])->assertSee('Costo por caja')->assertSee('123.45')->assertDontSee('Importe')->assertDontSee('370.35');
});

it('does not show an entry to users of other chambers', function () {
    $w = detailsWorld();

    $this->actingAs($w['norteUser'])->get(route('entries.show', $w['entry']))->assertForbidden();
    $this->get(route('entries.index'))->assertOk()->assertDontSee('ENT-');
});

it('shows the detail of an exit with the boxes, partial withdrawals and the transfer', function () {
    $w = detailsWorld();
    [$first, $second, $third] = $w['boxes']->all();

    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Ana López', [$first->id, $second->id], notes: 'Pedido urgente');
    $partial = app(WithdrawPartial::class)->handle($w['centroUser'], $third, '5', $w['sur'], 'Luis');

    $this->actingAs($w['centroUser'])->get(route('exits.show', $exit))
        ->assertOk()
        ->assertSee('Ana López')
        ->assertSee('Pedido urgente')
        ->assertSee('TRF-')
        ->assertSee('En tránsito')
        ->assertSee($first->barcode)
        ->assertSee('Caja completa');

    $this->actingAs($w['centroUser'])->get(route('exits.show', $partial))
        ->assertOk()
        ->assertSee('Retiro parcial')
        ->assertSee('15.000 kg')
        ->assertSee('Entrega directa');
});

it('shows the reversal of an exit and hides costs from warehouse users', function () {
    $w = detailsWorld();
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$w['boxes'][0]->id]);
    app(ReverseExit::class)->handle($w['admin'], $exit, 'Captura equivocada');
    $exit = $exit->fresh();

    Livewire::actingAs($w['admin'])->test(ExitDetail::class, ['exit' => $exit])
        ->assertSee('Captura equivocada')
        ->assertSee('Revertida')
        ->assertSee('123.45');

    Livewire::actingAs($w['centroUser'])->test(ExitDetail::class, ['exit' => $exit])
        ->assertSee('Captura equivocada')
        ->assertSee('Precio por caja')
        ->assertSee('123.45')
        ->assertDontSee('Costo histórico');
});

it('does not show an exit to users unrelated to its chambers', function () {
    $w = detailsWorld();
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$w['boxes'][0]->id]);

    $this->actingAs($w['norteUser'])->get(route('exits.show', $exit))->assertForbidden();

    $toNorte = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Norte', [$w['boxes'][1]->id]);
    $this->actingAs($w['norteUser'])->get(route('exits.show', $toNorte))->assertOk();
});

it('links the lists to the detail screens', function () {
    $w = detailsWorld();
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$w['boxes'][0]->id]);

    $this->actingAs($w['centroUser'])->get(route('entries.index'))->assertSee(route('entries.show', Entry::query()->first()));
    $this->get(route('exits.index'))->assertSee(route('exits.show', $exit));
});

it('lets only the super administrator open the correction of an entry and save it', function () {
    $w = detailsWorld();
    $items = $w['entry']->items()->orderBy('id')->get();

    Livewire::actingAs($w['centroUser'])->test(EntryDetail::class, ['entry' => $w['entry']])
        ->assertDontSee('Corregir')
        ->call('openEdit')->assertForbidden();

    Livewire::actingAs($w['admin'])->test(EntryDetail::class, ['entry' => $w['entry']])
        ->assertSee('Corregir')
        ->call('openEdit')
        ->set('editReason', 'x')
        ->call('saveEdit')->assertHasErrors(['editReason'])
        ->set('editReason', 'Peso mal capturado')
        ->set('editItems.'.$items[0]->id.'.weight_per_box_kg', '19')
        ->call('saveEdit')
        ->assertHasNoErrors()
        ->assertSee('Esta entrada fue corregida 1 vez');

    expect($items[0]->fresh()->weight_per_box_kg)->toBe('19.000');
});

it('shows the error of a rejected correction instead of failing', function () {
    $w = detailsWorld();
    $items = $w['entry']->items()->orderBy('id')->get();
    $box = Box::query()->where('entry_item_id', $items[0]->id)->first();
    app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$box->id]);

    Livewire::actingAs($w['admin'])->test(EntryDetail::class, ['entry' => $w['entry']])
        ->call('openEdit')
        ->set('editReason', 'Peso mal capturado')
        ->set('editItems.'.$items[0]->id.'.weight_per_box_kg', '19')
        ->call('saveEdit')
        ->assertHasErrors(['operation']);
});

it('lets the super administrator correct an exit: header, removing and adding boxes', function () {
    $w = detailsWorld();
    $boxes = $w['boxes'];
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$boxes[0]->id, $boxes[1]->id]);

    Livewire::actingAs($w['centroUser'])->test(ExitDetail::class, ['exit' => $exit])
        ->assertDontSee('Corregir')
        ->call('openEdit')->assertForbidden();

    Livewire::actingAs($w['admin'])->test(ExitDetail::class, ['exit' => $exit])
        ->assertSee('Corregir')
        ->call('openEdit')
        ->set('addBarcode', 'NO-EXISTE')->call('addBox')->assertHasErrors(['addBarcode'])
        ->set('addBarcode', $boxes[2]->barcode)->call('addBox')->assertHasNoErrors()
        ->set('removeIds', [$boxes[0]->id])
        ->set('editRequester', 'Luis')
        ->set('editReason', 'Se entregó otra caja')
        ->call('saveEdit')
        ->assertHasNoErrors()
        ->assertSee('Esta salida fue corregida 1 vez');

    expect($exit->fresh()->requester_name)->toBe('Luis')
        ->and($exit->fresh()->total_boxes)->toBe(2)
        ->and($boxes[0]->fresh()->current_chamber_id)->toBe($w['c1']->id);
});

it('offers no box changes for the correction of an exit that created a transfer', function () {
    $w = detailsWorld();
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Norte', [$w['boxes'][0]->id]);

    Livewire::actingAs($w['admin'])->test(ExitDetail::class, ['exit' => $exit])
        ->call('openEdit')
        ->assertDontSee('Agregar cajas')
        ->set('editRequester', 'Sucursal Norte')
        ->set('editReason', 'Nombre mal escrito')
        ->call('saveEdit')
        ->assertHasNoErrors();

    expect($exit->fresh()->requester_name)->toBe('Sucursal Norte');
});

it('filters the boxes of an entry and of an exit by barcode', function () {
    $w = detailsWorld();
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$w['boxes'][0]->id, $w['boxes'][1]->id]);

    Livewire::actingAs($w['admin'])->test(EntryDetail::class, ['entry' => $w['entry']])
        ->set('search', $w['boxes'][2]->barcode)
        ->assertSee($w['boxes'][2]->barcode)
        ->assertDontSee($w['boxes'][3]->barcode);

    Livewire::actingAs($w['centroUser'])->test(ExitDetail::class, ['exit' => $exit])
        ->set('search', $w['boxes'][0]->barcode)
        ->assertSee($w['boxes'][0]->barcode)
        ->assertDontSee($w['boxes'][1]->barcode);
});

it('also filters the boxes of an entry and of an exit by product', function () {
    $w = detailsWorld();
    $other = Product::factory()->create(['code' => 'RES', 'name' => 'Res molida']);
    $entry = app(RegisterEntry::class)->handle($w['centroUser'], $w['c1'], [
        ['product_id' => $w['product']->id, 'boxes_count' => 1, 'weight_per_box_kg' => '20', 'unit_cost_mxn' => '100'],
        ['product_id' => $other->id, 'boxes_count' => 1, 'weight_per_box_kg' => '20', 'unit_cost_mxn' => '100'],
    ]);
    $pollo = Box::query()->whereIn('entry_item_id', $entry->items()->pluck('id'))->where('product_id', $w['product']->id)->first();
    $res = Box::query()->whereIn('entry_item_id', $entry->items()->pluck('id'))->where('product_id', $other->id)->first();

    Livewire::actingAs($w['admin'])->test(EntryDetail::class, ['entry' => $entry])
        ->set('search', 'res mol')
        ->assertSee($res->barcode)
        ->assertDontSee($pollo->barcode);

    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$pollo->id, $res->id]);

    Livewire::actingAs($w['centroUser'])->test(ExitDetail::class, ['exit' => $exit])
        ->set('search', 'pollo')
        ->assertSee($pollo->barcode)
        ->assertDontSee($res->barcode);
});

it('prints a ticket of an entry with boxes and kilograms per product and never the costs', function () {
    $w = detailsWorld();

    foreach (['admin', 'centroUser'] as $who) {
        $this->actingAs($w[$who])->get(route('entries.ticket', $w['entry']))
            ->assertOk()
            ->assertSee($w['entry']->reference)
            ->assertSee('4 cajas')
            ->assertSee('82.000 kg')
            ->assertSee('Cajas')
            ->assertDontSee('123.45')
            ->assertDontSee('150.00')
            ->assertDontSee('setTimeout', false);
    }

    $this->actingAs($w['centroUser'])->get(route('entries.ticket', [$w['entry'], 'auto' => 1]))
        ->assertSee('setTimeout', false);

    $this->actingAs($w['norteUser'])->get(route('entries.ticket', $w['entry']))->assertForbidden();
});

it('prints a ticket of an exit grouped by product without costs', function () {
    $w = detailsWorld();
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$w['boxes'][0]->id, $w['boxes'][1]->id]);

    $this->actingAs($w['centroUser'])->get(route('exits.ticket', $exit))
        ->assertOk()
        ->assertSee($exit->reference)
        ->assertSee('Pollo')
        ->assertSee('2 cajas')
        ->assertSee('40.000 kg')
        ->assertSee('Ana')
        ->assertDontSee('246.90');

    $this->actingAs($w['norteUser'])->get(route('exits.ticket', $exit))->assertForbidden();
});

it('queues the ticket to print right after registering an entry or an exit', function () {
    $w = detailsWorld();

    Livewire::actingAs($w['centroUser'])->test(CreateEntry::class)
        ->set('chamberId', $w['c1']->id)
        ->set('productId', $w['product']->id)->set('boxesCount', '2')->set('weight', '10')->set('cost', '50')
        ->call('addLine')
        ->call('save')
        ->assertHasNoErrors();

    expect(session('print_ticket'))->toContain('/ticket')->toContain('auto=1');

    $single = Product::factory()->create(['code' => 'RES', 'name' => 'Res']);
    app(RegisterEntry::class)->handle($w['centroUser'], $w['c1'], [
        ['product_id' => $single->id, 'boxes_count' => 2, 'weight_per_box_kg' => '15', 'unit_cost_mxn' => '90'],
    ]);

    Livewire::actingAs($w['centroUser'])->test(CreateExit::class)
        ->set('originChamberId', $w['c1']->id)
        ->set('productId', $single->id)
        ->set('quantity', '1')
        ->call('addProduct')
        ->set('destBranchId', $w['sur']->id)
        ->set('requester', 'Ana')
        ->call('save')
        ->assertHasNoErrors();

    expect(session('print_ticket'))->toContain('/salidas/')->toContain('/ticket');
});

it('shows the print button on the detail of an entry and of an exit', function () {
    $w = detailsWorld();
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$w['boxes'][0]->id]);

    Livewire::actingAs($w['centroUser'])->test(EntryDetail::class, ['entry' => $w['entry']])->assertSeeHtml('data-test="entry-print"');
    Livewire::actingAs($w['centroUser'])->test(ExitDetail::class, ['exit' => $exit])->assertSeeHtml('data-test="exit-print"');
});

it('shows the stock grouped by chamber and product, with the costs only for those who may see them', function () {
    $w = detailsWorld();

    Livewire::actingAs($w['admin'])->test(Stock::class)
        ->call('showView', 'summary')
        ->assertSee('Centro 1')->assertSee('Pollo')->assertSee('130.09')
        ->assertSee('Ajustar');

    Livewire::actingAs($w['centroUser'])->test(Stock::class)
        ->call('showView', 'summary')
        ->assertSee('Pollo')->assertDontSee('130.09')
        ->assertDontSee('Ajustar')
        ->call('openAdjust', $w['c1']->id, $w['product']->id)->assertForbidden();
});

it('lets the super administrator adjust the boxes of a product from the summary', function () {
    $w = detailsWorld();

    Livewire::actingAs($w['admin'])->test(Stock::class)
        ->call('showView', 'summary')
        ->call('openAdjust', $w['c1']->id, $w['product']->id)
        ->assertSet('adjustBoxes', '4')
        ->set('adjustBoxes', '3')
        ->set('adjustReason', 'x')
        ->call('saveAdjust')->assertHasErrors(['adjustReason'])
        ->set('adjustReason', 'Conteo físico del almacén')
        ->call('saveAdjust')
        ->assertHasNoErrors();

    expect(Box::query()->where('status', 'available')->count())->toBe(3)
        ->and(Box::query()->where('status', 'written_off')->count())->toBe(1);
});

it('shows the history of a box as a timeline with links to its entry and exit', function () {
    $w = detailsWorld();
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['sur'], null, 'Ana', [$w['boxes'][0]->id]);

    Livewire::actingAs($w['admin'])->test(BoxHistory::class, ['box' => $w['boxes'][0]->fresh()])
        ->assertSeeHtml('data-test="box-timeline"')
        ->assertSee($w['entry']->reference)
        ->assertSee($exit->reference)
        ->assertSee('Entrada')
        ->assertSee('Salida')
        ->assertSee('Entregada')
        ->assertSeeHtml('data-test="box-card-cost"');

    Livewire::actingAs($w['centroUser'])->test(BoxHistory::class, ['box' => $w['boxes'][1]])
        ->assertSee('Centro 1')
        ->assertSee('Retiro parcial')
        ->assertDontSeeHtml('data-test="box-card-cost"');
});

it('lets the super administrator edit a box from the stock list', function () {
    $w = detailsWorld();
    $box = $w['boxes'][0];

    Livewire::actingAs($w['centroUser'])->test(Stock::class)
        ->assertDontSee('Editar')
        ->call('openEditBox', $box->id)->assertForbidden();

    Livewire::actingAs($w['admin'])->test(Stock::class)
        ->assertSee('Editar')
        ->call('openEditBox', $box->id)
        ->assertSet('editWeight', '20.000')
        ->set('editWeight', '18.500')
        ->set('editReason', 'x')
        ->call('saveEditBox')->assertHasErrors(['editReason'])
        ->set('editReason', 'Se pesó mal al recibir')
        ->call('saveEditBox')
        ->assertHasNoErrors();

    expect($box->fresh()->current_weight_kg)->toBe('18.500')->and($box->fresh()->unit_cost_mxn)->toBe('123.45');
});
