<?php

use App\Enums\TemperatureLevel;
use App\Enums\TemperatureShift;
use App\Livewire\Inventory\Stock;
use App\Livewire\Temperatures\TemperatureGate;
use App\Livewire\Temperatures\TemperatureLog;
use App\Livewire\Temperatures\TemperatureReminder;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\TemperatureReading;
use App\Models\User;
use App\Services\Temperature\PendingTemperatures;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('temperature.enforce', true);
    $this->travelTo('2026-11-27 08:15:00');
});

/**
 * @return array{0: User, 1: Chamber}
 */
function warehouseWithChamber(?Branch $branch = null): array
{
    $branch ??= Branch::factory()->create();
    $chamber = Chamber::factory()->for($branch)->create();
    $user = User::factory()->warehouse($branch)->create();
    $user->chambers()->attach($chamber);

    return [$user, $chamber];
}

function reading(Chamber $chamber, User $user, string $date = '2026-11-27', TemperatureShift $shift = TemperatureShift::Morning, array $attributes = []): TemperatureReading
{
    return TemperatureReading::create([
        'chamber_id' => $chamber->id,
        'user_id' => $user->id,
        'reading_date' => $date,
        'shift' => $shift,
        'temperature' => '-18.0',
        'recorded_at' => "{$date} 08:00:00",
        ...$attributes,
    ]);
}

function captureTemperature(User $user, Chamber $chamber, string $temperature)
{
    return Livewire::actingAs($user)->test(TemperatureGate::class)
        ->set("readings.{$chamber->id}", $temperature)
        ->call('review')
        ->call('save');
}

test('a warehouse user with a pending temperature is sent to the capture screen from any page', function (string $route) {
    [$user] = warehouseWithChamber();

    $this->actingAs($user)->get(route($route))->assertRedirect(route('temperatures.capture'));
})->with(['dashboard', 'stock', 'exits.index', 'temperatures.index', 'profile.edit']);

test('the server rejects other requests and component actions while a temperature is pending', function () {
    [$user] = warehouseWithChamber();
    $stock = Livewire::actingAs($user)->test(Stock::class);

    $stock->call('$refresh')->assertStatus(423);
    $this->actingAs($user)->post(route('qz.sign'), ['request' => 'x'])->assertStatus(423);
});

test('the capture screen shows a modal for the pending chambers and signing out stays available', function () {
    [$user, $chamber] = warehouseWithChamber();

    $this->actingAs($user)->get(route('temperatures.capture'))
        ->assertOk()
        ->assertSee('data-test="temperature-gate"', false)
        ->assertSee($chamber->name);

    $this->post(route('logout'))->assertRedirect();
    $this->assertGuest();
});

test('nobody else is blocked', function (Closure $makeUser) {
    warehouseWithChamber();
    $user = $makeUser();

    expect(app(PendingTemperatures::class)->for($user))->toBeEmpty();
})->with([
    'super administrator' => fn () => User::factory()->superAdmin()->create(),
    'administrator' => fn () => User::factory()->admin()->create(),
    'manager' => fn () => User::factory()->manager()->create(),
    'requester' => fn () => User::factory()->requester()->create(),
    'warehouse user without chambers' => fn () => User::factory()->warehouse()->create(),
    'warehouse user with only an inactive chamber' => function () {
        [$user, $chamber] = warehouseWithChamber();
        $chamber->update(['is_active' => false]);

        return $user;
    },
    'warehouse user who may only ask a chamber for product' => function () {
        $user = User::factory()->warehouse()->create();
        $user->requestableChambers()->attach(Chamber::factory()->create());

        return $user;
    },
]);

test('recording the temperature stores the server date and time and unlocks the system', function () {
    [$user, $chamber] = warehouseWithChamber();

    captureTemperature($user, $chamber, '-18,5')->assertHasNoErrors()->assertDontSee('data-test="temperature-gate"', false);

    $reading = TemperatureReading::sole();
    expect($reading->temperature)->toBe('-18.5')
        ->and($reading->user_id)->toBe($user->id)
        ->and($reading->shift)->toBe(TemperatureShift::Morning)
        ->and($reading->recorded_at->format('Y-m-d H:i'))->toBe('2026-11-27 08:15')
        ->and(AuditLog::query()->where('event', 'temperature.recorded')->count())->toBe(1);
    $this->actingAs($user)->get(route('stock'))->assertOk();
});

