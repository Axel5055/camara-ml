<?php

namespace App\Livewire\Inventory;

use App\Livewire\Concerns\ChoosesTicketCopies;
use App\Livewire\Concerns\InteractsWithChambers;
use App\Livewire\Concerns\ReportsInventoryErrors;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\Product;
use App\Services\Inventory\RegisterExit;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Nueva salida')]
class CreateExit extends Component
{
    use ChoosesTicketCopies, InteractsWithChambers, ReportsInventoryErrors;

    /** Barcodes listed per line before the list is cut short. */
    private const BARCODES_PER_LINE = 100;

    public ?int $originChamberId = null;

    public ?int $destBranchId = null;

    /** Chamber of the destination branch that receives the delivery; informational only, the exit is always direct. */
    public ?int $deliveryChamberId = null;

    public string $requester = '';

    public string $notes = '';

    /** Only administrators can change it; for everyone else the exit is stamped when it is saved. */
    public string $occurredAt = '';

    public string $clientToken = '';

    public ?int $productId = null;

    public string $quantity = '';

    /**
     * Boxes already taken for this exit, one line per product, weight and price.
     *
     * @var array<int, array{product_id: int, product: string, weight: string, cost: string, box_ids: array<int, int>}>
     */
    public array $lines = [];

    /**
     * Variants (weight and price) offered in the modal when the requested product has more than one.
     *
     * @var array<int, array{weight: string, cost: string, available: int}>
     */
    public array $variants = [];

    /** @var array<int, string> boxes requested per variant, by variant index */
    public array $variantQuantities = [];

    public int $pendingQuantity = 0;

    public ?int $pendingProductId = null;

    public function mount(): void
    {
        $this->occurredAt = now()->format('Y-m-d\TH:i');
        $this->clientToken = (string) Str::uuid();
        $this->originChamberId = $this->onlyChamberId();
    }

    public function updatedDestBranchId(): void
    {
        $this->deliveryChamberId = null;
    }

    public function updatedOriginChamberId(): void
    {
        $this->lines = [];
        $this->productId = null;
        $this->quantity = '';
    }

    /**
     * Validation rules for the destination; an exit only needs a branch and is always confirmed on save.
     *
     * @return array<string, array<int, string>>
     */
    protected function destinationRules(): array
    {
        return [
            'destBranchId' => ['required', 'exists:branches,id'],
            'deliveryChamberId' => ['nullable', 'integer', 'exists:chambers,id'],
        ];
    }

    /**
     * Chambers of the destination branch that can be recorded on a direct delivery.
     *
     * @return Collection<int, Chamber>
     */
    protected function deliveryChambers(): Collection
    {
        if ($this->isTransfer() || $this->destBranchId === null) {
            return new Collection;
        }

        return Chamber::query()
            ->where('branch_id', $this->destBranchId)
            ->where('is_active', true)
            ->when($this->originChamberId, fn (Builder $query, int $id) => $query->where('id', '!=', $id))
            ->orderBy('name')->get();
    }

    /**
     * The chosen delivery chamber, only if it really is one of the offered chambers of the destination branch.
     */
    protected function deliveryChamber(): ?Chamber
    {
        return $this->deliveryChamberId === null ? null : $this->deliveryChambers()->firstWhere('id', $this->deliveryChamberId);
    }

    /**
     * Destination branch and chamber handed to the service; exits never go through a transfer.
     *
     * @return array{0: Branch, 1: Chamber|null}
     */
    protected function destination(): array
    {
        return [Branch::query()->findOrFail($this->destBranchId), null];
    }

