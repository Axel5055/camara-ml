<?php

namespace App\Models;

use App\Enums\TemperatureLevel;
use App\Enums\TemperatureShift;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Append-only: the database rejects updates and deletes. Mistakes are fixed by adding a correction.
 *
 * @property int $id
 * @property int $chamber_id
 * @property int $user_id
 * @property CarbonInterface $reading_date
 * @property TemperatureShift $shift
 * @property string|null $temperature
 * @property string|null $unmeasured_reason
 * @property CarbonInterface $recorded_at
 * @property-read Chamber $chamber
 * @property-read User $user
 */
#[Unguarded]
class TemperatureReading extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reading_date' => 'date',
            'shift' => TemperatureShift::class,
            'temperature' => 'decimal:1',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * The value that counts: the latest correction, or what was captured. Null when there is no measurement.
     */
    public function effectiveTemperature(): ?string
    {
        return $this->corrections->last()->temperature ?? $this->temperature;
    }

    public function level(): ?TemperatureLevel
    {
        $value = $this->effectiveTemperature();

        return $value === null ? null : TemperatureLevel::of($value);
    }

    /**
     * @return BelongsTo<Chamber, $this>
     */
    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TemperatureCorrection, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(TemperatureCorrection::class)->orderBy('id');
    }
}
