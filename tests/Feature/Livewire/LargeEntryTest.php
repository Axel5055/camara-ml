<?php

use App\Livewire\Inventory\CreateEntry;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\EntryRequest;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

/**
 * @return array<int, array<string, mixed>>
 */
function largeEntryLines(int $groups, int $boxesPerGroup): array
{
    return Product::factory()->count($groups)->create()->map(fn (Product $product): array => [
        'product_id' => $product->id,
        'product' => $product->name,
        'code' => (string) $product->code,
        'boxes_count' => $boxesPerGroup,
        'weight' => '20.000',
        'cost' => '6.00',
        'manual' => [],
        'origin' => null,
    ])->all();
}

it('registers a very large entry directly', function () {
    $branch = Branch::factory()->create();
    $chamber = Chamber::factory()->for($branch)->create();
    $admin = User::factory()->superAdmin()->create();

    $started = microtime(true);

    Livewire::actingAs($admin)->test(CreateEntry::class)
        ->set('chamberId', $chamber->id)
        ->set('lines', largeEntryLines(60, 50))
        ->call('askConfirm')
        ->assertHasNoErrors()
        ->call('save')
        ->assertHasNoErrors();

    expect(Box::query()->count())->toBe(3000)
        ->and(microtime(true) - $started)->toBeLessThan(60);
});

it('sends a very large entry as a request when a warehouse user captures it', function () {
    $branch = Branch::factory()->create();
    $chamber = Chamber::factory()->for($branch)->create();
    $user = User::factory()->warehouse($branch)->create();
    $user->chambers()->attach($chamber);

    Livewire::actingAs($user)->test(CreateEntry::class)
        ->set('lines', largeEntryLines(60, 50))
        ->call('askConfirm')
        ->assertHasNoErrors()
        ->call('save')
        ->assertHasNoErrors();

    expect(EntryRequest::query()->count())->toBe(1);
});

it('shows what blocked the save next to the register button', function () {
    $branch = Branch::factory()->create();
    $chamber = Chamber::factory()->for($branch)->create();
    $admin = User::factory()->superAdmin()->create();

    Livewire::actingAs($admin)->test(CreateEntry::class)
        ->set('chamberId', $chamber->id)
        ->set('lines', largeEntryLines(3, 5))
        ->set('notes', str_repeat('x', 2001))
        ->call('askConfirm')
        ->assertSeeHtml('data-test="save-errors"')
        ->assertSee('Corrige los campos marcados en rojo: Observaciones.');

    Livewire::actingAs($admin)->test(CreateEntry::class)
        ->set('chamberId', $chamber->id)
        ->call('askConfirm')
        ->assertSeeHtml('data-test="save-errors"')
        ->assertSee('Agrega al menos un grupo de cajas');
});
