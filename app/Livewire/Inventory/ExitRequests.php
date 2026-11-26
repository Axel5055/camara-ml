<?php

namespace App\Livewire\Inventory;

use App\Enums\ExitRequestStatus;
use App\Enums\UserRole;
use App\Livewire\Concerns\ChoosesTicketCopies;
use App\Livewire\Concerns\ReportsInventoryErrors;
use App\Models\Box;
use App\Models\ExitRequest;
use App\Services\Inventory\ReviewExitRequest;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Solicitudes de salida')]
class ExitRequests extends Component
{
    use ChoosesTicketCopies, ReportsInventoryErrors, WithPagination;

    /** Boxes listed per product in the review, oldest first; always at least as many as were requested. */
    private const BOXES_LISTED = 300;

    /** pending | approved | rejected | cancelled | all */
    #[Url(as: 'estado')]
    public string $status = 'pending';

    /** "Mis solicitudes": only what the user asked for, instead of what is asked to them. */
    #[Locked]
    public bool $mine = false;

    public ?int $viewingId = null;

    /**
     * Boxes chosen to fulfil the request, keyed by product id (the oldest ones come preselected).
     *
     * @var array<int, array<int, int|string>>
     */
    public array $selected = [];

    public string $rejectReason = '';

    public string $approveNotes = '';

    public string $flash = '';

    /** Second step of the review: summary and confirmation before the exit is registered. */
    public bool $confirming = false;

    /** Exit just registered by authorizing the request; its ticket is offered right after. */
    public ?int $printExitId = null;

