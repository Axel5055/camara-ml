<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\ExitItem;
use App\Models\StockExit;
use App\Models\Transfer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Narrow receipts for thermal printers. They only carry boxes and kilograms, never costs, whoever prints them.
 */
class TicketController extends Controller
{
    public function entry(Entry $entry): View
    {
        Gate::authorize('view', $entry);

        $groups = DB::table('entry_items')
            ->join('products', 'products.id', '=', 'entry_items.product_id')
            ->where('entry_items.entry_id', $entry->id)
            ->groupBy('products.id', 'products.name')
            ->orderBy('products.name')
            ->selectRaw('products.name as product, sum(entry_items.boxes_count) as boxes, sum(entry_items.total_weight_kg) as kg')
            ->get();

        $chamber = $entry->chamber()->with('branch:id,name')->firstOrFail();

        return view('tickets.entry', [
            'entry' => $entry,
            'groups' => $groups,
            'chamber' => $chamber,
            'registeredBy' => $entry->user()->value('name'),
        ]);
    }

    public function exit(StockExit $exit): View
    {
        Gate::authorize('view', $exit);

        $groups = DB::table('exit_items')
            ->join('boxes', 'boxes.id', '=', 'exit_items.box_id')
            ->join('products', 'products.id', '=', 'boxes.product_id')
            ->where('exit_items.exit_id', $exit->id)
            ->where('exit_items.is_active', true)
            ->groupBy('products.id', 'products.name')
            ->orderBy('products.name')
            ->selectRaw('products.name as product, count(*) as boxes, sum(exit_items.weight_out_kg) as kg')
            ->get();

        $origin = $exit->originChamber()->with('branch:id,name')->firstOrFail();

        return view('tickets.exit', [
            'exit' => $exit,
            'groups' => $groups,
            'origin' => $origin,
            'destBranch' => $exit->destBranch()->value('name'),
            'destChamber' => $exit->destChamber()->value('name'),
            'registeredBy' => $exit->user()->value('name'),
            'hasPartial' => ExitItem::query()->where('exit_id', $exit->id)->where('is_active', true)->where('kind', 'partial')->exists(),
        ]);
    }

    public function transfer(Transfer $transfer): View
    {
        Gate::authorize('view', $transfer);

        $groups = DB::table('transfer_items')
            ->join('boxes', 'boxes.id', '=', 'transfer_items.box_id')
            ->join('products', 'products.id', '=', 'boxes.product_id')
            ->where('transfer_items.transfer_id', $transfer->id)
            ->whereNull('transfer_items.removed_at')
            ->groupBy('products.id', 'products.name')
            ->orderBy('products.name')
            ->selectRaw('products.name as product, count(*) as boxes, sum(transfer_items.expected_weight_kg) as kg')
            ->get();

        $exit = $transfer->exit()->firstOrFail();

        return view('tickets.transfer', [
            'transfer' => $transfer,
            'exit' => $exit,
            'groups' => $groups,
            'origin' => $transfer->originChamber()->with('branch:id,name')->firstOrFail(),
            'dest' => $transfer->destChamber()->with('branch:id,name')->firstOrFail(),
            'registeredBy' => $exit->user()->value('name'),
        ]);
    }
}
