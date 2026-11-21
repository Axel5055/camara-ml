<?php

namespace App\Services\Inventory;

use App\Enums\ExitRequestStatus;
use App\Enums\UserRole;
use App\Exceptions\InventoryException;
use App\Models\AuditLog;
use App\Models\Chamber;
use App\Models\ExitRequest;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequestExit
{
    /** Most boxes of one product a single request may ask for. */
    public const MAX_BOXES_PER_PRODUCT = 9999;

    /**
     * A requester asks a chamber for product. Nothing leaves the chamber: the boxes are only chosen and the exit
     * registered when the warehouse user of that chamber authorizes the request.
     *
     * @param  array<int, array{product_id: int|string, boxes: int|string}>  $lines
     */
    public function handle(User $user, Chamber $chamber, array $lines, ?string $notes = null, ?string $clientToken = null): ExitRequest
    {
        if (! $user->is_active || $user->role !== UserRole::Requester || $user->branch_id === null) {
            throw new AuthorizationException('Solo una sucursal solicitante puede pedir producto.');
        }

        if (! $user->canRequestFrom($chamber)) {
            throw new AuthorizationException('No puedes pedir producto a la cámara seleccionada.');
        }

        if (! $chamber->is_active) {
            throw new InventoryException("La cámara {$chamber->name} está inactiva.");
        }

        $branch = $user->branch()->firstOrFail();

        if (! $branch->is_active) {
            throw new InventoryException("La sucursal {$branch->name} está inactiva.");
        }

        $clientToken ??= (string) Str::uuid();

        if ($existing = ExitRequest::query()->where('client_token', $clientToken)->first()) {
            return $existing;
        }

        $lines = $this->normalize($lines);
        $products = Product::query()->with('category')->whereKey(array_keys($lines))->get()->keyBy('id');

        foreach (array_keys($lines) as $productId) {
            $product = $products->get($productId) ?? throw new InventoryException('El producto seleccionado no existe.');

            if (! $product->is_active || ! $product->category->is_active) {
                throw new InventoryException("El producto {$product->name} está inactivo.");
            }
        }

        $notes = trim((string) $notes);

        return DB::transaction(function () use ($user, $branch, $chamber, $lines, $notes, $clientToken): ExitRequest {
            $request = ExitRequest::create([
                'branch_id' => $branch->id,
                'chamber_id' => $chamber->id,
                'user_id' => $user->id,
                'total_boxes_requested' => array_sum($lines),
                'notes' => $notes === '' ? null : $notes,
                'status' => ExitRequestStatus::Pending,
                'client_token' => $clientToken,
            ]);

            $now = now();
            DB::table('exit_request_items')->insert(collect($lines)->map(fn (int $boxes, int $productId): array => [
                'exit_request_id' => $request->id,
                'product_id' => $productId,
                'boxes_requested' => $boxes,
                'boxes_approved' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all());

            AuditLog::record($user, 'exit_request.created', $request, null, [
                'reference' => $request->reference(),
                'branch_id' => $branch->id,
                'chamber_id' => $chamber->id,
                'total_boxes' => $request->total_boxes_requested,
                'products' => $lines,
            ]);

            return $request;
        });
    }

    /**
     * Merges repeated products and validates the amounts.
     *
     * @param  array<int, array{product_id: int|string, boxes: int|string}>  $lines
     * @return array<int, int> boxes requested, keyed by product id
     */
    private function normalize(array $lines): array
    {
        $merged = [];

        foreach ($lines as $line) {
            $productId = (int) $line['product_id'];
            $boxes = filter_var($line['boxes'], FILTER_VALIDATE_INT);

            if ($productId <= 0 || $boxes === false || $boxes < 1) {
                throw new InventoryException('Indica cuántas cajas pides de cada producto (mínimo 1).');
            }

            $merged[$productId] = ($merged[$productId] ?? 0) + $boxes;
        }

        if ($merged === []) {
            throw new InventoryException('Agrega al menos un producto a la solicitud.');
        }

        foreach ($merged as $boxes) {
            if ($boxes > self::MAX_BOXES_PER_PRODUCT) {
                throw new InventoryException('Una solicitud no puede pedir más de '.number_format(self::MAX_BOXES_PER_PRODUCT).' cajas de un mismo producto.');
            }
        }

        return $merged;
    }
}
