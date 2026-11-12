<?php

namespace App\Livewire\Inventory;

use App\Enums\UserRole;
use App\Exceptions\InventoryException;
use App\Livewire\Concerns\ChoosesTicketCopies;
use App\Livewire\Concerns\InteractsWithChambers;
use App\Livewire\Concerns\ReportsInventoryErrors;
use App\Models\Chamber;
use App\Models\Product;
use App\Services\Barcodes\BarcodeGenerator;
use App\Services\Inventory\RegisterEntry;
use App\Services\Inventory\RequestEntry;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Nueva entrada')]
class CreateEntry extends Component
{
    use ChoosesTicketCopies, InteractsWithChambers, ReportsInventoryErrors;

    public ?int $chamberId = null;

    public string $occurredAt = '';

    public string $notes = '';

    public string $clientToken = '';

    public ?int $productId = null;

    public string $boxesCount = '1';

    public string $weight = '';

    public string $cost = '';

    public string $manual = '';

    public string $origin = '';

    /**
     * Groups of boxes already captured for this entry.
     *
     * @var array<int, array{product_id: int, product: string, code: string, boxes_count: int, weight: string, cost: string, manual: array<int, string>, origin: string|null}>
     */
    public array $lines = [];

    public function mount(): void
    {
        $this->occurredAt = now()->format('Y-m-d\TH:i');
        $this->clientToken = (string) Str::uuid();
        $this->chamberId = $this->onlyChamberId();
    }

    public function addLine(BarcodeGenerator $barcodes): void
    {
        $this->resetErrorBag();

        $this->validate([
            'productId' => ['required', 'exists:products,id'],
            'boxesCount' => ['required', 'integer', 'min:1', 'max:100000'],
            'weight' => ['required', 'numeric', 'gt:0'],
            'cost' => ['required', 'numeric', 'min:0'],
            'origin' => ['nullable', 'string', 'max:255'],
        ], [], [
            'productId' => 'el producto',
            'boxesCount' => 'la cantidad de cajas',
            'weight' => 'el peso por caja',
            'cost' => 'el costo por kg',
        ]);

        try {
            $weight = Decimal::normalize($this->weight, Decimal::WEIGHT_SCALE);
            $cost = Decimal::normalize($this->cost, Decimal::MONEY_SCALE);
            $manual = array_map(
                fn (string $code): string => $barcodes->normalizeManual($code),
                array_values(array_filter(array_map('trim', preg_split('/\R/', $this->manual) ?: []))),
            );
        } catch (InventoryException $exception) {
            $this->addError('manual', $exception->getMessage());

            return;
        }

        if (count($manual) > (int) $this->boxesCount) {
            $this->addError('manual', 'Hay más códigos manuales que cajas.');

            return;
        }

        if ($manual !== [] && trim($this->origin) === '') {
            $this->addError('origin', 'Indica la procedencia de las etiquetas manuales.');

            return;
        }

        $already = array_merge([], ...array_column($this->lines, 'manual'));

        if (array_intersect($manual, $already) !== []) {
            $this->addError('manual', 'Algún código manual ya está en esta entrada.');

            return;
        }

        $product = Product::query()->findOrFail($this->productId);

        $this->lines[] = [
            'product_id' => $product->id,
            'product' => $product->name,
            'code' => $product->code,
            'boxes_count' => (int) $this->boxesCount,
            'weight' => $weight,
            'cost' => $cost,
            'manual' => $manual,
            'origin' => $manual === [] ? null : trim($this->origin),
        ];

        $this->reset('productId', 'weight', 'cost', 'manual', 'origin');
        $this->boxesCount = '1';
    }

