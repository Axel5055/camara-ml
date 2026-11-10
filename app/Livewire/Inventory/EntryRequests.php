<?php

namespace App\Livewire\Inventory;

use App\Enums\EntryRequestStatus;
use App\Livewire\Concerns\ChoosesTicketCopies;
use App\Livewire\Concerns\InteractsWithChambers;
use App\Livewire\Concerns\ReportsInventoryErrors;
use App\Models\EntryRequest;
use App\Models\Product;
use App\Services\Inventory\ReviewEntryRequest;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Solicitudes de entrada')]
class EntryRequests extends Component
{
    use ChoosesTicketCopies, InteractsWithChambers, ReportsInventoryErrors, WithPagination;

    /** pending | approved | rejected | cancelled | all */
    #[Url(as: 'estado')]
    public string $status = 'pending';

    public ?int $viewingId = null;

    public string $rejectReason = '';

    public string $approveNotes = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Requests the user may see: administrators see all of them; a warehouse user sees those of their chambers.
     *
     * @return Builder<EntryRequest>
     */
    private function visibleRequests(): Builder
    {
        $visible = $this->visibleChamberIds();

        return EntryRequest::query()
            ->when($visible !== null, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereIn('chamber_id', $visible)->orWhere('user_id', Auth::id())));
    }

    private function viewingRequest(): EntryRequest
    {
        $request = $this->visibleRequests()->findOrFail($this->viewingId);

        Gate::authorize('view', $request);

        return $request;
    }

    public function openRequest(int $id): void
    {
        $this->viewingId = $id;
        $this->viewingRequest();
        $this->reset('rejectReason', 'approveNotes');
        $this->ticketCopies = 1;
        $this->resetErrorBag();
        Flux::modal('entry-request')->show();
    }

    public function approve(ReviewEntryRequest $review): void
    {
        $request = $this->viewingRequest();

        if (! $request->isPending()) {
            $this->addError('operation', 'Esta solicitud ya fue resuelta.');

            return;
        }

        Gate::authorize('decide', $request);

        $this->validate(['approveNotes' => ['nullable', 'string', 'max:500']], attributes: ['approveNotes' => 'comentario']);

        $entry = null;
        $done = $this->attempt(function () use ($review, $request, &$entry): void {
            $entry = $review->approve(Auth::user(), $request, $this->approveNotes);
        });

        if ($done && $entry !== null) {
            Flux::modal('entry-request')->close();
            Flux::toast(text: "Entrada {$entry->reference} registrada.", variant: 'success');
        }
    }

    public function reject(ReviewEntryRequest $review): void
    {
        $request = $this->viewingRequest();

        if (! $request->isPending()) {
            $this->addError('operation', 'Esta solicitud ya fue resuelta.');

            return;
        }

        Gate::authorize('decide', $request);

        $this->validate(['rejectReason' => ['required', 'string', 'min:5', 'max:500']], attributes: ['rejectReason' => 'motivo']);

        $done = $this->attempt(fn () => $review->reject(Auth::user(), $request, $this->rejectReason));

        if ($done) {
            Flux::modal('entry-request')->close();
            Flux::toast(text: 'Solicitud rechazada.', variant: 'success');
        }
    }

    /**
     * Prints the ticket of the entry created by an authorized request, on this computer.
     */
    public function printTicket(): void
    {
        $request = $this->viewingRequest();

        if ($request->status !== EntryRequestStatus::Approved || $request->entry_id === null) {
            $this->addError('operation', 'Solo las solicitudes autorizadas tienen ticket.');

            return;
        }

        Gate::authorize('view', $request->entry);

        $this->dispatch('print-ticket', url: route('entries.ticket', ['entry' => $request->entry_id, 'auto' => 1, 'copies' => $this->ticketCopies()]));
    }

    public function cancelRequest(int $id, ReviewEntryRequest $review): void
    {
        $request = $this->visibleRequests()->findOrFail($id);
        Gate::authorize('cancel', $request);

        $done = $this->attempt(fn () => $review->cancel(Auth::user(), $request));

        if ($done) {
            Flux::toast(text: 'Solicitud cancelada.', variant: 'success');
        }
    }

    public function render(): View
    {
        $requests = $this->visibleRequests()
            ->when(in_array($this->status, array_column(EntryRequestStatus::cases(), 'value'), true), fn (Builder $query) => $query->where('status', $this->status))
            ->with(['chamber:id,name,branch_id', 'chamber.branch:id,name', 'user:id,name', 'decider:id,name', 'entry:id,reference'])
            ->orderByRaw("status = 'pending' desc")
            ->orderByDesc('created_at')
            ->paginate(20);

        $viewing = $this->viewingId !== null
            ? $this->visibleRequests()->with(['chamber.branch', 'user:id,name', 'decider:id,name', 'entry:id,reference'])->find($this->viewingId)
            : null;

        $products = $viewing !== null
            ? Product::query()->whereKey(array_column($viewing->groups, 'product_id'))->pluck('name', 'id')
            : collect();

        return view('livewire.inventory.entry-requests', [
            'requests' => $requests,
            'viewing' => $viewing,
            'products' => $products,
            'pendingCount' => $this->visibleRequests()->where('status', EntryRequestStatus::Pending)->count(),
            'canDecide' => $viewing !== null && Gate::allows('decide', $viewing),
            'statuses' => EntryRequestStatus::cases(),
        ]);
    }
}
