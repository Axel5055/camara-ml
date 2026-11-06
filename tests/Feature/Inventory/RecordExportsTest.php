<?php

use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\Entry;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\RegisterEntry;
use App\Services\Inventory\RegisterExit;
use App\Services\Inventory\WithdrawPartial;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;

/**
 * @return array<string, mixed>
 */
function exportsWorld(): array
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
    ], notes: '=cmd|calc');
    $boxes = Box::query()->orderBy('id')->get();
    $exit = app(RegisterExit::class)->handle($centroUser, $c1, $sur, null, 'Ana López', [$boxes[0]->id, $boxes[1]->id]);
    $partial = app(WithdrawPartial::class)->handle($centroUser, $boxes[2], '5', $sur, 'Luis');

    return compact('centro', 'norte', 'sur', 'c1', 'n1', 'admin', 'centroUser', 'norteUser', 'product', 'entry', 'boxes', 'exit', 'partial');
}

/**
 * Read every cell of a downloaded .xlsx, as sheet name => rows of values.
 *
 * @return array<string, list<list<mixed>>>
 */
function readXlsx(TestResponse $response): array
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    $reader = new Reader;
    $reader->open($path);
    $sheets = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $sheets[$sheet->getName()][] = $row->toArray();
        }
    }

    $reader->close();
    unlink($path);

    return $sheets;
}

it('downloads an entry as an Excel file with every box and the amounts for administrators', function () {
    $w = exportsWorld();

    $response = $this->actingAs($w['admin'])->get(route('entries.export', [$w['entry'], 'xlsx']));

    $response->assertOk()->assertDownload('entrada-'.$w['entry']->reference.'.xlsx');
    $sheets = readXlsx($response);
    $flat = json_encode($sheets, JSON_UNESCAPED_UNICODE);

    expect(array_keys($sheets))->toBe(['Resumen', 'Cajas'])
        ->and($flat)->toContain($w['boxes'][0]->barcode)->toContain($w['boxes'][2]->barcode)
        ->toContain('123.45')->toContain('Importe')
        ->and($sheets['Cajas'])->toHaveCount(2 + 3);
    // The note starts like a formula and must be neutralized.
    expect($flat)->toContain("'=cmd|calc");
});

it('shows only the price per box, never an amount, to warehouse users in the Excel and PDF of an entry', function () {
    $w = exportsWorld();

    $xlsx = readXlsx($this->actingAs($w['centroUser'])->get(route('entries.export', [$w['entry'], 'xlsx']))->assertOk());
    expect(json_encode($xlsx, JSON_UNESCAPED_UNICODE))->toContain('Precio por caja')->toContain('123.45')->not->toContain('Importe')->not->toContain('370.35')->toContain($w['boxes'][0]->barcode);

    $pdf = $this->get(route('entries.export', [$w['entry'], 'pdf']))->assertOk()->assertDownload('entrada-'.$w['entry']->reference.'.pdf');
    expect($pdf->streamedContent())->toStartWith('%PDF');
});

it('downloads an exit with full boxes, partial withdrawals and its costs', function () {
    $w = exportsWorld();

    $sheets = readXlsx($this->actingAs($w['admin'])->get(route('exits.export', [$w['exit'], 'xlsx']))->assertOk());
    $flat = json_encode($sheets, JSON_UNESCAPED_UNICODE);

    expect($flat)->toContain('Ana López')->toContain('Caja completa')->toContain($w['boxes'][0]->barcode)->toContain('246.9')
        ->and($sheets['Cajas'])->toHaveCount(2 + 2);

    $partial = readXlsx($this->get(route('exits.export', [$w['partial'], 'xlsx']))->assertOk());
    expect(json_encode($partial, JSON_UNESCAPED_UNICODE))->toContain('Retiro parcial')->toContain('15')->toContain('30.86');

    $this->get(route('exits.export', [$w['exit'], 'pdf']))->assertOk()->assertDownload('salida-'.$w['exit']->reference.'.pdf');
});

it('hides the historic cost from warehouse users in an exit export', function () {
    $w = exportsWorld();

    $sheets = readXlsx($this->actingAs($w['centroUser'])->get(route('exits.export', [$w['exit'], 'xlsx']))->assertOk());

    expect(json_encode($sheets, JSON_UNESCAPED_UNICODE))->not->toContain('246.9')->not->toContain('Costo histórico')->toContain('Precio por caja')->toContain('123.45')->toContain('Ana López');
});

it('downloads a transfer with the price per box and no money total for anyone', function () {
    $w = exportsWorld();
    app(RegisterEntry::class)->handle($w['centroUser'], $w['c1'], [['product_id' => $w['product']->id, 'boxes_count' => 1, 'weight_per_box_kg' => '10', 'unit_cost_mxn' => '123.45']]);
    $transfer = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Luis', [Box::query()->latest('id')->firstOrFail()->id])->transfer;

    foreach (['centroUser', 'norteUser'] as $who) {
        $sheets = readXlsx($this->actingAs($w[$who])->get(route('transfers.export', [$transfer, 'xlsx']))->assertOk());

        expect(json_encode($sheets, JSON_UNESCAPED_UNICODE))->toContain('Precio por caja')->toContain('123.45')->toContain($transfer->reference)->not->toContain('Costo histórico');
    }

    $this->actingAs($w['centroUser'])->get(route('transfers.export', [$transfer, 'pdf']))->assertOk();
    $this->actingAs($w['centroUser'])->get(route('transfers.export', [$transfer, 'csv']))->assertNotFound();
});

it('does not let users download records outside their chambers and rejects unknown formats', function () {
    $w = exportsWorld();

    $this->actingAs($w['norteUser'])->get(route('entries.export', [$w['entry'], 'pdf']))->assertForbidden();
    $this->get(route('exits.export', [$w['exit'], 'xlsx']))->assertForbidden();

    $this->actingAs($w['centroUser'])->get('/inventario/entradas/'.$w['entry']->id.'/descargar/docx')->assertNotFound();

    auth()->logout();
    $this->get(route('entries.export', [$w['entry'], 'pdf']))->assertRedirect(route('login'));
    expect(Entry::query()->count())->toBe(1);
});

it('offers the PDF and Excel buttons on both detail screens', function () {
    $w = exportsWorld();

    $this->actingAs($w['centroUser'])->get(route('entries.show', $w['entry']))
        ->assertSee(route('entries.export', [$w['entry'], 'pdf']))
        ->assertSee(route('entries.export', [$w['entry'], 'xlsx']));

    $this->get(route('exits.show', $w['exit']))
        ->assertSee(route('exits.export', [$w['exit'], 'pdf']))
        ->assertSee(route('exits.export', [$w['exit'], 'xlsx']));
});
