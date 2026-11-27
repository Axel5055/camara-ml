<?php

namespace App\Enums;

/**
 * How a temperature compares with the norm; the limits are the same for every chamber.
 */
enum TemperatureLevel: string
{
    case TooCold = 'too_cold';
    case Normal = 'normal';
    case Warning = 'warning';
    case Critical = 'critical';

    public static function of(string $temperature): self
    {
        $value = (float) $temperature;

        return match (true) {
            $value < config('temperature.levels.normal_from') => self::TooCold,
            $value <= config('temperature.levels.normal_to') => self::Normal,
            $value <= config('temperature.levels.warning_to') => self::Warning,
            default => self::Critical,
        };
    }

    /**
     * The levels that call for attention, the most serious first.
     *
     * @return list<self>
     */
    public static function alerts(): array
    {
        return [self::Critical, self::Warning, self::TooCold];
    }

    public function isAlert(): bool
    {
        return $this !== self::Normal;
    }

    public function label(): string
    {
        return match ($this) {
            self::TooCold => 'Muy frío',
            self::Normal => 'Normal',
            self::Warning => 'Advertencia',
            self::Critical => 'Crítico',
        };
    }

    /** Flux badge color. */
    public function color(): string
    {
        return match ($this) {
            self::TooCold => 'sky',
            self::Normal => 'green',
            self::Warning => 'yellow',
            self::Critical => 'red',
        };
    }

    public function textClass(): string
    {
        return match ($this) {
            self::TooCold => 'text-sky-600 dark:text-sky-300',
            self::Normal => 'text-zinc-900 dark:text-white',
            self::Warning => 'text-yellow-600 dark:text-yellow-300',
            self::Critical => 'text-red-600 dark:text-red-400',
        };
    }

    /** Chart marker: each alert has its own shape as well as its color. */
    public function markerClass(): string
    {
        return match ($this) {
            self::TooCold => 'bg-cyan-300',
            self::Normal => 'rounded-full bg-emerald-500',
            self::Warning => '[clip-path:polygon(50%_0,100%_100%,0_100%)] bg-yellow-300',
            self::Critical => 'rotate-45 bg-red-500',
        };
    }
}
