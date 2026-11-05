<?php

namespace App\Services\Inventory;

use App\Models\Chamber;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class LowStockAlerts
{
    /** A product is "low on stock" in a chamber when it has this many available boxes or fewer. */
    public const THRESHOLD = 20;

    /**
     * Products that have been stocked in a chamber the user can see and are now low there: with a configured
     * threshold (minimum boxes or kilograms) that one applies, otherwise THRESHOLD boxes or fewer (including none).
     * Products never stocked in a chamber are not reported for it.
     *
     * @return Collection<int, stdClass> rows with chamber_id, chamber, branch, product_id, product, boxes and kg
     */
    public function for(User $user): Collection
    {
        $visible = $user->seesAllChambers() ? null : Chamber::query()->accessibleBy($user)->pluck('id')->all();

        $history = DB::table('boxes')
            ->select('origin_chamber_id as chamber_id', 'product_id')
            ->groupBy('origin_chamber_id', 'product_id');

        $available = DB::table('boxes')
            ->selectRaw('current_chamber_id as chamber_id, product_id, count(*) as boxes, sum(current_weight_kg) as kg')
            ->where('status', 'available')
            ->groupBy('current_chamber_id', 'product_id');

        return DB::query()
            ->fromSub($history, 'h')
            ->leftJoinSub($available, 'a', fn ($join) => $join->on('a.chamber_id', '=', 'h.chamber_id')->on('a.product_id', '=', 'h.product_id'))
            ->join('products', 'products.id', '=', 'h.product_id')
            ->join('chambers', 'chambers.id', '=', 'h.chamber_id')
            ->join('branches', 'branches.id', '=', 'chambers.branch_id')
            ->leftJoin('stock_thresholds as t', fn ($join) => $join->on('t.chamber_id', '=', 'h.chamber_id')->on('t.product_id', '=', 'h.product_id'))
            ->where('products.is_active', true)
            ->where('chambers.is_active', true)
            ->whereRaw('case when t.id is null then coalesce(a.boxes, 0) <= ?
                else ((t.min_boxes is not null and coalesce(a.boxes, 0) < t.min_boxes) or (t.min_weight_kg is not null and coalesce(a.kg, 0) < t.min_weight_kg)) end', [self::THRESHOLD])
            ->when($visible !== null, fn ($query) => $query->whereIn('h.chamber_id', $visible))
            ->selectRaw('h.chamber_id, chambers.name as chamber, branches.name as branch, h.product_id, products.name as product, coalesce(a.boxes, 0) as boxes, coalesce(a.kg, 0) as kg')
            ->orderByRaw('coalesce(a.boxes, 0)')
            ->orderBy('products.name')
            ->get();
    }
}
