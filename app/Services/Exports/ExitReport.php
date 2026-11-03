<?php

namespace App\Services\Exports;

use App\Models\ExitItem;
use App\Models\StockExit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExitReport
{
    /**
     * Everything a printed or spreadsheet copy of an exit shows. Costs are left out unless $showCosts.
     *
     * @return array<string, mixed>
     */
    public function build(StockExit $exit, bool $showCosts): array
    {
        $origin = $exit->originChamber()->with('branch:id,name')->first();
        $transfer = $exit->transfer()->first();
        $destChamber = $exit->destChamber()->value('name');

        $totals = [['Cajas', (string) number_format($exit->total_boxes)], ['Peso total', number_format((float) $exit->total_weight_kg, 3).' kg']];
        if ($showCosts) {
            $totals[] = ['Costo histórico', '$'.number_format((float) $exit->total_cost_mxn, 2).' MXN'];
        }

        $perProduct = DB::table('exit_items')
            ->join('boxes', 'boxes.id', '=', 'exit_items.box_id')
            ->join('products', 'products.id', '=', 'boxes.product_id')
            ->where('exit_items.exit_id', $exit->id)
            ->groupBy('products.id', 'products.name')
            ->orderBy('products.name')
            ->selectRaw('products.name as product, count(*) as boxes, sum(exit_items.weight_out_kg) as kg, sum(exit_items.cost_out_mxn) as cost')
            ->get();

        $meta = [
            ['Referencia', $exit->reference],
            ['Fecha y hora', $exit->occurred_at->format('d/m/Y H:i')],
            ['Solicitante', $exit->requester_name],
            ['Cámara de origen', $origin->name.' · '.$origin->branch->name],
            ['Destino', $exit->destBranch()->value('name').($destChamber ? ' · Cámara '.$destChamber : ' · Entrega directa')],
            ['Registró', $exit->user()->value('name')],
            ['Estado', $exit->status->label().($transfer ? ' · Transferencia '.$transfer->reference.' ('.$transfer->status->label().')' : '')],
        ];

        if ($exit->notes) {
            $meta[] = ['Observaciones', $exit->notes];
        }

        if ($exit->reversed_at) {
            $meta[] = ['Reversión', $exit->reversed_at->format('d/m/Y H:i').' por '.User::query()->whereKey($exit->reversed_by)->value('name').'. Motivo: '.$exit->reversed_reason];
        }

        $items = ExitItem::query()->where('exit_id', $exit->id)->with('box:id,barcode,product_id,status', 'box.product:id,name');

        return [
            'title' => 'Salida '.$exit->reference,
            'filename' => 'salida-'.$exit->reference,
            'meta' => $meta,
            'totals' => $totals,
            'sections' => [
                [
                    'title' => 'Resumen por producto',
                    'sheet' => 'Resumen',
                    'columns' => [
                        ['label' => 'Producto', 'type' => 'text'],
                        ['label' => 'Cajas', 'type' => 'int'],
                        ['label' => 'Peso (kg)', 'type' => 'weight'],
                        ...($showCosts ? [['label' => 'Costo histórico (MXN)', 'type' => 'money']] : []),
                    ],
                    'count' => $perProduct->count(),
                    'rows' => $perProduct->map(fn ($row): array => [
                        $row->product,
                        $row->boxes,
                        $row->kg,
                        ...($showCosts ? [$row->cost] : []),
                    ])->lazy(),
                ],
                [
                    'title' => 'Cajas de la salida',
                    'sheet' => 'Cajas',
                    'columns' => [
                        ['label' => 'Código de barras', 'type' => 'text'],
                        ['label' => 'Producto', 'type' => 'text'],
                        ['label' => 'Tipo', 'type' => 'text'],
                        ['label' => 'Peso que salió (kg)', 'type' => 'weight'],
                        ['label' => 'Peso restante en la caja (kg)', 'type' => 'weight'],
                        ...($showCosts ? [['label' => 'Costo histórico (MXN)', 'type' => 'money']] : []),
                        ['label' => 'Estado actual', 'type' => 'text'],
                    ],
                    'count' => (clone $items)->count(),
                    'rows' => $items->lazyById(500)->map(fn (ExitItem $item): array => [
                        $item->box->barcode,
                        $item->box->product->name,
                        $item->kind->value === 'full' ? 'Caja completa' : 'Retiro parcial',
                        $item->weight_out_kg,
                        $item->kind->value === 'full' ? null : $item->weight_after_kg,
                        ...($showCosts ? [$item->cost_out_mxn] : []),
                        $item->box->status->label().($item->is_active ? '' : ' (revertida)'),
                    ]),
                ],
            ],
        ];
    }
}
