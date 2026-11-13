<?php

use App\Enums\AdjustmentType;
use App\Enums\IncidentStatus;
use App\Livewire\Admin\Adjustments;
use App\Livewire\Admin\AuditLogs;
use App\Livewire\Admin\Incidents;
use App\Livewire\Reports\EntriesReport;
use App\Livewire\Reports\ExitsReport;
use App\Livewire\Reports\InventoryReport;
use App\Livewire\Reports\LowStock;
use App\Livewire\Reports\MovementsReport;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\Incident;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\AdjustInventory;
use App\Services\Inventory\LowStockAlerts;
use App\Services\Inventory\RegisterEntry;
use App\Services\Inventory\RegisterExit;
use App\Support\CsvExport;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Example from the spec: 10×20kg@500, 15×22kg@550, 8×18kg@470 in Centro; three boxes delivered to Sur.
 *
 * @return array<string, mixed>
 */
function reportsWorld(): array
{
    $centro = Branch::factory()->create(['name' => 'Centro']);
    $sur = Branch::factory()->create(['name' => 'Sur']);
    $norte = Branch::factory()->create(['name' => 'Norte']);
    $c1 = Chamber::factory()->for($centro)->create(['name' => 'Centro 1']);
    $n1 = Chamber::factory()->for($norte)->create(['name' => 'Norte 1']);

    $admin = User::factory()->superAdmin()->create();
    $centroUser = User::factory()->warehouse($centro)->create();
    $centroUser->chambers()->attach($c1);

    $product = Product::factory()->create(['code' => 'POLLO', 'name' => 'Pollo']);
    app(RegisterEntry::class)->handle($centroUser, $c1, [
        ['product_id' => $product->id, 'boxes_count' => 10, 'weight_per_box_kg' => '20', 'price_per_kg_mxn' => '25.00'],
        ['product_id' => $product->id, 'boxes_count' => 15, 'weight_per_box_kg' => '22', 'price_per_kg_mxn' => '25.00'],
        ['product_id' => $product->id, 'boxes_count' => 8, 'weight_per_box_kg' => '18', 'price_per_kg_mxn' => '26.00'],
    ]);

    $boxes = Box::query()->orderBy('id')->get();
    app(RegisterExit::class)->handle($centroUser, $c1, $sur, null, 'Juan Pérez', [$boxes[10]->id, $boxes[11]->id, $boxes[25]->id]);

    return compact('centro', 'sur', 'norte', 'c1', 'n1', 'admin', 'centroUser', 'product', 'boxes');
}

it('keeps every report and admin tool away from warehouse users', function () {
    $w = reportsWorld();
    $this->actingAs($w['centroUser']);

    foreach (['reports.inventory', 'reports.entries', 'reports.exits', 'reports.movements', 'reports.low-stock', 'admin.incidents', 'admin.adjustments', 'admin.audit'] as $name) {
        $this->get(route($name))->assertForbidden();
    }

    Livewire::test(InventoryReport::class)->assertForbidden();
});

it('renders every report for an administrator', function () {
    $w = reportsWorld();
    $this->actingAs($w['admin']);

    foreach (['reports.inventory', 'reports.entries', 'reports.exits', 'reports.movements', 'reports.low-stock', 'admin.incidents', 'admin.adjustments', 'admin.audit'] as $name) {
        $this->get(route($name))->assertOk();
    }
});

it('reports current inventory by chamber and consolidated by branch', function () {
    $w = reportsWorld();

    $component = Livewire::actingAs($w['admin'])->test(InventoryReport::class)->assertSee('Centro 1');

    expect($component->viewData('totals'))->toMatchObject(['boxes' => 30]);
    expect((float) $component->viewData('totals')->kg)->toBe(674.0 - 62.0);

    $byBranch = Livewire::actingAs($w['admin'])->test(InventoryReport::class)->set('groupBy', 'branch');
    expect($byBranch->viewData('rows'))->toHaveCount(1)->and($byBranch->viewData('rows')[0]->label)->toBe('Centro');

    $delivered = Livewire::actingAs($w['admin'])->test(InventoryReport::class)->set('status', 'dispatched');
    expect($delivered->viewData('totals')->boxes)->toBe(3)
        ->and((float) $delivered->viewData('totals')->cost)->toBe(1568.0);
});

it('reports entries with exact kilograms and amounts, filtered by product', function () {
    $w = reportsWorld();
    $other = Product::factory()->create();

    $report = Livewire::actingAs($w['admin'])->test(EntriesReport::class);
    expect((int) $report->viewData('totals')->boxes)->toBe(33)
        ->and((float) $report->viewData('totals')->kg)->toBe(674.0)
        ->and((float) $report->viewData('totals')->cost)->toBe(16994.0)
        ->and($report->viewData('rows'))->toHaveCount(3);

    $filtered = Livewire::actingAs($w['admin'])->test(EntriesReport::class)->set('productId', $other->id);
    expect((int) $filtered->viewData('totals')->boxes)->toBe(0);
});

