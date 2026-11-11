<?php

namespace App\Services\Inventory;

use App\Enums\TransferStatus;
use App\Exceptions\InventoryException;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class CancelTransfer
{
    public function __construct(private ReverseExit $reverseExit) {}

    /**
     * Cancel a transfer (super administrators, administrators and managers within their scope); boxes return to the origin chamber. A transfer already
     * received is validated first: its boxes must still be untouched in the destination chamber.
     */
    public function handle(User $user, Transfer $transfer, string $reason): Transfer
    {
        $received = $transfer->status === TransferStatus::Received;

        if (! $user->canCorrectRecordsOf($transfer->originChamber, $transfer->destChamber)) {
            throw new AuthorizationException('No tienes permiso para cancelar esta transferencia.');
        }

        if (! $received && $transfer->status !== TransferStatus::InTransit) {
            throw new InventoryException("La transferencia {$transfer->reference} no está en tránsito ni recibida.");
        }

        $this->reverseExit->handle($user, $transfer->exit, $reason);

        return $transfer->refresh();
    }
}
