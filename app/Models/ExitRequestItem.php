<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $exit_request_id
 * @property int $product_id
 * @property int $boxes_requested
 * @property int|null $boxes_approved
 * @property-read Product $product
 */
#[Unguarded]
class ExitRequestItem extends Model
{
    /**
     * @return BelongsTo<ExitRequest, $this>
     */
    public function exitRequest(): BelongsTo
    {
        return $this->belongsTo(ExitRequest::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
