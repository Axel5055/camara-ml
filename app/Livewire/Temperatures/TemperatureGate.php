<?php

namespace App\Livewire\Temperatures;

use App\Enums\TemperatureShift;
use App\Livewire\Concerns\ReportsInventoryErrors;
use App\Models\Chamber;
use App\Services\Temperature\PendingTemperatures;
use App\Services\Temperature\RecordTemperatures;
use App\Support\Temperature;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Isolate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The modal that asks a warehouse user for the pending temperatures. It cannot be closed. It is the only component
 * that works while something is pending, so its requests travel on their own (Isolate).
 */
#[Isolate]
class TemperatureGate extends Component
{
    use ReportsInventoryErrors;

    /** Shown on the capture page (after signing in) instead of over a screen the user was already on. */
    #[Locked]
    public bool $standalone = false;

    /** The shift the form on screen belongs to. */
    #[Locked]
    public string $shift = '';

    /** @var array<int, string> */
    public array $readings = [];

    /** @var array<int, bool> */
    public array $unmeasured = [];

    /** @var array<int, string> */
    public array $reasons = [];

    public bool $confirming = false;

    /**
     * @return Collection<int, Chamber>
     */
    private function pendingChambers(): Collection
    {
        return app(PendingTemperatures::class)->for(Auth::user());
    }

    /**
     * Runs every few seconds, so a screen left open overnight is asked for the new day's reading without
     * reloading, and as soon as the server rejects an action because something is pending.
     */
    #[On('temperature-required')]
    public function check(): void
    {
        if ($this->standalone && $this->pendingChambers()->isEmpty()) {
            $this->redirectRoute('dashboard');
        }
    }

    public function review(): void
    {
        $this->resetErrorBag();

        foreach ($this->pendingChambers() as $chamber) {
            if ($this->unmeasured[$chamber->id] ?? false) {
                $length = mb_strlen(trim($this->reasons[$chamber->id] ?? ''));

                if ($length < 5 || $length > 500) {
                    $this->addError("reasons.{$chamber->id}", 'Explica por qué no se pudo medir (de 5 a 500 caracteres).');
                }
            } elseif (Temperature::parse($this->readings[$chamber->id] ?? '') === null) {
                $this->addError("readings.{$chamber->id}", 'Escribe la temperatura en °C, entre '.config('temperature.lowest').' y '.config('temperature.highest').', con un decimal como máximo.');
            }
        }

        $this->confirming = $this->getErrorBag()->isEmpty();
    }

    public function back(): void
    {
        $this->confirming = false;
    }

    public function save(RecordTemperatures $record): void
    {
        if (! $this->confirming) {
            return;
        }

        $entries = [];

        foreach ($this->pendingChambers() as $chamber) {
            $unmeasured = $this->unmeasured[$chamber->id] ?? false;

            $entries[$chamber->id] = [
                'temperature' => $unmeasured ? null : ($this->readings[$chamber->id] ?? ''),
                'reason' => $unmeasured ? ($this->reasons[$chamber->id] ?? '') : null,
            ];
        }

        $saved = $this->attempt(fn () => $record->handle(Auth::user(), TemperatureShift::from($this->shift), $entries));

        $this->confirming = false;

        if (! $saved) {
            return;
        }

        $this->reset('readings', 'unmeasured', 'reasons');

        if ($this->standalone) {
            session()->flash('temperature_saved', true);
            $this->redirectRoute('dashboard');
        }
    }

    public function render(): View
    {
        $chambers = $this->pendingChambers();
        $shift = TemperatureShift::at(now());

        // A form left open across 2 pm (or midnight) starts over: what was typed belongs to the previous shift.
        if ($this->shift !== $shift->value) {
            if ($this->shift !== '') {
                $this->reset('readings', 'unmeasured', 'reasons', 'confirming');
            }

            $this->shift = $shift->value;
        }

        foreach ($chambers as $chamber) {
            $this->readings[$chamber->id] ??= '';
            $this->unmeasured[$chamber->id] ??= false;
            $this->reasons[$chamber->id] ??= '';
        }

        return view('livewire.temperatures.temperature-gate', ['chambers' => $chambers]);
    }
}
