<?php

use App\Http\Controllers\AppVersionController;
use App\Http\Controllers\RecordExportController;
use App\Http\Controllers\TicketController;
use App\Livewire\Admin\Adjustments;
use App\Livewire\Admin\AuditLogs;
use App\Livewire\Admin\Branches;
use App\Livewire\Admin\Categories;
use App\Livewire\Admin\Chambers;
use App\Livewire\Admin\Incidents;
use App\Livewire\Admin\Products;
use App\Livewire\Admin\Users;
use App\Livewire\Dashboard;
use App\Livewire\Inventory\BoxHistory;
use App\Livewire\Inventory\CreateEntry;
use App\Livewire\Inventory\CreateExit;
use App\Livewire\Inventory\CreateTransfer;
use App\Livewire\Inventory\Entries;
use App\Livewire\Inventory\EntryDetail;
use App\Livewire\Inventory\ExitDetail;
use App\Livewire\Inventory\Exits;
use App\Livewire\Inventory\Stock;
use App\Livewire\Inventory\TransferDetail;
use App\Livewire\Inventory\Transfers;
use App\Livewire\Reports\EntriesReport;
use App\Livewire\Reports\ExitsReport;
use App\Livewire\Reports\InventoryReport;
use App\Livewire\Reports\LowStock;
use App\Livewire\Reports\MovementsReport;
use App\Models\Transfer;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('welcome'))->name('home');
Route::get('app-version', AppVersionController::class)->name('app-version');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    // Every signed-in role: the panel and the stock and entry/exit records (scoped to what each role may see).
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');

    Route::livewire('inventario/existencias', Stock::class)->name('stock');
    Route::livewire('inventario/cajas/{box}', BoxHistory::class)->name('boxes.show');

    // Registering and moving stock: warehouse users and super administrators. The read-only administrator is
    // kept out of these routes (declared first so "nueva" is never taken for an id).
    Route::middleware('can:operate-inventory')->group(function () {
        Route::livewire('inventario/entradas/nueva', CreateEntry::class)->name('entries.create');
        Route::livewire('inventario/salidas/nueva', CreateExit::class)->name('exits.create');
        Route::livewire('inventario/transferencias/nueva', CreateTransfer::class)->name('transfers.create');
    });

    // Transfers: operated by warehouse users and super administrators; the read-only administrator can only list them.
    Route::livewire('inventario/transferencias', Transfers::class)->name('transfers.index')->can('viewAny', Transfer::class);
    Route::livewire('inventario/transferencias/{transfer}', TransferDetail::class)->name('transfers.show');
    Route::get('inventario/transferencias/{transfer}/ticket', [TicketController::class, 'transfer'])->name('transfers.ticket');
    Route::get('inventario/transferencias/{transfer}/descargar/{format}', [RecordExportController::class, 'transfer'])->whereIn('format', ['pdf', 'xlsx'])->name('transfers.export');

    Route::livewire('inventario/entradas', Entries::class)->name('entries.index');
    Route::livewire('inventario/entradas/{entry}', EntryDetail::class)->name('entries.show');
    Route::get('inventario/entradas/{entry}/descargar/{format}', [RecordExportController::class, 'entry'])->whereIn('format', ['pdf', 'xlsx'])->name('entries.export');
    Route::get('inventario/entradas/{entry}/ticket', [TicketController::class, 'entry'])->name('entries.ticket');
    Route::get('inventario/salidas/{exit}/ticket', [TicketController::class, 'exit'])->name('exits.ticket');
    Route::livewire('inventario/salidas', Exits::class)->name('exits.index');
    Route::livewire('inventario/salidas/{exit}', ExitDetail::class)->name('exits.show');
    Route::get('inventario/salidas/{exit}/descargar/{format}', [RecordExportController::class, 'exit'])->whereIn('format', ['pdf', 'xlsx'])->name('exits.export');

    // Catalogs: super administrators manage them, administrators can only look at them.
    Route::middleware('can:view-catalogs')->prefix('administracion')->group(function () {
        Route::livewire('sucursales', Branches::class)->name('admin.branches');
        Route::livewire('camaras', Chambers::class)->name('admin.chambers');
        Route::livewire('categorias', Categories::class)->name('admin.categories');
        Route::livewire('productos', Products::class)->name('admin.products');
    });

    // Super administrators only.
    Route::middleware('super-admin')->group(function () {
        Route::prefix('administracion')->group(function () {
            Route::livewire('usuarios', Users::class)->name('admin.users');
            Route::livewire('incidencias', Incidents::class)->name('admin.incidents');
            Route::livewire('ajustes', Adjustments::class)->name('admin.adjustments');
            Route::livewire('auditoria', AuditLogs::class)->name('admin.audit');
        });

        Route::prefix('reportes')->group(function () {
            Route::livewire('inventario', InventoryReport::class)->name('reports.inventory');
            Route::livewire('entradas', EntriesReport::class)->name('reports.entries');
            Route::livewire('salidas', ExitsReport::class)->name('reports.exits');
            Route::livewire('movimientos', MovementsReport::class)->name('reports.movements');
            Route::livewire('existencias-bajas', LowStock::class)->name('reports.low-stock');
        });
    });
});

require __DIR__.'/settings.php';
