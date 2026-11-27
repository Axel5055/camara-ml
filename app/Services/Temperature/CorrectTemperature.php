<?php

namespace App\Services\Temperature;

use App\Exceptions\InventoryException;
use App\Models\AuditLog;
use App\Models\TemperatureCorrection;
use App\Models\TemperatureReading;
use App\Models\User;
use App\Support\Temperature;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * A super administrator fixes a reading by adding a correction; the captured value is kept as it was.
 */
class CorrectTemperature
{
    public function handle(User $user, TemperatureReading $reading, string $temperature, string $reason): TemperatureCorrection
    {
        if (! $user->isSuperAdmin()) {
            throw new AuthorizationException('Solo el super administrador puede corregir temperaturas.');
        }

        $value = Temperature::parse($temperature) ?? throw new InventoryException('La temperatura no es válida.');
        $reason = trim($reason);

        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new InventoryException('Explica el motivo de la corrección (de 5 a 500 caracteres).');
        }

        return DB::transaction(function () use ($user, $reading, $value, $reason): TemperatureCorrection {
            $previous = $reading->load('corrections')->effectiveTemperature();

            $correction = TemperatureCorrection::create([
                'temperature_reading_id' => $reading->id,
                'user_id' => $user->id,
                'temperature' => $value,
                'reason' => $reason,
            ]);

            AuditLog::record($user, 'temperature.corrected', $reading, ['temperature' => $previous], ['temperature' => $value, 'reason' => $reason]);

            return $correction;
        });
    }
}