    protected function isTransfer(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function destinationViewData(): array
    {
        return ['deliveryChambers' => $this->deliveryChambers()];
    }

    /**
     * The origin id comes from the browser, so using it requires that the user may work with that chamber.
     */
    private function originIsAllowed(): bool
    {
        return $this->originChamberId !== null
            && Chamber::query()->accessibleBy(Auth::user())->where('is_active', true)->whereKey($this->originChamberId)->exists();
    }

    /**
     * @return array<int, int>
     */
    private function takenIds(): array
    {
        return array_merge([], ...array_column($this->lines, 'box_ids'));
    }

    /**
     * Available boxes of a product in the origin chamber that are not already in this exit.
     *
     * @return Builder<Box>
     */
    private function availableBoxes(int $productId): Builder
    {
        return Box::query()
            ->availableIn((int) $this->originChamberId)
            ->where('product_id', $productId)
            ->when($this->takenIds() !== [], fn (Builder $query) => $query->whereNotIn('id', $this->takenIds()));
    }

    /**
     * Distinct weight/price combinations still available for a product, with how many boxes each has.
     *
     * @return array<int, array{weight: string, cost: string, available: int}>
     */
    private function variantsOf(int $productId): array
    {
        return $this->availableBoxes($productId)->toBase()
            ->selectRaw('current_weight_kg as weight, unit_cost_mxn as cost, count(*) as available')
            ->groupBy('current_weight_kg', 'unit_cost_mxn')
            ->orderBy('current_weight_kg')->orderBy('unit_cost_mxn')
            ->get()
            ->map(fn ($row): array => ['weight' => (string) $row->weight, 'cost' => (string) $row->cost, 'available' => (int) $row->available])
            ->all();
    }

    /**
     * Take the oldest boxes of one variant (first in, first out) and add them to the exit.
     */
    private function takeBoxes(Product $product, string $weight, string $cost, int $quantity): bool
    {
        $ids = $this->availableBoxes($product->id)
            ->where('current_weight_kg', $weight)->where('unit_cost_mxn', $cost)
            ->orderBy('entered_at')->orderBy('id')
            ->limit($quantity)->pluck('id')->all();

        if (count($ids) < $quantity) {
            $this->addError('quantity', 'Las existencias cambiaron mientras capturabas. Intenta de nuevo.');

            return false;
        }

        foreach ($this->lines as $index => $line) {
            if ($line['product_id'] === $product->id && $line['weight'] === $weight && $line['cost'] === $cost) {
                $this->lines[$index]['box_ids'] = [...$line['box_ids'], ...$ids];

                return true;
            }
        }

        $this->lines[] = ['product_id' => $product->id, 'product' => $product->name, 'weight' => $weight, 'cost' => $cost, 'box_ids' => $ids];

        return true;
    }

    public function addProduct(): void
    {
        abort_unless($this->originIsAllowed(), 403);

        $this->resetErrorBag();
        $this->validate([
            'productId' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ], [], ['productId' => 'el producto', 'quantity' => 'la cantidad de cajas']);

        $product = Product::query()->findOrFail($this->productId);
        $quantity = (int) $this->quantity;
        $variants = $this->variantsOf($product->id);
        $available = array_sum(array_column($variants, 'available'));

        if ($quantity > $available) {
            $this->addError('quantity', $available === 0
                ? "No hay cajas disponibles de {$product->name} en esta cámara."
                : "No hay suficientes cajas de {$product->name}: solicitaste {$quantity} y solo hay {$available} disponibles.");

            return;
        }

        if (count($variants) === 1) {
            if ($this->takeBoxes($product, $variants[0]['weight'], $variants[0]['cost'], $quantity)) {
                $this->reset('productId', 'quantity');
            }

            return;
        }

        $this->variants = $variants;
        $this->variantQuantities = [];
        $this->pendingQuantity = $quantity;
        $this->pendingProductId = $product->id;
        Flux::modal('variants')->show();
    }

    /**
     * Fill one variant with as many boxes as it can give toward the pending quantity.
     */
    public function fillVariant(int $index): void
    {
        $others = array_sum(array_map('intval', array_diff_key($this->variantQuantities, [$index => true])));
        $room = max(0, $this->pendingQuantity - $others);

        $this->variantQuantities[$index] = (string) min($room, $this->variants[$index]['available'] ?? 0);
    }

    public function confirmVariants(): void
    {
        abort_unless($this->originIsAllowed() && $this->pendingProductId !== null, 403);

        $this->resetErrorBag('variants');
        $chosen = [];

        foreach ($this->variants as $index => $variant) {
            $quantity = (int) ($this->variantQuantities[$index] ?? 0);

            if ($quantity < 0 || $quantity > $variant['available']) {
                $this->addError('variants', "Solo hay {$variant['available']} cajas de {$variant['weight']} kg a \${$variant['cost']}.");

                return;
            }

            if ($quantity > 0) {
                $chosen[$index] = $quantity;
            }
        }

        if (array_sum($chosen) !== $this->pendingQuantity) {
            $this->addError('variants', "Distribuye exactamente {$this->pendingQuantity} cajas entre las variantes (llevas ".array_sum($chosen).').');

            return;
        }

        $product = Product::query()->findOrFail($this->pendingProductId);

        foreach ($chosen as $index => $quantity) {
            if (! $this->takeBoxes($product, $this->variants[$index]['weight'], $this->variants[$index]['cost'], $quantity)) {
                return;
            }
        }

        Flux::modal('variants')->close();
        $this->reset('productId', 'quantity', 'variants', 'variantQuantities', 'pendingQuantity', 'pendingProductId');
    }

    /**
     * Rebuilds the capture from the draft the browser kept (refresh, lost connection). Nothing from the browser is
     * trusted: the origin chamber is checked against the user's access and only the boxes that are still available
     * there come back, so an exit never takes boxes that moved in the meantime.
     *
     * @param  array<string, mixed>  $draft
     */
    public function restoreDraft(array $draft): void
    {
        $this->resetErrorBag();

        $originId = is_numeric($draft['originChamberId'] ?? null) ? (int) $draft['originChamberId'] : null;
        $this->originChamberId = $originId;

        if (! $this->originIsAllowed()) {
            $this->originChamberId = $this->onlyChamberId();
        }

        $this->requester = is_string($draft['requester'] ?? null) ? mb_substr($draft['requester'], 0, 255) : '';
        $this->notes = is_string($draft['notes'] ?? null) ? mb_substr($draft['notes'], 0, 2000) : '';

        if (is_string($draft['clientToken'] ?? null) && Str::isUuid($draft['clientToken'])) {
            $this->clientToken = $draft['clientToken'];
        }

        $this->restoreDestination($draft);

        $requested = array_values(array_unique(array_map('intval', array_filter(
            array_merge([], ...array_map(
                fn (mixed $line): array => is_array($line) && is_array($line['box_ids'] ?? null) ? array_slice($line['box_ids'], 0, 20000) : [],
                is_array($draft['lines'] ?? null) ? $draft['lines'] : [],
            )),
            'is_numeric',
        ))));

        $this->lines = $this->originIsAllowed() ? $this->restoredLines($requested) : [];
        $dropped = count($requested) - count($this->takenIds());

        $productId = is_numeric($draft['productId'] ?? null) ? (int) $draft['productId'] : 0;

        if ($productId > 0 && $this->originIsAllowed() && $this->availableBoxes($productId)->exists()) {
            $this->productId = $productId;
        }

        $this->quantity = is_numeric($draft['quantity'] ?? null) && ctype_digit((string) $draft['quantity']) && (int) $draft['quantity'] >= 1 && (int) $draft['quantity'] <= 100000
            ? (string) (int) $draft['quantity']
            : '';

        if ($dropped > 0) {
            Flux::toast(text: "Se recuperó la captura, pero {$dropped} caja(s) ya no están disponibles en la cámara y se omitieron.", variant: 'warning');
        } else {
            Flux::toast(text: 'Se recuperó tu captura anterior.', variant: 'success');
        }
    }

    /**
     * Destination fields of the draft: the branch and, optionally, the chamber that receives the delivery.
     *
     * @param  array<string, mixed>  $draft
     */
    protected function restoreDestination(array $draft): void
    {
        $branchId = is_numeric($draft['destBranchId'] ?? null) ? (int) $draft['destBranchId'] : 0;
        $this->destBranchId = $branchId > 0 && $this->activeBranches()->contains('id', $branchId) ? $branchId : null;

        $deliveryId = is_numeric($draft['deliveryChamberId'] ?? null) ? (int) $draft['deliveryChamberId'] : null;
        $this->deliveryChamberId = $deliveryId;

        if ($this->deliveryChamber() === null) {
            $this->deliveryChamberId = null;
        }
    }

    /**
     * The requested boxes that are still available in the origin chamber, grouped by product, weight and price.
     *
     * @param  array<int, int>  $boxIds
     * @return array<int, array{product_id: int, product: string, weight: string, cost: string, box_ids: array<int, int>}>
     */
    private function restoredLines(array $boxIds): array
    {
        $lines = [];

        foreach (array_chunk($boxIds, 1000) as $chunk) {
            Box::query()->availableIn((int) $this->originChamberId)
                ->whereIn('id', $chunk)->with('product:id,name')
                ->orderBy('entered_at')->orderBy('id')->get()
                ->each(function (Box $box) use (&$lines): void {
                    $key = $box->product_id.'|'.$box->current_weight_kg.'|'.$box->unit_cost_mxn;
                    $lines[$key] ??= [
                        'product_id' => $box->product_id,
                        'product' => $box->product->name,
                        'weight' => (string) $box->current_weight_kg,
                        'cost' => (string) $box->unit_cost_mxn,
                        'box_ids' => [],
                    ];
                    $lines[$key]['box_ids'][] = $box->id;
                });
        }

        return array_values($lines);
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function clearAll(): void
    {
        $this->reset('lines', 'productId', 'quantity', 'variants', 'variantQuantities', 'pendingQuantity', 'pendingProductId');
        $this->resetErrorBag();
    }

    /**
     * Validates the captured data; shared by the confirmation step and the final save.
     */
    private function formIsValid(): bool
    {
        $this->validate([
            'originChamberId' => ['required', 'exists:chambers,id'],
            ...$this->destinationRules(),
            'requester' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $this->isTransfer() && $this->deliveryChamberId !== null && $this->deliveryChamber() === null) {
            $this->addError('deliveryChamberId', 'La cámara elegida no es una cámara disponible de la sucursal destino.');

            return false;
        }

        if ($this->lines === []) {
            $this->addError('operation', $this->isTransfer() ? 'Agrega al menos una caja a la transferencia.' : 'Agrega al menos una caja a la salida.');

            return false;
        }

        return true;
    }

    /**
     * First step of registering: validate and ask the user for a final confirmation.
     */
    public function askConfirm(): void
    {
        if ($this->formIsValid()) {
            Flux::modal('confirm-save')->show();
        }
    }

    public function save(RegisterExit $registerExit): void
    {
        if (! $this->formIsValid()) {
            Flux::modal('confirm-save')->close();

            return;
        }

        // Warehouse users never choose the time: the exit is stamped when it is saved.
        $occurredAt = Auth::user()->isSuperAdmin() && $this->occurredAt !== '' ? CarbonImmutable::parse($this->occurredAt) : null;

        $exit = null;

        $done = $this->attempt(function () use ($registerExit, $occurredAt, &$exit): void {
            [$destBranch, $destChamber] = $this->destination();

            $exit = $registerExit->handle(
                Auth::user(),
                Chamber::query()->findOrFail($this->originChamberId),
                $destBranch,
                $destChamber,
                $this->requester,
                $this->takenIds(),
                $occurredAt,
                $this->notes ?: null,
                $this->clientToken,
                $this->deliveryChamber(),
            );
        });

        Flux::modal('confirm-save')->close();

        if ($done && $exit !== null) {
            if ($this->shouldPrintTicket()) {
                session()->flash('print_ticket', $this->isTransfer()
                    ? route('transfers.ticket', ['transfer' => $exit->transfer()->value('id'), 'auto' => 1, 'copies' => $this->ticketCopies()])
                    : route('exits.ticket', ['exit' => $exit, 'auto' => 1, 'copies' => $this->ticketCopies()]));
            }

            $this->dispatch('draft-saved');
            Flux::toast(text: $this->isTransfer() ? 'Transferencia enviada.' : 'Salida registrada.', variant: 'success');
            $this->redirectRoute($this->isTransfer() ? 'transfers.index' : 'exits.index', navigate: true);
        }
    }

    public function render(): View
    {
        $allowed = $this->originIsAllowed();

        $summaries = [];
        $totalBoxes = 0;
        $totalWeight = '0.000';
        $totalCost = '0.00';

        foreach ($this->lines as $line) {
            $count = count($line['box_ids']);
            $weight = Decimal::mul($line['weight'], (string) $count, Decimal::WEIGHT_SCALE);
            $cost = Decimal::mul($line['cost'], (string) $count, Decimal::MONEY_SCALE);

            $summaries[] = [...$line, 'count' => $count, 'total_weight' => $weight, 'total_cost' => $cost];
            $totalBoxes += $count;
            $totalWeight = Decimal::add($totalWeight, $weight, Decimal::WEIGHT_SCALE);
            $totalCost = Decimal::add($totalCost, $cost, Decimal::MONEY_SCALE);
        }

        $shownIds = array_merge([], ...array_map(fn (array $line): array => array_slice($line['box_ids'], 0, self::BARCODES_PER_LINE), $this->lines));

        return view('livewire.inventory.create-exit', [
            'chambers' => $this->accessibleChambers(),
            'branches' => $this->activeBranches(),
            'products' => $allowed
                ? Product::query()
                    ->whereIn('id', Box::query()->availableIn((int) $this->originChamberId)->select('product_id')->distinct())
                    ->orderBy('name')->get(['id', 'name', 'code'])
                : collect(),
            'availableForProduct' => $allowed && $this->productId ? array_sum(array_column($this->variantsOf($this->productId), 'available')) : null,
            'summaries' => $summaries,
            'barcodes' => Box::query()->whereIn('id', $shownIds)->pluck('barcode', 'id'),
            'totals' => ['boxes' => $totalBoxes, 'weight' => $totalWeight, 'cost' => $totalCost],
            'perLine' => self::BARCODES_PER_LINE,
            'showCosts' => Gate::allows('view-costs'),
            'isSuperAdmin' => Auth::user()->isSuperAdmin(),
            'pendingProduct' => $this->pendingProductId ? Product::query()->find($this->pendingProductId) : null,
            'isTransfer' => $this->isTransfer(),
            ...$this->destinationViewData(),
        ]);
    }
}
