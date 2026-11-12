<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $entry_id
 * @property int $product_id
 * @property int $boxes_count
 * @property string $weight_per_box_kg
 * @property string $price_per_kg_mxn
 * @property string $unit_cost_mxn
 * @property string $total_weight_kg
 * @property string $total_cost_mxn
 */
#[Unguarded]
class EntryItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_per_box_kg' => 'decimal:3',
            'price_per_kg_mxn' => 'decimal:2',
            'unit_cost_mxn' => 'decimal:2',
            'total_weight_kg' => 'decimal:3',
            'total_cost_mxn' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<Box, $this>
     */
    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class);
    }
}
