<?php

namespace App\Services\Inventory;

use App\Enums\ExitRequestStatus;
use App\Exceptions\InventoryException;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\ExitRequest;
use App\Models\StockExit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ReviewExitRequest
{
    public function __construct(private RegisterExit $registerExit) {}

    /**
     * Authorizes the request, in full or in part: the chosen boxes leave the chamber as a normal exit to the
     * requesting branch. The request keeps the link to the exit and to who authorized it.
     *
     * @param  array<int, array<int, int|string>>  $boxIdsByProduct  boxes to send, keyed by product id
     */
    public function approve(User $reviewer, ExitRequest $request, array $boxIdsByProduct, ?string $notes = null): StockExit
    {
        $this->assertMayDecide($reviewer, $request);

        $request->load(['items.product', 'chamber', 'branch', 'user']);
        $selected = $this->validatedSelection($request, $boxIdsByProduct);
        $boxIds = array_merge(...array_values($selected));
        $notes = $this->clean($notes);

        return DB::transaction(function () use ($reviewer, $request, $selected, $boxIds, $notes): StockExit {
            $locked = ExitRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new InventoryException('Esta solicitud ya fue resuelta.');
            }

            $exit = $this->registerExit->handle(
                $reviewer,
                $request->chamber,
                $request->branch,
                null,
                $request->user->name,
                $boxIds,
                null,
                trim("Solicitud {$request->reference()}. ".($notes ?? '')),
                'exit-request-'.$request->id,
            );

            foreach ($request->items as $item) {
                $item->update(['boxes_approved' => count($selected[$item->product_id] ?? [])]);
            }

            $locked->update([
                'status' => ExitRequestStatus::Approved,
                'decided_by' => $reviewer->id,
                'decided_at' => now(),
                'decision_notes' => $notes,
                'total_boxes_approved' => count($boxIds),
                'exit_id' => $exit->id,
            ]);

            AuditLog::record($reviewer, 'exit_request.approved', $locked, null, [
                'reference' => $locked->reference(),
                'exit_id' => $exit->id,
                'exit_reference' => $exit->reference,
                'boxes_requested' => $locked->total_boxes_requested,
                'boxes_approved' => count($boxIds),
                'notes' => $notes,
            ]);

            return $exit;
        });
    }

    public function reject(User $reviewer, ExitRequest $request, string $reason): ExitRequest
    {
        $this->assertMayDecide($reviewer, $request);

        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw new InventoryException('Indica el motivo del rechazo (mínimo 5 caracteres).');
        }

        return $this->close($request, ExitRequestStatus::Rejected, $reviewer, $reason, 'exit_request.rejected');
    }

    /**
     * The requester withdraws their own pending request.
     */
    public function cancel(User $user, ExitRequest $request): ExitRequest
    {
        if ($request->user_id !== $user->id) {
            throw new AuthorizationException('Solo quien hizo la solicitud puede cancelarla.');
        }

        return $this->close($request, ExitRequestStatus::Cancelled, null, null, 'exit_request.cancelled', $user);
    }

    private function assertMayDecide(User $reviewer, ExitRequest $request): void
    {
        if (! $reviewer->canAccessChamber($request->chamber)) {
            throw new AuthorizationException('Solo el almacenista de la cámara o un super administrador puede autorizar o rechazar solicitudes de salida.');
        }

        if ($request->user_id === $reviewer->id && ! $reviewer->isSuperAdmin()) {
            throw new AuthorizationException('No puedes resolver tu propia solicitud.');
        }

        if (! $request->fresh()?->isPending()) {
            throw new InventoryException('Esta solicitud ya fue resuelta.');
        }
    }

    /**
     * Checks the boxes chosen to fulfil the request: only requested products, never more boxes than asked for,
     * each box of its product and available in the chamber, and at least one (otherwise it must be rejected).
     *
     * @param  array<int, array<int, int|string>>  $boxIdsByProduct
     * @return array<int, list<int>> box ids to send, keyed by product id
     */
    private function validatedSelection(ExitRequest $request, array $boxIdsByProduct): array
    {
        $requested = $request->items->pluck('boxes_requested', 'product_id');
        $selected = [];

        foreach ($boxIdsByProduct as $productId => $ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));

            if ($ids === []) {
                continue;
            }

            if (! $requested->has((int) $productId)) {
                throw new InventoryException('Seleccionaste cajas de un producto que no se pidió.');
            }

            if (count($ids) > $requested[(int) $productId]) {
                $name = $request->items->firstWhere('product_id', (int) $productId)?->product->name;

                $asked = $requested[(int) $productId];

                throw new InventoryException(($asked === 1 ? 'Solo se pidió 1 caja' : "Solo se pidieron {$asked} cajas")." de {$name}; quita ".(count($ids) - $asked).' para poder autorizar.');
            }

            $selected[(int) $productId] = $ids;
        }

        if ($selected === []) {
            throw new InventoryException('Selecciona al menos una caja o rechaza la solicitud.');
        }

        $all = array_merge(...array_values($selected));

        if (count($all) !== count(array_unique($all))) {
            throw new InventoryException('Hay cajas repetidas en la selección.');
        }

        $boxes = Box::query()->whereKey($all)->get()->keyBy('id');

        foreach ($selected as $productId => $ids) {
            foreach ($ids as $id) {
                if (($boxes->get($id)?->product_id) !== $productId) {
                    throw new InventoryException('Una de las cajas seleccionadas no corresponde al producto pedido.');
                }
            }
        }

        return $selected;
    }

    private function close(ExitRequest $request, ExitRequestStatus $status, ?User $reviewer, ?string $notes, string $event, ?User $actor = null): ExitRequest
    {
        return DB::transaction(function () use ($request, $status, $reviewer, $notes, $event, $actor): ExitRequest {
            $locked = ExitRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new InventoryException('Esta solicitud ya fue resuelta.');
            }

            $locked->update([
                'status' => $status,
                'decided_by' => $reviewer?->id,
                'decided_at' => now(),
                'decision_notes' => $this->clean($notes),
            ]);

            AuditLog::record($reviewer ?? $actor, $event, $locked, null, ['reference' => $locked->reference(), 'notes' => $notes]);

            return $locked;
        });
    }

    private function clean(?string $notes): ?string
    {
        $notes = trim((string) $notes);

        return $notes === '' ? null : $notes;
    }
}
