<?php

namespace App\Models;

use App\Enums\EntryRequestStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $category_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property-read Category $category
 */
#[Fillable(['category_id', 'code', 'name', 'description', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Box, $this>
     */
    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class);
    }

    /**
     * @return HasMany<EntryItem, $this>
     */
    public function entryItems(): HasMany
    {
        return $this->hasMany(EntryItem::class);
    }

    /**
     * @return HasMany<StockThreshold, $this>
     */
    public function stockThresholds(): HasMany
    {
        return $this->hasMany(StockThreshold::class);
    }

    /**
     * Why the product cannot be deleted, or null when it has no movements anywhere in the system: no boxes ever
     * received, no entry records and no pending entry request that includes it.
     */
    public function deletionBlocker(): ?string
    {
        if ($this->boxes()->exists() || $this->entryItems()->exists()) {
            return 'tiene movimientos en el sistema (entradas o cajas registradas). Desactívalo en lugar de eliminarlo.';
        }

        $inPendingRequest = EntryRequest::query()
            ->where('status', EntryRequestStatus::Pending)
            ->get(['id', 'groups'])
            ->contains(fn (EntryRequest $request): bool => in_array($this->id, array_map('intval', array_column($request->groups, 'product_id')), true));

        return $inPendingRequest ? 'está incluido en una solicitud de entrada pendiente.' : null;
    }
}