it('reports exits with the historic cost, by requester and destination branch', function () {
    $w = reportsWorld();

    $report = Livewire::actingAs($w['admin'])->test(ExitsReport::class);
    expect((int) $report->viewData('totals')->boxes)->toBe(3)
        ->and((float) $report->viewData('totals')->kg)->toBe(62.0)
        ->and((float) $report->viewData('totals')->cost)->toBe(1568.0);

    $report->set('requester', 'Pérez')->assertSee('Juan Pérez');
    expect((int) Livewire::actingAs($w['admin'])->test(ExitsReport::class)->set('destBranchId', $w['norte']->id)->viewData('totals')->boxes)->toBe(0);
});

it('lists movements by user and type', function () {
    $w = reportsWorld();

    $all = Livewire::actingAs($w['admin'])->test(MovementsReport::class)->set('userId', $w['centroUser']->id);
    expect($all->viewData('rows')->total())->toBe(36);

    $exits = Livewire::actingAs($w['admin'])->test(MovementsReport::class)->set('type', 'exit');
    expect($exits->viewData('rows')->total())->toBe(3);
});

it('flags low stock against configured thresholds', function () {
    $w = reportsWorld();

    $component = Livewire::actingAs($w['admin'])->test(LowStock::class)
        ->set('thresholdChamberId', $w['c1']->id)
        ->set('thresholdProductId', $w['product']->id)
        ->set('minBoxes', '50')
        ->call('saveThreshold')
        ->assertHasNoErrors();

    expect($component->viewData('rows'))->toHaveCount(1)
        ->and($component->viewData('rows')[0]->boxes)->toBe(30);

    $component->set('thresholdChamberId', $w['c1']->id)->set('thresholdProductId', $w['product']->id)->set('minBoxes', '10')->call('saveThreshold');
    $component->assertSet('showAll', false);
    expect($component->viewData('rows'))->toHaveCount(0);

    $component->call('saveThreshold')->assertHasErrors('thresholdChamberId');
});

it('exports reports as CSV protected against formula injection', function () {
    $w = reportsWorld();

    $response = Livewire::actingAs($w['admin'])->test(EntriesReport::class)->call('export')->assertFileDownloaded();
    expect($response)->not->toBeNull();

    expect(CsvExport::safe('=HYPERLINK("x")'))->toBe("'=HYPERLINK(\"x\")")
        ->and(CsvExport::safe('-12.50'))->toBe('-12.50')
        ->and(CsvExport::safe('@cmd'))->toBe("'@cmd")
        ->and(CsvExport::safe('Pollo'))->toBe('Pollo');

    $content = '';
    ob_start();
    CsvExport::download('x.csv', ['A', 'B'], [['=1+1', 'ok']], fn ($row) => $row)->sendContent();
    $content = ob_get_clean();

    expect($content)->toContain("\xEF\xBB\xBF")->toContain("'=1+1");
});

it('resolves incidents and records it in the audit trail', function () {
    $w = reportsWorld();
    $box = $w['boxes'][0];
    $exit = app(RegisterExit::class)->handle($w['centroUser'], $w['c1'], $w['norte'], $w['n1'], 'Norte', [$box->id]);
    $transfer = $exit->transfer;
    $incident = Incident::query()->create([
        'transfer_id' => $transfer->id, 'box_id' => $box->id, 'type' => 'missing', 'description' => 'No llegó',
        'status' => IncidentStatus::Open, 'reported_by' => $w['centroUser']->id,
    ]);

    Livewire::actingAs($w['admin'])->test(Incidents::class)
        ->assertSee('No llegó')
        ->call('askResolve', $incident->id)
        ->call('resolve')
        ->assertHasErrors('resolution')
        ->set('resolution', 'Se localizó en tránsito')
        ->call('resolve')
        ->assertHasNoErrors();

    expect($incident->fresh()->status)->toBe(IncidentStatus::Resolved)
        ->and($incident->fresh()->resolved_by)->toBe($w['admin']->id)
        ->and(DB::table('audit_logs')->where('event', 'incident.resolved')->count())->toBe(1);
});

it('applies an inventory adjustment from the screen', function () {
    $w = reportsWorld();
    $box = $w['boxes'][0];

    Livewire::actingAs($w['admin'])->test(Adjustments::class)
        ->call('create')
        ->set('barcode', strtolower($box->barcode))
        ->set('type', 'weight_correction')
        ->set('newWeight', '19.500')
        ->set('reason', 'Báscula descalibrada')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee($box->barcode);

    expect($box->fresh()->current_weight_kg)->toBe('19.500');

    Livewire::actingAs($w['admin'])->test(Adjustments::class)
        ->set('barcode', 'NOEXISTE-1')
        ->set('reason', 'Motivo válido')
        ->call('save')
        ->assertHasErrors('barcode');
});

it('filters the audit log by event and user', function () {
    $w = reportsWorld();
    $box = $w['boxes'][0];

    app(AdjustInventory::class)->handle($w['admin'], $box, AdjustmentType::Lost, 'Caja extraviada');

    $logs = Livewire::actingAs($w['admin'])->test(AuditLogs::class)->set('event', 'inventory');
    expect($logs->viewData('logs')->total())->toBe(1);

    $none = Livewire::actingAs($w['admin'])->test(AuditLogs::class)->set('event', 'inventory')->set('userId', $w['centroUser']->id);
    expect($none->viewData('logs')->total())->toBe(0);

    $entries = Livewire::actingAs($w['admin'])->test(AuditLogs::class)->set('event', 'entry.registered')->set('userId', $w['centroUser']->id);
    expect($entries->viewData('logs')->total())->toBeGreaterThan(0);
});

