<?php

namespace App\Services\Temperature;

use App\Enums\TemperatureShift;
use App\Exceptions\InventoryException;
use App\Models\AuditLog;
use App\Models\Chamber;
use App\Models\TemperatureReading;
use App\Models\User;
use App\Support\Temperature;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Records the readings of a warehouse user. The date, time, shift and chambers are decided here, never by the
 * browser, and only the current day can be recorded.
 */
class RecordTemperatures
{
    public function __construct(private PendingTemperatures $pending) {}

    /**
     * The first reading of the day, required for every pending chamber.
     *
     * @param  TemperatureShift  $shift  The shift the form was opened in; it must still be the current one.
     * @param  array<int, array{temperature: string|null, reason: string|null}>  $entries  By chamber id; a reason
     *                                                                                     means it could not be measured.
     */
    public function handle(User $user, TemperatureShift $shift, array $entries): void
    {
        $now = now();

        if (TemperatureShift::at($now) !== $shift) {
            throw new InventoryException('Cambió el turno mientras capturabas. Vuelve a tomar la temperatura.');
        }

        DB::transaction(function () use ($user, $shift, $entries, $now): void {
            foreach ($this->pending->for($user) as $chamber) {
                $entry = $entries[$chamber->id] ?? throw new InventoryException("Falta la temperatura de {$chamber->name}.");

                $this->store($user, $chamber, $shift, $entry['temperature'], $entry['reason'], $now);
            }
        });
    }

    /**
     * The optional afternoon reading of one chamber.
     *
     * @param  string  $date  The day the form was opened; it must still be today.
     */
    public function afternoon(User $user, int $chamberId, string $date, ?string $temperature, ?string $reason): void
    {
        $now = now();

        if ($date !== $now->toDateString()) {
            throw new InventoryException('El día cambió mientras capturabas. Solo se puede registrar la temperatura del día en curso.');
        }

        $chamber = $this->pending->afternoonFor($user)->firstWhere('id', $chamberId)
            ?? throw new InventoryException('Esa cámara no tiene pendiente la temperatura de la tarde.');

        DB::transaction(fn () => $this->store($user, $chamber, TemperatureShift::Afternoon, $temperature, $reason, $now));
    }

    private function store(User $user, Chamber $chamber, TemperatureShift $shift, ?string $typed, ?string $reason, CarbonInterface $now): void
    {
        $reason = $reason === null ? null : trim($reason);
        $temperature = $reason === null ? Temperature::parse((string) $typed) : null;

        if ($reason !== null && (mb_strlen($reason) < 5 || mb_strlen($reason) > 500)) {
            throw new InventoryException("Explica por qué no se pudo medir en {$chamber->name} (de 5 a 500 caracteres).");
        }

        if ($reason === null && $temperature === null) {
            throw new InventoryException("La temperatura de {$chamber->name} no es válida.");
        }

        try {
            $reading = TemperatureReading::create([
                'chamber_id' => $chamber->id,
                'user_id' => $user->id,
                'reading_date' => $now->toDateString(),
                'shift' => $shift,
                'temperature' => $temperature,
                'unmeasured_reason' => $reason,
                'recorded_at' => $now,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A coworker recorded this chamber a moment ago: theirs stays.
            return;
        }

        AuditLog::record($user, 'temperature.recorded', $reading, null, [
            'chamber_id' => $chamber->id,
            'shift' => $shift->value,
            'temperature' => $temperature,
            'unmeasured_reason' => $reason,
        ]);
    }
}
