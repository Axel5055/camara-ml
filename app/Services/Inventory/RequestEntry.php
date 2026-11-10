<?php

namespace App\Services\Inventory;

use App\Enums\EntryRequestStatus;
use App\Enums\UserRole;
use App\Exceptions\InventoryException;
use App\Models\AuditLog;
use App\Models\Chamber;
use App\Models\EntryRequest;
use App\Models\Product;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequestEntry extends InventoryOperation
{
    public function __construct(private RegisterEntry $registerEntry) {}

    /**
     * A warehouse user asks to register an entry. Nothing touches the stock yet: no boxes, no barcodes and no
     * references are created until an administrator authorizes the request.
     *
     * @param  array<int, array<string, mixed>>  $groups
     */
    public function handle(
        User $user,
        Chamber $chamber,
        array $groups,
        ?CarbonInterface $occurredAt = null,
        ?string $notes = null,
        ?string $clientToken = null,
    ): EntryRequest {
        if ($user->role !== UserRole::Warehouse) {
            throw new AuthorizationException('Solo el almacenista solicita entradas.');
        }

        $this->authorizeChamber($user, $chamber);

        $clientToken ??= (string) Str::uuid();

        if ($existing = EntryRequest::query()->where('client_token', $clientToken)->first()) {
            return $existing;
        }

        $groups = $this->registerEntry->normalizeGroups($groups);
        $products = Product::query()->with('category')->whereKey(array_column($groups, 'product_id'))->get()->keyBy('id');
        $totalBoxes = 0;
        $totalWeight = '0.000';
        $totalCost = '0.00';

        foreach ($groups as $group) {
            $product = $products->get($group['product_id']) ?? throw new InventoryException('El producto seleccionado no existe.');

            if (! $product->is_active || ! $product->category->is_active) {
                throw new InventoryException("El producto {$product->name} está inactivo.");
            }

            $totalBoxes += $group['boxes_count'];
            $totalWeight = Decimal::add($totalWeight, Decimal::mul($group['weight_per_box_kg'], (string) $group['boxes_count'], Decimal::WEIGHT_SCALE), Decimal::WEIGHT_SCALE);
            $totalCost = Decimal::add($totalCost, Decimal::mul($group['unit_cost_mxn'], (string) $group['boxes_count'], Decimal::MONEY_SCALE), Decimal::MONEY_SCALE);
        }

        return DB::transaction(function () use ($user, $chamber, $groups, $occurredAt, $notes, $clientToken, $totalBoxes, $totalWeight, $totalCost): EntryRequest {
            $request = EntryRequest::create([
                'chamber_id' => $chamber->id,
                'user_id' => $user->id,
                'occurred_at' => $occurredAt ?? now(),
                'total_boxes' => $totalBoxes,
                'total_weight_kg' => $totalWeight,
                'total_cost_mxn' => $totalCost,
                'notes' => $notes,
                'groups' => $groups,
                'status' => EntryRequestStatus::Pending,
                'client_token' => $clientToken,
            ]);

            AuditLog::record($user, 'entry_request.created', $request, null, [
                'chamber_id' => $chamber->id, 'boxes' => $totalBoxes, 'weight_kg' => $totalWeight,
            ]);

            return $request;
        });
    }
}
