<?php

namespace App\Livewire\Temperatures;

use App\Enums\TemperatureLevel;
use App\Enums\TemperatureShift;
use App\Enums\UserRole;
use App\Livewire\Concerns\ReportsInventoryErrors;
use App\Models\Chamber;
use App\Models\TemperatureReading;
use App\Services\Temperature\CorrectTemperature;
use App\Services\Temperature\PendingTemperatures;
use App\Services\Temperature\RecordTemperatures;
use App\Support\Temperature;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Temperature log and chart of one chamber. Super administrators and managers (their chambers) see every reading
 * and the missing ones; warehouse users only what they recorded.
 */
#[Title('Temperaturas')]
class TemperatureLog extends Component
{
    use ReportsInventoryErrors;

    /** Longest period shown at once. */
    private const MAX_DAYS = 92;

    public ?int $chamberId = null;

    public string $dateFrom = '';

    public string $dateTo = '';

    /** The chamber whose afternoon reading is being captured, and the day the form was opened. */
    #[Locked]
    public ?int $capturingId = null;

    #[Locked]
    public string $capturingDate = '';

    public string $captureValue = '';

    public bool $captureUnmeasured = false;

    public string $captureReason = '';

    public bool $captureConfirming = false;

    #[Locked]
    public ?int $correctingId = null;

    public string $correctionValue = '';

    public string $correctionReason = '';

