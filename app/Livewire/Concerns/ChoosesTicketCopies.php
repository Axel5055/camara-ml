<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

trait ChoosesTicketCopies
{
    public int|string $ticketCopies = 1;

    /** Only super administrators may skip the ticket when they register something. */
    public bool $printTicketOnSave = true;

    protected function shouldPrintTicket(): bool
    {
        return ! Auth::user()->isSuperAdmin() || $this->printTicketOnSave;
    }

    /**
     * Copies of the ticket to print (1 to 10); anything else typed falls back to the nearest valid value.
     */
    protected function ticketCopies(): int
    {
        return max(1, min(10, (int) $this->ticketCopies));
    }
}
