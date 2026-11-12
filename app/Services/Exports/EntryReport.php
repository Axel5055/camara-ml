<?php

namespace App\Services\Exports;

use App\Models\Box;
use App\Models\Entry;

class EntryReport
{
    /**
     * Everything a printed or spreadsheet copy of an entry shows. Amounts are left out unless $showCosts.
     *
     * @return array<string, mixed>
     */
    public function build(Entry $entry, bool $showCosts): array
    {
        $chamber = $entry->chamber()->with('branch:id,name')->first();
        $items = $entry->items()->with('product:id,name,category_id', 'product.category:id,name')->orderBy('id')->get();

        $totals = [['Cajas', (string) number_format($entry->total_boxes)], ['Peso total', number_format((float) $entry->total_weight_kg, 3).' kg']];
        if ($showCosts) {
            $totals[] = ['Importe', '$'.number_format((float) $entry->total_cost_mxn, 2).' MXN'];
        }

        $groupColumns = [
            ['label' => 'Producto', 'type' => 'text'],
            ['label' => 'Categoría', 'type' => 'text'],
            ['label' => 'Cajas', 'type' => 'int'],
            ['label' => 'Peso por caja (kg)', 'type' => 'weight'],
            ['label' => 'Costo por kg (MXN)', 'type' => 'money'],
            ['label' => 'Peso total (kg)', 'type' => 'weight'],
            ...($showCosts ? [['label' => 'Importe (MXN)', 'type' => 'money']] : []),
        ];

        $groupRows = $items->map(fn ($item): array => [
            $item->product->name,
            $item->product->category->name,
            $item->boxes_count,
            $item->weight_per_box_kg,
            $item->price_per_kg_mxn,
            $item->total_weight_kg,
            ...($showCosts ? [$item->total_cost_mxn] : []),
        ])->all();

        $meta = [
            ['Referencia', $entry->reference],
            ['Fecha y hora', $entry->occurred_at->format('d/m/Y H:i')],
            ['Cámara de destino', $chamber->name.' · '.$chamber->branch->name],
            ['Registró', $entry->user()->value('name')],
            ['Estado', $entry->status->label()],
        ];

        if ($entry->notes) {
            $meta[] = ['Observaciones', $entry->notes];
        }

        $boxes = Box::query()->whereIn('entry_item_id', $items->modelKeys())->with('product:id,name', 'currentChamber:id,name');

        return [
            'title' => 'Entrada '.$entry->reference,
            'filename' => 'entrada-'.$entry->reference,
            'meta' => $meta,
            'totals' => $totals,
            'sections' => [
                [
                    'title' => 'Grupos de la entrada',
                    'sheet' => 'Resumen',
                    'columns' => $groupColumns,
                    'count' => count($groupRows),
                    'rows' => collect($groupRows)->lazy(),
                ],
                [
                    'title' => 'Cajas que ingresaron',
                    'sheet' => 'Cajas',
                    'columns' => [
                        ['label' => 'Código de barras', 'type' => 'text'],
                        ['label' => 'Producto', 'type' => 'text'],
                        ['label' => 'Peso inicial (kg)', 'type' => 'weight'],
                        ['label' => 'Peso actual (kg)', 'type' => 'weight'],
                        ['label' => 'Precio por caja (MXN)', 'type' => 'money'],
                        ['label' => 'Ubicación actual', 'type' => 'text'],
                        ['label' => 'Estado', 'type' => 'text'],
                    ],
                    'count' => (clone $boxes)->count(),
                    'rows' => $boxes->lazyById(500)->map(fn (Box $box): array => [
                        $box->barcode,
                        $box->product->name,
                        $box->initial_weight_kg,
                        $box->current_weight_kg,
                        $box->unit_cost_mxn,
                        $box->current_chamber_id === null ? '—' : $box->currentChamber->name,
                        $box->status->label(),
                    ]),
                ],
            ],
        ];
    }
}
