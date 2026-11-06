<?php

namespace App\Services\Exports;

use App\Models\Transfer;
use App\Models\TransferItem;
use Illuminate\Support\Facades\DB;

class TransferReport
{
    /**
     * Everything a printed or spreadsheet copy of a transfer shows. The price per box is listed, never a money total.
     *
     * @return array<string, mixed>
     */
    public function build(Transfer $transfer): array
    {
        $exit = $transfer->exit()->first();
        $origin = $transfer->originChamber()->with('branch:id,name')->first();
        $dest = $transfer->destChamber()->with('branch:id,name')->first();

        $perProduct = DB::table('transfer_items')
            ->join('boxes', 'boxes.id', '=', 'transfer_items.box_id')
            ->join('products', 'products.id', '=', 'boxes.product_id')
            ->where('transfer_items.transfer_id', $transfer->id)
            ->whereNull('transfer_items.removed_at')
            ->groupBy('products.id', 'products.name')
            ->orderBy('products.name')
            ->selectRaw('products.name as product, count(*) as boxes, sum(transfer_items.expected_weight_kg) as kg, min(boxes.unit_cost_mxn) as min_unit_cost, max(boxes.unit_cost_mxn) as max_unit_cost')
            ->get();

        $meta = [
            ['Referencia', $transfer->reference],
            ['Salida', $exit->reference],
            ['Enviada', $transfer->dispatched_at->format('d/m/Y H:i')],
            ['Solicitante', $exit->requester_name],
            ['Cámara de origen', $origin->name.' · '.$origin->branch->name],
            ['Cámara de destino', $dest->name.' · '.$dest->branch->name],
            ['Registró', $exit->user()->value('name')],
            ['Estado', $transfer->status->label()],
        ];

        if ($transfer->received_at) {
            $meta[] = ['Recibida', $transfer->received_at->format('d/m/Y H:i').' por '.$transfer->receiver()->value('name')];
        }

        if ($exit->notes) {
            $meta[] = ['Observaciones del envío', $exit->notes];
        }

        if ($transfer->receive_notes) {
            $meta[] = ['Observaciones de la recepción', $transfer->receive_notes];
        }

        if ($transfer->cancelled_at) {
            $meta[] = ['Cancelación', $transfer->cancelled_at->format('d/m/Y H:i').' por '.$transfer->canceller()->value('name').'. Motivo: '.$transfer->cancel_reason];
        }

        $items = TransferItem::query()->where('transfer_id', $transfer->id)->with('box:id,barcode,product_id,unit_cost_mxn', 'box.product:id,name');

        return [
            'title' => 'Transferencia '.$transfer->reference,
            'filename' => 'transferencia-'.$transfer->reference,
            'meta' => $meta,
            'totals' => [['Cajas', (string) number_format((int) $perProduct->sum('boxes'))], ['Peso enviado', number_format((float) $perProduct->sum('kg'), 3).' kg']],
            'sections' => [
                [
                    'title' => 'Resumen por producto',
                    'sheet' => 'Resumen',
                    'columns' => [
                        ['label' => 'Producto', 'type' => 'text'],
                        ['label' => 'Cajas', 'type' => 'int'],
                        ['label' => 'Peso (kg)', 'type' => 'weight'],
                        ['label' => 'Precio por caja (MXN)', 'type' => 'text'],
                    ],
                    'count' => $perProduct->count(),
                    'rows' => $perProduct->map(fn ($row): array => [
                        $row->product,
                        $row->boxes,
                        $row->kg,
                        '$'.number_format((float) $row->min_unit_cost, 2).((float) $row->min_unit_cost !== (float) $row->max_unit_cost ? ' – $'.number_format((float) $row->max_unit_cost, 2) : ''),
                    ])->lazy(),
                ],
                [
                    'title' => 'Cajas de la transferencia',
                    'sheet' => 'Cajas',
                    'columns' => [
                        ['label' => 'Código de barras', 'type' => 'text'],
                        ['label' => 'Producto', 'type' => 'text'],
                        ['label' => 'Peso enviado (kg)', 'type' => 'weight'],
                        ['label' => 'Peso recibido (kg)', 'type' => 'weight'],
                        ['label' => 'Precio por caja (MXN)', 'type' => 'money'],
                        ['label' => 'Estado', 'type' => 'text'],
                    ],
                    'count' => (clone $items)->count(),
                    'rows' => $items->lazyById(500)->map(fn (TransferItem $item): array => [
                        $item->box->barcode,
                        $item->box->product->name,
                        $item->expected_weight_kg,
                        $item->received_weight_kg,
                        $item->box->unit_cost_mxn,
                        match ($item->status->value) {
                            'received' => 'Recibida',
                            'missing' => 'Faltante',
                            default => 'Esperada',
                        },
                    ]),
                ],
            ],
        ];
    }
}
