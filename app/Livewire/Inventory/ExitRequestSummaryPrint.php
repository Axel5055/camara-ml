<?php

namespace App\Livewire\Inventory;

use App\Livewire\Concerns\ChoosesTicketCopies;
use App\Livewire\Concerns\InteractsWithChambers;
use App\Models\Branch;
use App\Models\Chamber;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Button and dialog to print one ticket that adds up everything authorized from branch requests on a day or
 * over a range of dates. The ticket of each single exit is not affected.
 */
class ExitRequestSummaryPrint extends Component
{
    use ChoosesTicketCopies, InteractsWithChambers;

    /** Longest range, in days, a single summary may cover. */
    public const MAX_DAYS = 92;

    public string $from = '';

    public string $to = '';

    public ?int $chamberId = null;

    /** Branch that made the requests; null prints every branch. */
    public ?int $branchId = null;

    public function mount(): void
    {
        Gate::authorize('print-exit-summary');

        $this->setDay(Date::today());
    }

    public function today(): void
    {
        $this->setDay(Date::today());
    }

    public function yesterday(): void
    {
        $this->setDay(Date::yesterday());
    }

    private function setDay(\DateTimeInterface $day): void
    {
        $this->from = $this->to = $day->format('Y-m-d');
        $this->resetErrorBag();
    }

    public function print(): void
    {
        Gate::authorize('print-exit-summary');

        $this->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'chamberId' => ['nullable', 'integer'],
            'branchId' => ['nullable', 'integer', 'exists:branches,id'],
            'ticketCopies' => ['required', 'integer', 'min:1', 'max:10'],
        ], [
            'from.required' => 'Elige la fecha de inicio.',
            'from.date_format' => 'La fecha de inicio no es válida.',
            'to.required' => 'Elige la fecha final.',
            'to.date_format' => 'La fecha final no es válida.',
            'to.after_or_equal' => 'La fecha final no puede ser anterior a la de inicio.',
            'branchId.exists' => 'Elige una sucursal de la lista.',
            'ticketCopies.min' => 'Imprime al menos 1 copia.',
            'ticketCopies.max' => 'Máximo 10 copias.',
        ]);

        if (Date::parse($this->from)->diffInDays(Date::parse($this->to)) >= self::MAX_DAYS) {
            $this->addError('to', 'El rango no puede pasar de '.self::MAX_DAYS.' días.');

            return;
        }

        if ($this->chamberId !== null && ! Chamber::query()->accessibleBy(auth()->user())->whereKey($this->chamberId)->exists()) {
            $this->addError('chamberId', 'No tienes acceso a esa cámara.');

            return;
        }

        $this->dispatch('print-ticket', url: route('exit-requests.summary-ticket', array_filter([
            'desde' => $this->from,
            'hasta' => $this->to,
            'camara' => $this->chamberId,
            'sucursal' => $this->branchId,
            'auto' => 1,
            'copies' => $this->ticketCopies(),
        ])));

        Flux::modal('exit-request-summary')->close();
    }

    public function render(): View
    {
        return view('livewire.inventory.exit-request-summary-print', [
            'chambers' => $this->accessibleChambers(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'seesAllChambers' => auth()->user()->seesAllChambers(),
        ]);
    }
}
