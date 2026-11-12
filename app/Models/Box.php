<?php

namespace App\Models;

use App\Enums\BarcodeSource;
use App\Enums\BoxStatus;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Physical box: the source of truth for inventory. Written only through App\Services\Inventory.
 *
 * @property int $id
 * @property string $barcode
 * @property BarcodeSource $barcode_source
 * @property string|null $manual_barcode_origin
 * @property int $product_id
 * @property int $category_id
 * @property int $entry_item_id
 * @property int $origin_chamber_id
 * @property string $initial_weight_kg
 * @property string $current_weight_kg
 * @property string $price_per_kg_mxn
 * @property string $unit_cost_mxn
 * @property Carbon $entered_at
 * @property Carbon|null $voided_at
 * @property BoxStatus $status
 * @property int|null $current_chamber_id
 * @property-read Product $product
 * @property-read Category $category
 */
#[Unguarded]
class Box extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'barcode_source' => BarcodeSource::class,
            'status' => BoxStatus::class,
            'initial_weight_kg' => 'decimal:3',
            'current_weight_kg' => 'decimal:3',
            'price_per_kg_mxn' => 'decimal:2',
            'unit_cost_mxn' => 'decimal:2',
            'entered_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * Boxes that are physically stored in the given chamber.
     *
     * @param  Builder<Box>  $query
     */
    public function scopeAvailableIn(Builder $query, int $chamberId): void
    {
        $query->where('status', BoxStatus::Available)->where('current_chamber_id', $chamberId);
    }

    public function isPartial(): bool
    {
        return Decimal::cmp($this->current_weight_kg, $this->initial_weight_kg, Decimal::WEIGHT_SCALE) < 0;
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<EntryItem, $this>
     */
    public function entryItem(): BelongsTo
    {
        return $this->belongsTo(EntryItem::class);
    }

    /**
     * @return BelongsTo<Chamber, $this>
     */
    public function currentChamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class, 'current_chamber_id');
    }

    /**
     * @return BelongsTo<Chamber, $this>
     */
    public function originChamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class, 'origin_chamber_id');
    }

    /**
     * @return HasMany<BoxMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(BoxMovement::class)->orderBy('occurred_at')->orderBy('id');
    }
}