test('the capture screen goes to the panel once nothing is pending', function () {
    [$user, $chamber] = warehouseWithChamber();

    Livewire::actingAs($user)->test(TemperatureGate::class, ['standalone' => true])
        ->set("readings.{$chamber->id}", '-18')
        ->call('review')
        ->call('save')
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)->get(route('temperatures.capture'))->assertRedirect(route('dashboard'));
});

test('nothing is saved before the value is confirmed', function () {
    [$user, $chamber] = warehouseWithChamber();

    Livewire::actingAs($user)->test(TemperatureGate::class)
        ->set("readings.{$chamber->id}", '-18')
        ->call('save')
        ->call('review')
        ->assertSee('-18.0 °C');

    expect(TemperatureReading::count())->toBe(0);
});

test('values that are not a temperature are rejected', function (string $value) {
    [$user, $chamber] = warehouseWithChamber();

    captureTemperature($user, $chamber, $value)->assertHasErrors("readings.{$chamber->id}");

    expect(TemperatureReading::count())->toBe(0);
})->with(['empty' => '', 'text' => 'frío', 'two decimals' => '-18.25', 'too low' => '-50.1', 'too high' => '51']);

test('a chamber that cannot be measured needs a reason', function () {
    [$user, $chamber] = warehouseWithChamber();

    $gate = Livewire::actingAs($user)->test(TemperatureGate::class)
        ->set("unmeasured.{$chamber->id}", true)
        ->set("reasons.{$chamber->id}", '  no ')
        ->call('review')
        ->assertHasErrors("reasons.{$chamber->id}");

    $gate->set("reasons.{$chamber->id}", 'Termómetro descompuesto')->call('review')->call('save')->assertHasNoErrors();

    expect(TemperatureReading::sole()->only('temperature', 'unmeasured_reason'))
        ->toBe(['temperature' => null, 'unmeasured_reason' => 'Termómetro descompuesto']);
});

test('every assigned chamber is required and one already recorded by a coworker is not asked again', function () {
    $branch = Branch::factory()->create();
    [$user, $first] = warehouseWithChamber($branch);
    $second = Chamber::factory()->for($branch)->create();
    $user->chambers()->attach($second);

    captureTemperature($user, $first, '-18')->assertHasErrors("readings.{$second->id}");
    expect(TemperatureReading::count())->toBe(0);

    $coworker = User::factory()->warehouse($branch)->create();
    $coworker->chambers()->attach($second);
    captureTemperature($coworker, $second, '-17')->assertHasNoErrors();

    expect(app(PendingTemperatures::class)->for($user)->modelKeys())->toBe([$first->id]);
});

test('after the first reading of the day nothing else blocks, not even after 2 pm', function () {
    [$user, $chamber] = warehouseWithChamber();
    captureTemperature($user, $chamber, '-18');

    $this->travelTo('2026-11-27 16:30:00');

    expect(app(PendingTemperatures::class)->for($user))->toBeEmpty();
    $this->actingAs($user)->get(route('stock'))->assertOk();
});

test('the first reading of the next day blocks again', function () {
    [$user, $chamber] = warehouseWithChamber();
    captureTemperature($user, $chamber, '-18');

    $this->travelTo('2026-11-28 07:00:00');

    $this->actingAs($user)->get(route('stock'))->assertRedirect(route('temperatures.capture'));
});

test('a first reading of the day taken after 2 pm counts as the afternoon one and nothing else is asked', function () {
    [$user, $chamber] = warehouseWithChamber();
    $this->travelTo('2026-11-27 15:20:00');

    captureTemperature($user, $chamber, '-18');

    expect(TemperatureReading::sole()->shift)->toBe(TemperatureShift::Afternoon)
        ->and(app(PendingTemperatures::class)->afternoonFor($user))->toBeEmpty();
});

test('from 2 pm a reminder that can be dismissed asks for the afternoon reading', function () {
    [$user, $chamber] = warehouseWithChamber();
    reading($chamber, $user);
    $this->travelTo('2026-11-27 13:59:00');
    Livewire::actingAs($user)->test(TemperatureReminder::class)->assertDontSee('data-test="temperature-reminder"', false);

    $this->travelTo('2026-11-27 14:00:00');
    Livewire::actingAs($user)->test(TemperatureReminder::class)
        ->assertSee('data-test="temperature-reminder"', false)
        ->assertSee($chamber->name)
        ->call('dismiss')
        ->assertDontSee('data-test="temperature-reminder"', false);
});

