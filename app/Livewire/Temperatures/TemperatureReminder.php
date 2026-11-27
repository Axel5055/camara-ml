<?php

namespace App\Livewire\Temperatures;

use App\Services\Temperature\PendingTemperatures;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * From 2 pm reminds a warehouse user of the afternoon temperature. It never blocks: it can be closed for the rest
 * of the day (of this session).
 */
class TemperatureReminder extends Component
{
    private const DISMISSED_KEY = 'temperature_reminder_dismissed';

    /** The temperature screen has its own button to record it, so the reminder stays out of the way there. */
    #[Locked]
    public bool $onTemperaturePage = false;

    public function mount(): void
    {
        $this->onTemperaturePage = request()->routeIs('temperatures.index');
    }

    public function dismiss(): void
    {
        session()->put(self::DISMISSED_KEY, now()->toDateString());
    }

    #[On('temperature-recorded')]
    public function refreshReminder(): void {}

    public function render(PendingTemperatures $pending): View
    {
        $due = ! $this->onTemperaturePage
            && now()->format('H:i') >= config('temperature.afternoon_starts')
            && session(self::DISMISSED_KEY) !== now()->toDateString();

        return view('livewire.temperatures.temperature-reminder', [
            'chambers' => $due ? $pending->afternoonFor(Auth::user()) : new Collection,
        ]);
    }
}
