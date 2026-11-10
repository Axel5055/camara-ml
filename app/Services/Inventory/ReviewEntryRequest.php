<?php

namespace App\Services\Inventory;

use App\Enums\EntryRequestStatus;
use App\Enums\UserRole;
use App\Exceptions\InventoryException;
use App\Models\AuditLog;
use App\Models\Entry;
use App\Models\EntryRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ReviewEntryRequest
{
    public function __construct(private RegisterEntry $registerEntry) {}

    /**
     * Authorizes the request: the entry is registered for the user who asked, with the data they captured.
     * The request keeps the link to the entry and to who authorized it.
     */
    public function approve(User $reviewer, EntryRequest $request, ?string $notes = null): Entry
    {
        $this->assertMayDecide($reviewer, $request);

        $entry = $this->registerEntry->handle(
            $request->user,
            $request->chamber,
            $request->groups,
            $request->occurred_at->toImmutable(),
            $request->notes,
            $request->client_token,
        );

        DB::transaction(function () use ($reviewer, $request, $entry, $notes): void {
            $locked = EntryRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                return;
            }

            $locked->update([
                'status' => EntryRequestStatus::Approved,
                'decided_by' => $reviewer->id,
                'decided_at' => now(),
                'decision_notes' => $this->clean($notes),
                'entry_id' => $entry->id,
            ]);

            AuditLog::record($reviewer, 'entry_request.approved', $locked, null, ['entry_id' => $entry->id, 'reference' => $entry->reference]);
        });

        return $entry;
    }

    public function reject(User $reviewer, EntryRequest $request, string $reason): EntryRequest
    {
        $this->assertMayDecide($reviewer, $request);

        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw new InventoryException('Indica el motivo del rechazo (mínimo 5 caracteres).');
        }

        return $this->close($request, EntryRequestStatus::Rejected, $reviewer, $reason, 'entry_request.rejected');
    }

    /**
     * The requester withdraws their own pending request.
     */
    public function cancel(User $user, EntryRequest $request): EntryRequest
    {
        if ($request->user_id !== $user->id) {
            throw new AuthorizationException('Solo quien hizo la solicitud puede cancelarla.');
        }

        return $this->close($request, EntryRequestStatus::Cancelled, null, null, 'entry_request.cancelled', $user);
    }

    private function assertMayDecide(User $reviewer, EntryRequest $request): void
    {
        if (! $reviewer->is_active || ! in_array($reviewer->role, [UserRole::Admin, UserRole::SuperAdmin], true)) {
            throw new AuthorizationException('Solo un administrador puede autorizar o rechazar entradas.');
        }

        if ($request->user_id === $reviewer->id) {
            throw new AuthorizationException('No puedes resolver tu propia solicitud.');
        }

        if (! $request->fresh()?->isPending()) {
            throw new InventoryException('Esta solicitud ya fue resuelta.');
        }
    }

    private function close(EntryRequest $request, EntryRequestStatus $status, ?User $reviewer, ?string $notes, string $event, ?User $actor = null): EntryRequest
    {
        return DB::transaction(function () use ($request, $status, $reviewer, $notes, $event, $actor): EntryRequest {
            $locked = EntryRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new InventoryException('Esta solicitud ya fue resuelta.');
            }

            $locked->update([
                'status' => $status,
                'decided_by' => $reviewer?->id,
                'decided_at' => now(),
                'decision_notes' => $this->clean($notes),
            ]);

            AuditLog::record($reviewer ?? $actor, $event, $locked, null, ['notes' => $notes]);

            return $locked;
        });
    }

    private function clean(?string $notes): ?string
    {
        $notes = trim((string) $notes);

        return $notes === '' ? null : $notes;
    }
}