    public function mount(): void
    {
        $this->flash = (string) session()->pull('status', '');
        $this->mine = $this->mine || request()->routeIs('exit-requests.mine') || Auth::user()->role === UserRole::Requester;

        if ($this->mine && ! request()->has('estado')) {
            $this->status = 'all';
        }
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return Builder<ExitRequest>
     */
    private function visibleRequests(): Builder
    {
        return ExitRequest::query()->visibleTo(Auth::user());
    }

    /**
     * The requests of the list being looked at; opening or acting on one only needs it to be visible.
     *
     * @return Builder<ExitRequest>
     */
    private function listedRequests(): Builder
    {
        return $this->mine
            ? $this->visibleRequests()->where('user_id', Auth::id())
            : ExitRequest::query()->incomingFor(Auth::user());
    }

    private function viewingRequest(): ExitRequest
    {
        $request = $this->visibleRequests()->with('items')->findOrFail($this->viewingId);

        Gate::authorize('view', $request);

        return $request;
    }

    public function openRequest(int $id): void
    {
        $this->viewingId = $id;
        $request = $this->viewingRequest();
        $this->reset('rejectReason', 'approveNotes', 'selected', 'confirming', 'printExitId');
        $this->ticketCopies = 1;
        $this->resetErrorBag();

        if ($request->isPending() && Gate::allows('decide', $request)) {
            foreach ($this->availableBoxes($request) as $productId => $boxes) {
                $wanted = (int) $request->items->firstWhere('product_id', $productId)?->boxes_requested;
                $this->selected[$productId] = $boxes->take($wanted)->pluck('id')->map(fn (int $id): string => (string) $id)->all();
            }
        }

        Flux::modal('exit-request')->show();
    }

    /**
     * The boxes the warehouse user may send, per requested product, oldest first (the suggestion is first in,
     * first out).
     *
     * @return Collection<int, EloquentCollection<int, Box>>
     */
    private function availableBoxes(ExitRequest $request): Collection
    {
        return $request->items->mapWithKeys(fn ($item): array => [
            $item->product_id => Box::query()
                ->availableIn($request->chamber_id)
                ->where('product_id', $item->product_id)
                ->orderBy('entered_at')
                ->orderBy('id')
                ->limit(max(self::BOXES_LISTED, $item->boxes_requested))
                ->get(['id', 'barcode', 'current_weight_kg', 'entered_at']),
        ]);
    }

    /**
     * First click on "Autorizar": checks the chosen boxes and shows the summary to confirm.
     */
    public function askToApprove(): void
    {
        $request = $this->viewingRequest();

        if (! $request->isPending()) {
            $this->addError('operation', 'Esta solicitud ya fue resuelta.');

            return;
        }

        Gate::authorize('decide', $request);

        $this->validate(['approveNotes' => ['nullable', 'string', 'max:500']], attributes: ['approveNotes' => 'comentario']);
        $this->resetErrorBag('operation');

        $total = 0;

        foreach ($request->items()->with('product:id,name')->get() as $item) {
            $count = count($this->selected[$item->product_id] ?? []);
            $total += $count;

            if ($count > $item->boxes_requested) {
                $this->addError('operation', ($item->boxes_requested === 1 ? 'Solo se pidió 1 caja' : "Solo se pidieron {$item->boxes_requested} cajas")." de {$item->product->name}; quita ".($count - $item->boxes_requested).' para poder autorizar.');

                return;
            }
        }

        if ($total === 0) {
            $this->addError('operation', 'Selecciona al menos una caja o rechaza la solicitud.');

            return;
        }

        $this->confirming = true;
    }

    public function backToReview(): void
    {
        $this->confirming = false;
    }

    public function approve(ReviewExitRequest $review): void
    {
        $request = $this->viewingRequest();

        if (! $request->isPending()) {
            $this->addError('operation', 'Esta solicitud ya fue resuelta.');

            return;
        }

        Gate::authorize('decide', $request);

        $this->validate(['approveNotes' => ['nullable', 'string', 'max:500']], attributes: ['approveNotes' => 'comentario']);

        $exit = null;
        $done = $this->attempt(function () use ($review, $request, &$exit): void {
            $exit = $review->approve(Auth::user(), $request, $this->selected, $this->approveNotes);
        });

        $this->confirming = false;

        if ($done && $exit !== null) {
            $this->printExitId = $exit->id;
            $this->ticketCopies = 1;
        }
    }

    /**
     * Prints the ticket of the exit created by the authorization, on this computer.
     */
    public function printTicket(): void
    {
        $request = $this->viewingRequest();

        if ($this->printExitId === null || $request->exit_id !== $this->printExitId) {
            $this->addError('operation', 'Esta solicitud no tiene una salida para imprimir.');

            return;
        }

        Gate::authorize('view', $request->exit()->firstOrFail());

        $this->dispatch('print-ticket', url: route('exits.ticket', ['exit' => $this->printExitId, 'auto' => 1, 'copies' => $this->ticketCopies()]));
        $this->finishReview();
    }

    public function skipTicket(): void
    {
        $this->finishReview();
    }

    private function finishReview(): void
    {
        $this->reset('printExitId', 'confirming', 'selected');
        $this->ticketCopies = 1;
        Flux::modal('exit-request')->close();
    }

    public function reject(ReviewExitRequest $review): void
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
            Flux::modal('exit-request')->close();
            Flux::toast(text: 'Solicitud rechazada.', variant: 'success');
        }
    }

    /**
     * The warehouse user checks an authorized request: its exit moves from "Salidas por solicitud" to the direct ones.
     */
    public function validateRequest(int $id, ReviewExitRequest $review): void
    {
        $request = $this->visibleRequests()->findOrFail($id);
        Gate::authorize('validate', $request);

        $done = $this->attempt(fn () => $review->validate(Auth::user(), $request));

        if ($done) {
            Flux::modal('exit-request')->close();
            Flux::toast(text: 'Solicitud validada: su salida pasó a Directas.', variant: 'success');
        }
    }

    public function cancelRequest(int $id, ReviewExitRequest $review): void
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
        $requests = $this->listedRequests()
            ->when(in_array($this->status, array_column(ExitRequestStatus::cases(), 'value'), true), fn (Builder $query) => $query->where('status', $this->status))
            ->with(['branch:id,name', 'chamber:id,name,branch_id', 'chamber.branch:id,name', 'user:id,name', 'decider:id,name', 'validator:id,name', 'exit:id,reference,origin_chamber_id,dest_chamber_id'])
            ->orderByRaw("status = 'pending' desc")
            ->orderByDesc('created_at')
            ->paginate(20);

        $viewing = $this->viewingId !== null
            ? $this->visibleRequests()->with(['branch', 'chamber.branch', 'user:id,name', 'decider:id,name', 'validator:id,name', 'exit:id,reference,origin_chamber_id,dest_chamber_id', 'items.product:id,name'])->find($this->viewingId)
            : null;

        $canDecide = false;
        $boxesByProduct = collect();

        if ($viewing !== null && Gate::allows('decide', $viewing)) {
            $canDecide = true;
            $boxesByProduct = $this->availableBoxes($viewing);
        }

        return view('livewire.inventory.exit-requests', [
            'requests' => $requests,
            'viewing' => $viewing,
            'canDecide' => $canDecide,
            'boxesByProduct' => $boxesByProduct,
            'pendingCount' => $this->listedRequests()->where('status', ExitRequestStatus::Pending)->count(),
            'statuses' => ExitRequestStatus::cases(),
            // A super administrator also asks from the general list; warehouse users only from their own one.
            'showNewRequest' => $this->mine || Auth::user()->isSuperAdmin(),
        ]);
    }
}
