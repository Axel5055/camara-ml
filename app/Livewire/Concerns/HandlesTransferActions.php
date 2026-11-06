<?php

namespace App\Livewire\Concerns;

use App\Models\Transfer;
use App\Services\Inventory\CancelTransfer;
use App\Services\Inventory\ReceiveTransfer;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Receive and cancel flows of a transfer, shared by the transfers list and the transfer detail page.
 * The matching modals live in the `livewire.inventory.partials.transfer-modals` view.
 */
trait HandlesTransferActions
{
    use ReportsInventoryErrors;

    public ?int $actingId = null;

    /** @var array<int, int|string> box ids physically received */
    public array $received = [];

    /** @var array<int|string, string|null> measured weights by box id */
    public array $weights = [];

    public string $reason = '';

    public string $receiveNotes = '';

    /**
     * What the user is about to confirm when receiving, shown in the confirmation step.
     *
     * @var array{total: int, arrived: int, missing: int, differences: array<int, array{barcode: string, expected: string, actual: string}>}
     */
    public array $confirmSummary = ['total' => 0, 'arrived' => 0, 'missing' => 0, 'differences' => []];

    public function askReceive(int $id): void
    {
        $transfer = Transfer::query()->with('items')->findOrFail($id);
        Gate::authorize('receive', $transfer);

        $this->actingId = $transfer->id;
        $this->received = $transfer->items->pluck('box_id')->all();
        $this->weights = [];
        $this->receiveNotes = '';
        $this->resetErrorBag();
        Flux::modal('receive-transfer')->show();
    }

    /**
     * First step of receiving: summarize what was captured and ask for a final confirmation.
     */
    public function askConfirmReceive(): void
    {
        $transfer = Transfer::query()->with('items.box:id,barcode')->findOrFail($this->actingId);
        Gate::authorize('receive', $transfer);

        $arrivedIds = array_map('intval', $this->received);
        $differences = [];
        $arrived = 0;

        foreach ($transfer->items as $item) {
            if (! in_array($item->box_id, $arrivedIds, true)) {
                continue;
            }

            $arrived++;
            $typed = $this->weights[$item->box_id] ?? null;

            if ($typed !== null && $typed !== '' && is_numeric($typed) && round((float) $typed - (float) $item->expected_weight_kg, 3) !== 0.0) {
                $differences[] = [
                    'barcode' => $item->box->barcode,
                    'expected' => rtrim(rtrim(number_format((float) $item->expected_weight_kg, 3, '.', ','), '0'), '.'),
                    'actual' => rtrim(rtrim(number_format((float) $typed, 3, '.', ','), '0'), '.'),
                ];
            }
        }

        $this->confirmSummary = [
            'total' => $transfer->items->count(),
            'arrived' => $arrived,
            'missing' => $transfer->items->count() - $arrived,
            'differences' => $differences,
        ];

        Flux::modal('confirm-receive')->show();
    }

    public function receive(ReceiveTransfer $receiveTransfer): void
    {
        $transfer = Transfer::query()->findOrFail($this->actingId);
        Gate::authorize('receive', $transfer);

        $weights = array_filter($this->weights, fn ($weight): bool => $weight !== '' && $weight !== null);

        $done = $this->attempt(fn () => $receiveTransfer->handle(Auth::user(), $transfer, $this->received, $weights, $this->receiveNotes));

        Flux::modal('confirm-receive')->close();

        if ($done) {
            Flux::modal('receive-transfer')->close();
            Flux::toast(text: 'Transferencia recibida.', variant: 'success');
        }
    }

    public function askCancel(int $id): void
    {
        Gate::authorize('cancel', Transfer::query()->findOrFail($id));

        $this->actingId = $id;
        $this->reason = '';
        $this->resetErrorBag();
        Flux::modal('cancel-transfer')->show();
    }

    public function cancel(CancelTransfer $cancelTransfer): void
    {
        $transfer = Transfer::query()->with('exit')->findOrFail($this->actingId);
        Gate::authorize('cancel', $transfer);

        $done = $this->attempt(fn () => $cancelTransfer->handle(Auth::user(), $transfer, $this->reason));

        if ($done) {
            Flux::modal('cancel-transfer')->close();
            Flux::toast(text: 'Transferencia cancelada.', variant: 'success');
        }
    }

    /**
     * The transfer the receive and cancel modals are working on, with what they display.
     */
    protected function actingTransfer(): ?Transfer
    {
        return $this->actingId
            ? Transfer::query()->with('originChamber:id,name', 'destChamber:id,name', 'items.box:id,barcode,current_weight_kg,product_id,unit_cost_mxn', 'items.box.product:id,name')->find($this->actingId)
            : null;
    }
}