test('the afternoon reading is recorded from the temperature screen at any hour from noon', function (string $time) {
    [$user, $chamber] = warehouseWithChamber();
    reading($chamber, $user);
    $this->travelTo("2026-11-27 {$time}");

    Livewire::actingAs($user)->test(TemperatureLog::class)
        ->call('askToCapture', $chamber->id)
        ->set('captureValue', '-16')
        ->call('reviewCapture')
        ->call('saveCapture')
        ->assertHasNoErrors()
        ->assertDontSee('data-test="temperature-afternoon-panel"', false);

    $afternoon = TemperatureReading::query()->where('shift', TemperatureShift::Afternoon)->sole();
    expect($afternoon->temperature)->toBe('-16.0')
        ->and($afternoon->recorded_at->format('Y-m-d H:i:s'))->toBe("2026-11-27 {$time}")
        ->and(TemperatureReading::count())->toBe(2);
})->with(['12:00:00', '13:00:00', '16:00:00', '23:59:00']);

test('the afternoon reading cannot be opened before noon, for another day or for a foreign chamber', function (string $case) {
    [$user, $chamber] = warehouseWithChamber();
    reading($chamber, $user, $case === 'only yesterday has a morning reading' ? '2026-11-26' : '2026-11-27');
    config()->set('temperature.enforce', false);
    $this->travelTo($case === 'before noon' ? '2026-11-27 11:59:00' : '2026-11-27 15:00:00');

    if ($case === 'a chamber of someone else') {
        [$other, $chamber] = warehouseWithChamber();
        reading($chamber, $other);
    }

    Livewire::actingAs($user)->test(TemperatureLog::class)->call('askToCapture', $chamber->id)->assertForbidden();
})->with(['before noon', 'only yesterday has a morning reading', 'a chamber of someone else']);

test('an afternoon form left open past midnight saves nothing', function () {
    [$user, $chamber] = warehouseWithChamber();
    reading($chamber, $user);
    config()->set('temperature.enforce', false);
    $this->travelTo('2026-11-27 23:59:00');
    $log = Livewire::actingAs($user)->test(TemperatureLog::class)
        ->call('askToCapture', $chamber->id)
        ->set('captureValue', '-16')
        ->call('reviewCapture');

    $this->travelTo('2026-11-28 00:01:00');
    $log->call('saveCapture')->assertHasErrors('operation');

    expect(TemperatureReading::count())->toBe(1);
});

test('a form left open across 2 pm does not save the value in the afternoon', function () {
    [$user, $chamber] = warehouseWithChamber();
    $this->travelTo('2026-11-27 13:59:00');
    $gate = Livewire::actingAs($user)->test(TemperatureGate::class)->set("readings.{$chamber->id}", '-18')->call('review');

    $this->travelTo('2026-11-27 14:01:00');
    $gate->call('save')
        ->assertHasErrors('operation')
        ->assertSet("readings.{$chamber->id}", '');

    expect(TemperatureReading::count())->toBe(0);
});

test('the database does not allow readings or corrections to be changed or deleted', function (string $table, string $operation) {
    [$user, $chamber] = warehouseWithChamber();
    $reading = reading($chamber, $user);
    $reading->corrections()->create(['user_id' => $user->id, 'temperature' => '-17.0', 'reason' => 'Error de dedo']);

    expect(fn () => $operation === 'delete' ? DB::table($table)->delete() : DB::table($table)->update(['temperature' => 1]))
        ->toThrow(QueryException::class);
})->with(['temperature_readings', 'temperature_corrections'])->with(['update', 'delete']);

test('every reading gets the same levels in every chamber', function (string $temperature, TemperatureLevel $level) {
    [$user, $chamber] = warehouseWithChamber();

    captureTemperature($user, $chamber, $temperature)->assertHasNoErrors();

    expect(TemperatureReading::sole()->level())->toBe($level);
})->with([
    ['-18.1', TemperatureLevel::TooCold],
    ['-18.0', TemperatureLevel::Normal],
    ['-10.0', TemperatureLevel::Normal],
    ['-9.9', TemperatureLevel::Warning],
    ['-7.0', TemperatureLevel::Warning],
    ['-6.9', TemperatureLevel::Critical],
    ['4', TemperatureLevel::Critical],
]);

test('administrators and requesters cannot open the temperature log', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route('temperatures.index'))->assertForbidden();
})->with(['admin', 'requester']);

