<?php

namespace App\Livewire;

use App\Enums\EntryRequestStatus;
use App\Livewire\Concerns\ChoosesTicketCopies;
use App\Models\EntryRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Tells a warehouse user that an entry they asked for was authorized and offers to print its ticket on their
 * own computer, where their printer is. It appears once per request.
 */
class ApprovedEntryPrompt extends Component
{
    use ChoosesTicketCopies;

    /**
     * The oldest authorized request of the user whose ticket has not been offered yet.
     */
    private function nextRequest(): ?EntryRequest
    {
        return EntryRequest::query()
            ->where('user_id', Auth::id())
            ->where('status', EntryRequestStatus::Approved)
            ->whereNotNull('entry_id')
            ->whereNull('ticket_prompted_at')
            ->with(['entry:id,reference', 'chamber:id,name', 'decider:id,name'])
            ->orderBy('decided_at')
            ->first();
    }

    public function printTicket(): void
    {
        $request = $this->nextRequest();

        if ($request === null) {
            return;
        }

        $request->update(['ticket_prompted_at' => now()]);
        $this->dispatch('print-ticket', url: route('entries.ticket', ['entry' => $request->entry_id, 'auto' => 1, 'copies' => $this->ticketCopies()]));
        $this->ticketCopies = 1;
    }

    public function dismiss(): void
    {
        $this->nextRequest()?->update(['ticket_prompted_at' => now()]);
        $this->ticketCopies = 1;
    }

    public function render(): View
    {
        return view('livewire.approved-entry-prompt', ['request' => $this->nextRequest()]);
    }
}