    public function mount(): void
    {
        Gate::authorize('view-temperatures');

        $this->chamberId = $this->chambers()->first()?->id;
        $this->dateFrom = now()->subDays(13)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    /**
     * @return Collection<int, Chamber>
     */
    private function chambers(): Collection
    {
        return Chamber::query()
            ->accessibleBy(Auth::user())
            ->with('branch:id,name')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    private function ownOnly(): bool
    {
        return Auth::user()->role === UserRole::Warehouse;
    }

    /**
     * The readings of a chamber the user may see.
     *
     * @return Builder<TemperatureReading>
     */
    private function visibleReadings(): Builder
    {
        return TemperatureReading::query()
            ->whereIn('chamber_id', Chamber::query()->accessibleBy(Auth::user())->select('id'))
            ->when($this->ownOnly(), fn (Builder $query) => $query->where('user_id', Auth::id()));
    }

    public function askToCapture(int $chamberId, PendingTemperatures $pending): void
    {
        Gate::authorize('operate-inventory');
        abort_unless($pending->afternoonFor(Auth::user())->contains('id', $chamberId), 403);

        $this->reset('captureValue', 'captureUnmeasured', 'captureReason', 'captureConfirming');
        $this->resetErrorBag();
        $this->capturingId = $chamberId;
        $this->capturingDate = now()->toDateString();
        Flux::modal('temperature-capture')->show();
    }

    public function reviewCapture(): void
    {
        $this->resetErrorBag();

        if ($this->captureUnmeasured) {
            $length = mb_strlen(trim($this->captureReason));

            if ($length < 5 || $length > 500) {
                $this->addError('captureReason', 'Explica por qué no se pudo medir (de 5 a 500 caracteres).');
            }
        } elseif (Temperature::parse($this->captureValue) === null) {
            $this->addError('captureValue', 'Escribe la temperatura en °C, entre '.config('temperature.lowest').' y '.config('temperature.highest').', con un decimal como máximo.');
        }

        $this->captureConfirming = $this->getErrorBag()->isEmpty();
    }

    public function backToCapture(): void
    {
        $this->captureConfirming = false;
    }

    public function saveCapture(RecordTemperatures $record): void
    {
        Gate::authorize('operate-inventory');

        if (! $this->captureConfirming || $this->capturingId === null) {
            return;
        }

        $saved = $this->attempt(fn () => $record->afternoon(
            Auth::user(),
            $this->capturingId,
            $this->capturingDate,
            $this->captureUnmeasured ? null : $this->captureValue,
            $this->captureUnmeasured ? $this->captureReason : null,
        ));

        $this->captureConfirming = false;

        if (! $saved) {
            return;
        }

        $this->reset('capturingId', 'capturingDate', 'captureValue', 'captureUnmeasured', 'captureReason');
        $this->dispatch('temperature-recorded');
        Flux::modal('temperature-capture')->close();
        Flux::toast(text: 'Temperatura registrada.', variant: 'success');
    }

    public function askToCorrect(int $id): void
    {
        Gate::authorize('correct-temperatures');

        $reading = $this->visibleReadings()->findOrFail($id);

        $this->correctingId = $reading->id;
        $this->reset('correctionValue', 'correctionReason');
        $this->resetErrorBag();
        Flux::modal('temperature-correction')->show();
    }

    public function correct(CorrectTemperature $correct): void
    {
        Gate::authorize('correct-temperatures');

        $reading = $this->visibleReadings()->findOrFail($this->correctingId);

        if (! $this->attempt(fn () => $correct->handle(Auth::user(), $reading, $this->correctionValue, $this->correctionReason))) {
            return;
        }

        $this->reset('correctingId', 'correctionValue', 'correctionReason');
        Flux::modal('temperature-correction')->close();
        Flux::toast(text: 'Corrección guardada.', variant: 'success');
    }

    private function parseDate(string $date, CarbonInterface $fallback): CarbonInterface
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? CarbonImmutable::parse($date)->startOfDay() : $fallback;
    }

    /**
     * The period shown: never beyond today nor longer than the maximum.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function period(): array
    {
        $today = CarbonImmutable::parse(now()->toDateString());
        $to = $this->parseDate($this->dateTo, $today)->min($today);
        $from = $this->parseDate($this->dateFrom, $to->subDays(13))->min($to)->max($to->subDays(self::MAX_DAYS - 1));

        return [$from, $to];
    }

    /**
     * One row per day, newest first. Complete views include the days nobody recorded, from the chamber's first
     * reading on (and up to its last one when it is no longer active); own views only the days with a reading.
     *
     * @return array<int, array{date: CarbonInterface, morning: array{reading: TemperatureReading|null, status: string}, afternoon: array{reading: TemperatureReading|null, status: string}}>
     */
    private function days(?Chamber $chamber): array
    {
        if ($chamber === null) {
            return [];
        }

        [$from, $to] = $this->period();

        $readings = $this->visibleReadings()
            ->where('chamber_id', $chamber->id)
            ->whereBetween('reading_date', [$from->toDateString(), $to->toDateString()])
            ->with(['user:id,name', 'corrections.user:id,name'])
            ->get()
            ->groupBy(fn (TemperatureReading $reading): string => $reading->reading_date->toDateString());

        if ($this->ownOnly()) {
            $dates = $readings->keys()->sortDesc()->values()->all();
        } else {
            $history = TemperatureReading::query()->where('chamber_id', $chamber->id);
            $first = (clone $history)->min('reading_date');
            $dates = [];

            if ($first !== null) {
                $start = $from->max(CarbonImmutable::parse($first));
                $end = $chamber->is_active ? $to : $to->min(CarbonImmutable::parse((string) $history->max('reading_date')));

                for ($day = $end; $day->gte($start); $day = $day->subDay()) {
                    $dates[] = $day->toDateString();
                }
            }
        }

        return array_map(function (string $date) use ($readings): array {
            $row = ['date' => CarbonImmutable::parse($date)];

            foreach (TemperatureShift::cases() as $shift) {
                $reading = $readings->get($date)?->first(fn (TemperatureReading $reading): bool => $reading->shift === $shift);

                $row[$shift->value] = ['reading' => $reading, 'status' => $this->slotStatus($date, $shift, $reading)];
            }

            return $row;
        }, $dates);
    }

    /**
     * recorded, pending (its time has not passed yet), missing, or none (own views say nothing about other slots).
     */
    private function slotStatus(string $date, TemperatureShift $shift, ?TemperatureReading $reading): string
    {
        if ($reading !== null) {
            return 'recorded';
        }

        if ($this->ownOnly()) {
            return 'none';
        }

        if ($date === now()->toDateString() && ($shift === TemperatureShift::Afternoon || TemperatureShift::at(now()) === $shift)) {
            return 'pending';
        }

        return 'missing';
    }

    public function render(): View
    {
        $chambers = $this->chambers();
        $chamber = $chambers->firstWhere('id', $this->chamberId);

        abort_if($this->chamberId !== null && $chamber === null, 403);

        $days = $this->days($chamber);
        $slots = collect($days)->flatMap(fn (array $day): array => [$day['morning'], $day['afternoon']]);
        $readings = $slots->pluck('reading')->filter();

        $afternoonPending = app(PendingTemperatures::class)->afternoonFor(Auth::user());

        return view('livewire.temperatures.temperature-log', [
            'afternoonPending' => $afternoonPending,
            'capturing' => $chambers->firstWhere('id', $this->capturingId),
            'chambers' => $chambers,
            'chamber' => $chamber,
            'days' => $days,
            'ownOnly' => $this->ownOnly(),
            'canCorrect' => Gate::allows('correct-temperatures'),
            'correcting' => $this->correctingId === null ? null : $this->visibleReadings()->with(['chamber', 'corrections'])->find($this->correctingId),
            'totals' => [
                'readings' => $readings->count(),
                ...collect(TemperatureLevel::alerts())->mapWithKeys(fn (TemperatureLevel $level): array => [
                    $level->value => $readings->filter(fn (TemperatureReading $reading): bool => $reading->level() === $level)->count(),
                ])->all(),
                'unmeasured' => $readings->filter(fn (TemperatureReading $reading): bool => $reading->effectiveTemperature() === null)->count(),
                'missing' => $slots->where('status', 'missing')->count(),
            ],
            'chart' => collect($days)->reverse()->values()->map(fn (array $day): array => [
                'label' => $day['date']->format('d/m'),
                'date' => $day['date']->format('d/m/Y'),
                ...collect(TemperatureShift::cases())->mapWithKeys(fn (TemperatureShift $shift): array => [$shift->value => [
                    'value' => $day[$shift->value]['reading']?->effectiveTemperature(),
                    'level' => $day[$shift->value]['reading']?->level()?->value,
                    'status' => $day[$shift->value]['status'],
                    'recorded_at' => $day[$shift->value]['reading']?->recorded_at->format('d/m/Y H:i'),
                    'corrected' => (bool) $day[$shift->value]['reading']?->corrections->isNotEmpty(),
                ]])->all(),
            ])->all(),
        ]);
    }
}
