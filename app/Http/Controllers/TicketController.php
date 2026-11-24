<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\Entry;
use App\Models\ExitItem;
use App\Models\ExitRequest;
use App\Models\StockExit;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            ->orderByRaw('min(entry_items.id)')
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
            ->orderByRaw('min(exit_items.id)')
            ->selectRaw('products.name as product, count(*) as boxes, sum(exit_items.weight_out_kg) as kg')
            ->get();

        $origin = $exit->originChamber()->with('branch:id,name')->firstOrFail();

        return view('tickets.exit', [
            'exit' => $exit,
            'groups' => $groups,
            'origin' => $origin,
            'destBranch' => $exit->destBranch()->value('name'),
            'destChamber' => $exit->destChamber()->value('name') ?? $exit->deliveryChamber()->value('name'),
            'registeredBy' => $exit->user()->value('name'),
            'hasPartial' => ExitItem::query()->where('exit_id', $exit->id)->where('is_active', true)->where('kind', 'partial')->exists(),
        ]);
    }

    /**
     * One ticket adding up, per requesting branch and product, every exit authorized from a branch request over a day or a range of
     * dates, limited to the chambers the user may see (and to one of them when asked).
     */
    public function exitRequestSummary(Request $request): View
    {
        $user = $request->user();

        $data = $request->validate([
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'camara' => ['nullable', 'integer'],
            'sucursal' => ['nullable', 'integer', 'exists:branches,id'],
            'solicitante' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $chambers = Chamber::query()->accessibleBy($user)->orderBy('name')->get(['id', 'name']);
        $chamber = isset($data['camara']) ? $chambers->find((int) $data['camara']) : null;

        abort_if(isset($data['camara']) && $chamber === null, 403);

        $chamberIds = $chamber ? [$chamber->id] : $chambers->modelKeys();
        $from = Carbon::parse($data['desde'])->startOfDay();
        $to = Carbon::parse($data['hasta'])->endOfDay();

        $exits = fn () => DB::table('exits')
            ->whereIn('exits.id', ExitRequest::query()->whereNotNull('exit_id')
                ->when(isset($data['solicitante']), fn ($query) => $query->where('user_id', (int) $data['solicitante']))
                ->select('exit_id'))
            ->where('exits.status', DocumentStatus::Confirmed->value)
            ->whereIn('exits.origin_chamber_id', $chamberIds)
            ->whereBetween('exits.occurred_at', [$from, $to])
            ->when(isset($data['sucursal']), fn ($query) => $query->where('exits.dest_branch_id', (int) $data['sucursal']));

        $groups = $exits()
            ->join('exit_items', 'exit_items.exit_id', '=', 'exits.id')
            ->join('boxes', 'boxes.id', '=', 'exit_items.box_id')
            ->join('products', 'products.id', '=', 'boxes.product_id')
            ->join('branches', 'branches.id', '=', 'exits.dest_branch_id')
            ->where('exit_items.is_active', true)
            ->groupBy('branches.id', 'branches.name', 'products.id', 'products.name')
            ->orderBy('branches.name')
            ->orderBy('products.name')
            ->selectRaw('branches.name as branch, products.name as product, count(*) as boxes, sum(exit_items.weight_out_kg) as kg')
            ->get();

        return view('tickets.exit-requests-summary', [
            'groups' => $groups,
            'branches' => $groups->groupBy('branch'),
            'requestsCount' => $exits()->count(),
            'from' => $from,
            'to' => $to,
            'chamberLabel' => $chamber->name ?? ($chambers->count() === 1 ? $chambers->first()->name : 'Todas'),
            'branchLabel' => isset($data['sucursal']) ? Branch::query()->whereKey((int) $data['sucursal'])->value('name') : 'Todas',
            'requesterLabel' => isset($data['solicitante']) ? User::query()->whereKey((int) $data['solicitante'])->value('name') : 'Todos',
            'printedBy' => $user->name,
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
