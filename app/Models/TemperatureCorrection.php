<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A super administrator's fix of a reading. Append-only like the reading it corrects.
 *
 * @property int $id
 * @property int $temperature_reading_id
 * @property int $user_id
 * @property string $temperature
 * @property string $reason
 * @property CarbonInterface $created_at
 * @property-read User $user
 */
#[Unguarded]
class TemperatureCorrection extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['temperature' => 'decimal:1'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
