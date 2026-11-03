<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $chamber_id
 * @property int $product_id
 * @property int|null $min_boxes
 * @property string|null $min_weight_kg
 */
#[Fillable(['chamber_id', 'product_id', 'min_boxes', 'min_weight_kg'])]
class StockThreshold extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['min_weight_kg' => 'decimal:3'];
    }

    /**
     * @return BelongsTo<Chamber, $this>
     */
    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