it('clears the inventory report filters and shows an empty state', function () {
    $w = reportsWorld();

    $component = Livewire::actingAs($w['admin'])->test(InventoryReport::class)
        ->set('status', 'written_off')
        ->assertSee('No hay existencias que coincidan')
        ->call('clearFilters');

    expect($component->get('status'))->toBe('available')
        ->and($component->viewData('hasFilters'))->toBeFalse()
        ->and($component->viewData('totals')->boxes)->toBe(30);
});

it('counts entries in the report, clears filters and shows an empty state', function () {
    $w = reportsWorld();

    $component = Livewire::actingAs($w['admin'])->test(EntriesReport::class);
    expect((int) $component->viewData('totals')->entries)->toBe(1)
        ->and($component->viewData('hasFilters'))->toBeFalse();

    $component->set('productId', Product::factory()->create()->id)->assertSee('No hay entradas que coincidan');
    expect($component->viewData('hasFilters'))->toBeTrue();

    $component->call('clearFilters');
    expect($component->viewData('hasFilters'))->toBeFalse()
        ->and((int) $component->viewData('totals')->boxes)->toBe(33);
});

it('counts exits in the report, clears filters and shows an empty state', function () {
    $w = reportsWorld();

    $component = Livewire::actingAs($w['admin'])->test(ExitsReport::class);
    expect((int) $component->viewData('totals')->exits)->toBe(1)
        ->and($component->viewData('hasFilters'))->toBeFalse();

    $component->set('requester', 'Nadie')->assertSee('No hay salidas que coincidan');
    expect($component->viewData('hasFilters'))->toBeTrue();

    $component->call('clearFilters');
    expect($component->viewData('hasFilters'))->toBeFalse()
        ->and((int) $component->viewData('totals')->boxes)->toBe(3);
});

it('summarises movements, clears filters and shows an empty state', function () {
    $w = reportsWorld();

    $component = Livewire::actingAs($w['admin'])->test(MovementsReport::class);
    expect((int) $component->viewData('totals')->movements)->toBe(36)
        ->and((int) $component->viewData('totals')->users)->toBe(1)
        ->and($component->viewData('hasFilters'))->toBeFalse();

    $component->set('type', 'adjustment')->assertSee('No hay movimientos que coincidan');
    expect($component->viewData('hasFilters'))->toBeTrue();

    $component->call('clearFilters');
    expect($component->viewData('hasFilters'))->toBeFalse()
        ->and((int) $component->viewData('totals')->movements)->toBe(36);
});

it('summarises thresholds, filters them and clears the filters', function () {
    $w = reportsWorld();
    $other = Product::factory()->create(['name' => 'Producto sin existencias']);

    $component = Livewire::actingAs($w['admin'])->test(LowStock::class);
    foreach ([[$w['product']->id, '50'], [$other->id, '5']] as [$productId, $min]) {
        $component->set('thresholdChamberId', $w['c1']->id)->set('thresholdProductId', $productId)->set('minBoxes', $min)->call('saveThreshold');
    }

    expect($component->viewData('totals'))->toBe(['thresholds' => 2, 'low' => 2, 'empty' => 1, 'healthy' => 0]);

    $component->set('search', 'sin existencias');
    expect($component->viewData('rows'))->toHaveCount(1)
        ->and($component->viewData('totals')['thresholds'])->toBe(1);

    $component->set('search', 'zzz')->assertSee('Sin existencias bajas');
    $component->call('clearFilters');
    expect($component->viewData('hasFilters'))->toBeFalse()
        ->and($component->viewData('rows'))->toHaveCount(2);
});

it('lists every stocked product with the default threshold and lets a configured one replace it', function () {
    $w = reportsWorld();
    DB::table('boxes')->where('status', 'available')->limit(12)->update(['status' => 'dispatched', 'current_chamber_id' => null]);

    // 18 boxes left: low by the default rule (20 or fewer), with no threshold configured.
    $component = Livewire::actingAs($w['admin'])->test(LowStock::class);
    expect($component->viewData('rows'))->toHaveCount(1)
        ->and((bool) $component->viewData('rows')[0]->is_default)->toBeTrue()
        ->and((int) $component->viewData('rows')[0]->min_boxes)->toBe(20);
    $component->assertSee('Por defecto');
    expect(app(LowStockAlerts::class)->for($w['admin']))->toHaveCount(1);

    // A configured threshold replaces the default one, in the report and on the dashboard.
    $component->set('thresholdChamberId', $w['c1']->id)->set('thresholdProductId', $w['product']->id)->set('minBoxes', '10')->call('saveThreshold');
    expect($component->viewData('rows'))->toHaveCount(0)
        ->and(app(LowStockAlerts::class)->for($w['admin']))->toBeEmpty();
});