    /**
     * Rebuilds the capture from the draft the browser kept (refresh, lost connection). Nothing from the browser is
     * trusted: products, chamber and every number are checked again, and whatever no longer holds is left out.
     *
     * @param  array<string, mixed>  $draft
     */
    public function restoreDraft(array $draft, BarcodeGenerator $barcodes): void
    {
        $this->resetErrorBag();

        $chamberId = is_numeric($draft['chamberId'] ?? null) ? (int) $draft['chamberId'] : 0;

        if ($chamberId > 0 && $this->accessibleChambers()->contains('id', $chamberId)) {
            $this->chamberId = $chamberId;
        }

        $this->notes = is_string($draft['notes'] ?? null) ? mb_substr($draft['notes'], 0, 2000) : '';

        if (is_string($draft['clientToken'] ?? null) && Str::isUuid($draft['clientToken'])) {
            $this->clientToken = $draft['clientToken'];
        }

        $rawLines = array_values(array_filter(is_array($draft['lines'] ?? null) ? $draft['lines'] : [], 'is_array'));
        $products = Product::query()->where('is_active', true)
            ->whereIn('id', array_filter(array_map(fn (array $line): int => is_numeric($line['product_id'] ?? null) ? (int) $line['product_id'] : 0, $rawLines)))
            ->get()->keyBy('id');

        $this->lines = [];
        $dropped = 0;

        foreach ($rawLines as $raw) {
            $line = $this->restoredLine($raw, $products->get((int) ($raw['product_id'] ?? 0)), $barcodes);

            if ($line === null) {
                $dropped++;

                continue;
            }

            $this->lines[] = $line;
        }

        $this->restoreInProgressFields($draft);

        if ($dropped > 0) {
            Flux::toast(text: "Se recuperó la captura, pero {$dropped} grupo(s) ya no son válidos y se omitieron.", variant: 'warning');
        } else {
            Flux::toast(text: 'Se recuperó tu captura anterior.', variant: 'success');
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{product_id: int, product: string, code: string, boxes_count: int, weight: string, cost: string, manual: array<int, string>, origin: string|null}|null
     */
    private function restoredLine(array $raw, ?Product $product, BarcodeGenerator $barcodes): ?array
    {
        $boxesCount = $raw['boxes_count'] ?? null;

        if ($product === null || ! is_numeric($boxesCount) || (int) $boxesCount < 1 || (int) $boxesCount > 100000
            || ! is_numeric($raw['weight'] ?? null) || (float) $raw['weight'] <= 0
            || ! is_numeric($raw['cost'] ?? null) || (float) $raw['cost'] < 0) {
            return null;
        }

        try {
            $manual = array_map(
                fn (mixed $code): string => $barcodes->normalizeManual((string) $code),
                array_values(array_filter(is_array($raw['manual'] ?? null) ? $raw['manual'] : [], 'is_string')),
            );
            $weight = Decimal::normalize((string) $raw['weight'], Decimal::WEIGHT_SCALE);
            $cost = Decimal::normalize((string) $raw['cost'], Decimal::MONEY_SCALE);
        } catch (InventoryException) {
            return null;
        }

        $origin = is_string($raw['origin'] ?? null) ? trim(mb_substr($raw['origin'], 0, 255)) : '';

        if (count($manual) > (int) $boxesCount
            || ($manual !== [] && $origin === '')
            || array_intersect($manual, array_merge([], ...array_column($this->lines, 'manual'))) !== []) {
            return null;
        }

        return [
            'product_id' => $product->id,
            'product' => $product->name,
            'code' => (string) $product->code,
            'boxes_count' => (int) $boxesCount,
            'weight' => $weight,
            'cost' => $cost,
            'manual' => $manual,
            'origin' => $manual === [] ? null : $origin,
        ];
    }

    /**
     * The group that was being typed (not yet added) comes back too.
     *
     * @param  array<string, mixed>  $draft
     */
    private function restoreInProgressFields(array $draft): void
    {
        $productId = is_numeric($draft['productId'] ?? null) ? (int) $draft['productId'] : 0;

        if ($productId > 0 && Product::query()->where('is_active', true)->whereKey($productId)->exists()) {
            $this->productId = $productId;
        }

        $boxes = $draft['boxesCount'] ?? null;

        if (is_numeric($boxes) && ctype_digit((string) $boxes) && (int) $boxes >= 1 && (int) $boxes <= 100000) {
            $this->boxesCount = (string) (int) $boxes;
        }

        foreach (['weight', 'cost'] as $field) {
            if (is_numeric($draft[$field] ?? null) && strlen((string) $draft[$field]) <= 20) {
                $this->{$field} = (string) $draft[$field];
            }
        }
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function clearAll(): void
    {
        $this->reset('lines', 'productId', 'weight', 'cost', 'manual', 'origin');
        $this->boxesCount = '1';
        $this->resetErrorBag();
    }

    /**
     * Validates the captured data; shared by the confirmation step and the final save.
     */
    private function formIsValid(): bool
    {
        $this->validate([
            'chamberId' => ['required', 'exists:chambers,id'],
            'occurredAt' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['chamberId' => 'la cámara', 'occurredAt' => 'la fecha y hora']);

        if ($this->lines === []) {
            $this->addError('operation', 'Agrega al menos un grupo de cajas a la entrada.');

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

    public function save(RegisterEntry $registerEntry, RequestEntry $requestEntry): void
    {
        if (! $this->formIsValid()) {
            Flux::modal('confirm-save')->close();

            return;
        }

        $payload = array_map(fn (array $line): array => [
            'product_id' => $line['product_id'],
            'boxes_count' => $line['boxes_count'],
            'weight_per_box_kg' => $line['weight'],
            'price_per_kg_mxn' => $line['cost'],
            'manual_barcodes' => $line['manual'],
            'manual_barcode_origin' => $line['origin'],
        ], $this->lines);

        if ($this->needsApproval()) {
            $this->sendRequest($requestEntry, $payload);

            return;
        }

        $entry = null;

        $done = $this->attempt(function () use ($registerEntry, $payload, &$entry): void {
            $entry = $registerEntry->handle(
                Auth::user(),
                Chamber::query()->findOrFail($this->chamberId),
                $payload,
                CarbonImmutable::parse($this->occurredAt),
                $this->notes ?: null,
                $this->clientToken,
            );
        });

        Flux::modal('confirm-save')->close();

        if ($done && $entry !== null) {
            if ($this->shouldPrintTicket()) {
                session()->flash('print_ticket', route('entries.ticket', ['entry' => $entry, 'auto' => 1, 'copies' => $this->ticketCopies()]));
            }

            $this->dispatch('draft-saved');
            Flux::toast(text: 'Entrada registrada.', variant: 'success');
            $this->redirectRoute('entries.index', navigate: true);
        }
    }

    /**
     * Warehouse users do not register entries directly: an administrator has to authorize them first.
     */
    private function needsApproval(): bool
    {
        return Auth::user()->role === UserRole::Warehouse;
    }

    /**
     * @param  array<int, array<string, mixed>>  $payload
     */
    private function sendRequest(RequestEntry $requestEntry, array $payload): void
    {
        $sent = $this->attempt(function () use ($requestEntry, $payload): void {
            $requestEntry->handle(
                Auth::user(),
                Chamber::query()->findOrFail($this->chamberId),
                $payload,
                CarbonImmutable::parse($this->occurredAt),
                $this->notes ?: null,
                $this->clientToken,
            );
        });

        Flux::modal('confirm-save')->close();

        if ($sent) {
            $this->dispatch('draft-saved');
            Flux::toast(text: 'Solicitud enviada. Un administrador debe autorizarla para registrar la entrada.', variant: 'success');
            $this->redirectRoute('entry-requests.index', navigate: true);
        }
    }

    /**
     * Live preview of the group being captured, shown before it is added to the entry.
     *
     * @return array{boxes: int, weight: string, cost: string}|null
     */
    private function draft(): ?array
    {
        if (! ctype_digit($this->boxesCount) || (int) $this->boxesCount < 1 || ! is_numeric($this->weight) || ! is_numeric($this->cost)) {
            return null;
        }

        try {
            return [
                'boxes' => (int) $this->boxesCount,
                'weight' => Decimal::mul(Decimal::normalize($this->weight, Decimal::WEIGHT_SCALE), $this->boxesCount, Decimal::WEIGHT_SCALE),
                'cost' => Decimal::mul(Decimal::costOf(Decimal::normalize($this->weight, Decimal::WEIGHT_SCALE), Decimal::normalize($this->cost, Decimal::MONEY_SCALE)), $this->boxesCount, Decimal::MONEY_SCALE),
            ];
        } catch (InventoryException) {
            return null;
        }
    }

    public function render(): View
    {
        $barcodes = app(BarcodeGenerator::class);
        $today = now();
        $nextNumber = (int) DB::table('sequences')->where('name', 'barcode')->value('last_value') + 1;

        $summaries = [];
        $totalBoxes = 0;
        $totalWeight = '0.000';
        $totalCost = '0.00';

        foreach ($this->lines as $line) {
            $weight = Decimal::mul($line['weight'], (string) $line['boxes_count'], Decimal::WEIGHT_SCALE);
            $cost = Decimal::mul(Decimal::costOf($line['weight'], $line['cost']), (string) $line['boxes_count'], Decimal::MONEY_SCALE);

            // Same order the service uses: groups in sequence, manual labels keep their own code.
            $generated = $line['boxes_count'] - count($line['manual']);
            $preview = $generated > 0
                ? ['first' => $barcodes->format($line['product'], $nextNumber, $today), 'last' => $barcodes->format($line['product'], $nextNumber + $generated - 1, $today), 'count' => $generated]
                : null;
            $nextNumber += $generated;

            $summaries[] = [...$line, 'total_weight' => $weight, 'total_cost' => $cost, 'barcodes' => $preview];
            $totalBoxes += $line['boxes_count'];
            $totalWeight = Decimal::add($totalWeight, $weight, Decimal::WEIGHT_SCALE);
            $totalCost = Decimal::add($totalCost, $cost, Decimal::MONEY_SCALE);
        }

        return view('livewire.inventory.create-entry', [
            'draft' => $this->draft(),
            'chambers' => $this->accessibleChambers(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'summaries' => $summaries,
            'totals' => ['boxes' => $totalBoxes, 'weight' => $totalWeight, 'cost' => $totalCost],
            'showCosts' => Gate::allows('view-costs'),
            'needsApproval' => $this->needsApproval(),
        ]);
    }
}
