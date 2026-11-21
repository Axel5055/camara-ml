<?php

namespace App\Livewire\Inventory;

use App\Livewire\Concerns\ReportsInventoryErrors;
use App\Models\Box;
use App\Models\Chamber;
use App\Models\ExitRequest;
use App\Models\Product;
use App\Services\Inventory\RequestExit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Nueva solicitud de salida')]
class CreateExitRequest extends Component
{
    use ReportsInventoryErrors;

    public ?int $chamberId = null;

    public string $notes = '';

    public string $clientToken = '';

    public ?int $productId = null;

    public string $quantity = '';

    /**
     * Products asked for so far, one line per product.
     *
     * @var array<int, array{product_id: int, product: string, boxes: int}>
     */
    public array $lines = [];

    public function mount(): void
    {
        Gate::authorize('request-exits');

        $this->clientToken = (string) Str::uuid();
        $chambers = $this->chambers();
        $this->chamberId = $chambers->count() === 1 ? $chambers->first()->id : null;
    }

    /**
     * Active chambers the requester may ask for product.
     *
     * @return Collection<int, Chamber>
     */
    private function chambers(): Collection
    {
        return Chamber::query()->requestableBy(Auth::user())->with('branch')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Product>
     */
    private function products(): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    public function updatedChamberId(): void
    {
        $this->resetErrorBag();
    }

    public function addProduct(): void
    {
        $this->resetErrorBag();

        $validated = $this->validate([
            'chamberId' => ['required', 'integer'],
            'productId' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.RequestExit::MAX_BOXES_PER_PRODUCT],
        ], attributes: ['chamberId' => 'cámara', 'productId' => 'producto', 'quantity' => 'cantidad de cajas']);

        $product = $this->products()->firstWhere('id', $validated['productId']);

        if ($product === null) {
            $this->addError('productId', 'Elige un producto de la lista.');

            return;
        }

        $this->addLine($product, (int) $validated['quantity']);
        $this->reset('productId', 'quantity');
    }

    private function addLine(Product $product, int $boxes): void
    {
        foreach ($this->lines as $index => $line) {
            if ($line['product_id'] === $product->id) {
                $this->lines[$index]['boxes'] = min($line['boxes'] + $boxes, RequestExit::MAX_BOXES_PER_PRODUCT);

                return;
            }
        }

        $this->lines[] = ['product_id' => $product->id, 'product' => $product->name, 'boxes' => $boxes];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(RequestExit $requestExit): void
    {
        $this->resetErrorBag();

        $this->validate([
            'chamberId' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], attributes: ['chamberId' => 'cámara', 'notes' => 'comentario']);

        $chamber = Chamber::query()->findOrFail($this->chamberId);
        Gate::authorize('create', [ExitRequest::class, $chamber]);

        $request = null;
        $done = $this->attempt(function () use ($requestExit, $chamber, &$request): void {
            $request = $requestExit->handle(
                Auth::user(),
                $chamber,
                array_map(fn (array $line): array => ['product_id' => $line['product_id'], 'boxes' => $line['boxes']], $this->lines),
                $this->notes,
                $this->clientToken,
            );
        });

        if ($done && $request !== null) {
            $this->dispatch('draft-saved');
            session()->flash('status', "Solicitud {$request->reference()} enviada.");

            $this->redirectRoute('exit-requests.index', navigate: true);
        }
    }

    /**
     * Rebuilds the capture from the draft the browser kept (refresh, lost connection). Nothing from the browser is
     * trusted: the chamber must still be allowed and every product must still exist and be active.
     *
     * @param  array<string, mixed>  $draft
     */
    public function restoreDraft(array $draft): void
    {
        $chamberId = is_numeric($draft['chamberId'] ?? null) ? (int) $draft['chamberId'] : null;

        if ($chamberId !== null && $this->chambers()->contains('id', $chamberId)) {
            $this->chamberId = $chamberId;
        }

        $this->notes = is_string($draft['notes'] ?? null) ? mb_substr($draft['notes'], 0, 500) : '';

        if (is_string($draft['clientToken'] ?? null) && Str::isUuid($draft['clientToken'])) {
            $this->clientToken = $draft['clientToken'];
        }

        $products = $this->products()->keyBy('id');
        $this->lines = [];

        foreach (is_array($draft['lines'] ?? null) ? $draft['lines'] : [] as $line) {
            $product = is_array($line) && is_numeric($line['product_id'] ?? null) ? $products->get((int) $line['product_id']) : null;
            $boxes = is_array($line) && is_numeric($line['boxes'] ?? null) ? (int) $line['boxes'] : 0;

            if ($product !== null && $boxes >= 1) {
                $this->addLine($product, min($boxes, RequestExit::MAX_BOXES_PER_PRODUCT));
            }
        }

        $productId = is_numeric($draft['productId'] ?? null) ? (int) $draft['productId'] : 0;
        $this->productId = $products->has($productId) ? $productId : null;
        $this->quantity = is_numeric($draft['quantity'] ?? null) && ctype_digit((string) $draft['quantity']) && (int) $draft['quantity'] >= 1 && (int) $draft['quantity'] <= RequestExit::MAX_BOXES_PER_PRODUCT
            ? (string) (int) $draft['quantity']
            : '';
    }

    public function render(): View
    {
        $productIds = array_column($this->lines, 'product_id');

        if ($this->productId !== null) {
            $productIds[] = $this->productId;
        }

        $available = $this->chamberId !== null && $productIds !== []
            ? Box::query()->availableIn($this->chamberId)->whereIn('product_id', $productIds)
                ->selectRaw('product_id, count(*) as boxes')->groupBy('product_id')->pluck('boxes', 'product_id')
            : collect();

        return view('livewire.inventory.create-exit-request', [
            'chambers' => $this->chambers(),
            'products' => $this->products(),
            'available' => $available,
            'totalBoxes' => array_sum(array_column($this->lines, 'boxes')),
        ]);
    }
}