test('a manager only sees the temperatures of their chambers', function () {
    [$warehouse, $mine] = warehouseWithChamber();
    [$other, $foreign] = warehouseWithChamber();
    reading($mine, $warehouse);
    reading($foreign, $other, attributes: ['temperature' => '-3.0']);
    $manager = User::factory()->manager()->create();
    $manager->chambers()->attach($mine);

    $log = Livewire::actingAs($manager)->test(TemperatureLog::class)
        ->assertSet('chamberId', $mine->id)
        ->assertSee('-18.0 °C')
        ->assertDontSee($foreign->name)
        ->assertDontSee('data-test="temperature-correct"', false);

    $log->set('chamberId', $foreign->id)->assertForbidden();
});

test('a warehouse user only sees their own readings', function () {
    $branch = Branch::factory()->create();
    [$user, $chamber] = warehouseWithChamber($branch);
    $coworker = User::factory()->warehouse($branch)->create();
    reading($chamber, $user);
    reading($chamber, $coworker, shift: TemperatureShift::Afternoon, attributes: ['temperature' => '-3.0']);
    config()->set('temperature.enforce', false);

    Livewire::actingAs($user)->test(TemperatureLog::class)
        ->assertSee('-18.0 °C')
        ->assertDontSee('-3.0 °C')
        ->assertDontSee('data-test="temperature-missing"', false)
        ->assertDontSee('data-test="temperature-correct"', false);
});

test('the full log lists the slots nobody recorded since the first reading', function () {
    [$user, $chamber] = warehouseWithChamber();
    reading($chamber, $user, '2026-11-25');
    $this->travelTo('2026-11-27 15:00:00');

    // The 25th afternoon, both of the 26th and this morning; this afternoon is still pending.
    Livewire::actingAs(User::factory()->superAdmin()->create())->test(TemperatureLog::class)
        ->assertViewHas('totals', ['readings' => 1, 'critical' => 0, 'warning' => 0, 'too_cold' => 0, 'unmeasured' => 0, 'missing' => 4])
        ->assertSee('data-test="temperature-pending"', false);
});

test('a super administrator corrects a reading and the captured value is kept', function () {
    [$user, $chamber] = warehouseWithChamber();
    $reading = reading($chamber, $user, attributes: ['temperature' => '18.0']);

    Livewire::actingAs(User::factory()->superAdmin()->create())->test(TemperatureLog::class)
        ->call('askToCorrect', $reading->id)
        ->set('correctionValue', '-18')
        ->set('correctionReason', 'Faltó el signo negativo')
        ->call('correct')
        ->assertHasNoErrors()
        ->assertSee('Capturado: 18.0 °C');

    $reading->refresh();
    expect($reading->temperature)->toBe('18.0')
        ->and($reading->effectiveTemperature())->toBe('-18.0')
        ->and($reading->level())->toBe(TemperatureLevel::Normal)
        ->and(AuditLog::query()->where('event', 'temperature.corrected')->count())->toBe(1);
});

test('a correction needs a reason', function () {
    [$user, $chamber] = warehouseWithChamber();
    $reading = reading($chamber, $user);

    Livewire::actingAs(User::factory()->superAdmin()->create())->test(TemperatureLog::class)
        ->call('askToCorrect', $reading->id)
        ->set('correctionValue', '-17')
        ->call('correct')
        ->assertHasErrors('operation');

    expect($reading->corrections()->count())->toBe(0);
});

test('only super administrators can correct a reading', function (string $role) {
    [$warehouse, $chamber] = warehouseWithChamber();
    $reading = reading($chamber, $warehouse);
    $user = $role === 'warehouse' ? $warehouse : User::factory()->manager()->create();

    if ($role === 'manager') {
        $user->chambers()->attach($chamber);
    }

    Livewire::actingAs($user)->test(TemperatureLog::class)->call('askToCorrect', $reading->id)->assertForbidden();
})->with(['warehouse', 'manager']);

test('each day of the chart carries both readings with the date and time they were recorded', function () {
    [$user, $chamber] = warehouseWithChamber();
    reading($chamber, $user);
    $this->travelTo('2026-11-27 15:00:00');

    Livewire::actingAs(User::factory()->superAdmin()->create())->test(TemperatureLog::class)
        ->assertViewHas('chart', fn (array $chart): bool => $chart[0]['date'] === '27/11/2026'
            && $chart[0]['morning']['recorded_at'] === '27/11/2026 08:00'
            && $chart[0]['afternoon']['recorded_at'] === null
            && $chart[0]['afternoon']['status'] === 'pending')
        ->assertSee('Registrada: 27/11/2026 08:00');
});

test('alert levels are named after their severity, not their color', function () {
    expect(array_map(fn (TemperatureLevel $level): string => $level->label(), TemperatureLevel::alerts()))
        ->toBe(['Crítico', 'Advertencia', 'Muy frío']);
});
