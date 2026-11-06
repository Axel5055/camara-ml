<?php

namespace App\Livewire\Concerns;

trait ChoosesTicketCopies
{
    public int|string $ticketCopies = 1;

    /**
     * Copies of the ticket to print (1 to 10); anything else typed falls back to the nearest valid value.
     */
    protected function ticketCopies(): int
    {
        return max(1, min(10, (int) $this->ticketCopies));
    }
}
