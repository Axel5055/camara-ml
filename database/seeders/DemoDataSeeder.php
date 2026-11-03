<?php

namespace Database\Seeders;

use App\Enums\AdjustmentType;
use App\Models\Box;
use App\Models\Branch;
use App\Models\Chamber;
use App\Models\Product;
use App\Models\StockExit;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Inventory\AdjustInventory;
use App\Services\Inventory\CancelTransfer;
use App\Services\Inventory\ReceiveTransfer;
use App\Services\Inventory\RegisterEntry;
use App\Services\Inventory\RegisterExit;
use App\Services\Inventory\ReverseExit;
use App\Services\Inventory\WithdrawPartial;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use RuntimeException;

/**
 * Demo scenario to check every screen: three branches (Centro and Norte with one chamber each, Sur only as a
 * delivery destination), the five test users and stock, exits, transfers (received, with incidents, in transit
 * and cancelled), a partial withdrawal, a reversal and adjustments. Everything goes through the real inventory
 * services, so it is fully consistent. The password of every user is "password".
 *
 * The product catalog must already exist. Run it on a development database only:
 * php artisan db:seed --class=DemoDataSeeder. Running it twice does nothing.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Branch::query()->where('code', 'DEMO-CENTRO')->exists()) {
            $this->command?->warn('Los datos de demostración ya existen; no se hizo nada.');

            return;
        }

        $centro = Branch::query()->create(['code' => 'DEMO-CENTRO', 'name' => 'Sucursal Centro', 'is_active' => true]);
        $norte = Branch::query()->create(['code' => 'DEMO-NORTE', 'name' => 'Sucursal Norte', 'is_active' => true]);
        $sur = Branch::query()->create(['code' => 'DEMO-SUR', 'name' => 'Sucursal Sur', 'is_active' => true]);

        $c1 = Chamber::query()->create(['branch_id' => $centro->id, 'code' => 'DEMO-C1', 'name' => 'Cámara Centro 1', 'is_active' => true]);
        $n1 = Chamber::query()->create(['branch_id' => $norte->id, 'code' => 'DEMO-N1', 'name' => 'Cámara Norte 1', 'is_active' => true]);

        $admin = User::factory()->superAdmin()->create(['name' => 'Administrador', 'email' => 'admin@example.com']);
        User::factory()->admin()->create(['name' => 'Administrador (consulta)', 'email' => 'consulta@example.com']);
        $centroUser = User::factory()->warehouse($centro)->create(['name' => 'Almacenista Centro', 'email' => 'almacen1@example.com']);
        $centroUser->chambers()->attach($c1);
        $norteUser = User::factory()->warehouse($norte)->create(['name' => 'Almacenista Norte', 'email' => 'almacen2@example.com']);
        $norteUser->chambers()->attach($n1);
        User::factory()->warehouse($sur)->create(['name' => 'Almacenista sin cámara', 'email' => 'prueba@example.com']);

        $entry = app(RegisterEntry::class);
        $exit = app(RegisterExit::class);

        $stock = [
            [$c1, $centroUser, 9, [['TILMC', 40, 10, 185], ['NUGMP', 30, 12, 142.5], ['PAPCUR', 25, 12.5, 96], ['CAMCOC', 18, 5, 320]]],
            [$c1, $admin, 7, [['SAL10KG', 12, 10, 890], ['PAVAHU', 8, 9, 210], ['BACAL', 6, 15, 410], ['TENEMP', 12, 10, 158]]],
            [$n1, $norteUser, 8, [['TENEMP', 35, 10, 160], ['PAPCUR', 22, 12.5, 98], ['TILMC', 15, 10, 188], ['NUGMP', 14, 12, 140]]],
            [$n1, $admin, 5, [['SAL10KG', 5, 10, 900], ['DEDQUE', 4, 12, 112]]],
            [$c1, $centroUser, 0, [['DEDQUE', 4, 12, 110]]],
            [$n1, $norteUser, 0, [['BACAL', 3, 15, 415]]],
        ];

        foreach ($stock as [$chamber, $user, $daysAgo, $groups]) {
            $entry->handle($user, $chamber, array_map(fn (array $g): array => [
                'product_id' => $this->product($g[0])->id,
                'boxes_count' => $g[1],
                'weight_per_box_kg' => (string) $g[2],
                'unit_cost_mxn' => (string) $g[3],
            ], $groups), now()->subDays($daysAgo)->setTime(9, 30), $daysAgo === 0 ? 'Recepción de proveedor' : null);
        }

        // Deliveries to the branch that has no chamber.
        $exit->handle($centroUser, $c1, $sur, null, 'Juan Pérez', $this->boxes($c1, 'TILMC', 5), now()->subDays(5)->setTime(11, 0));
        $exit->handle($centroUser, $c1, $sur, null, 'María López', $this->boxes($c1, 'NUGMP', 10), now()->subDays(3)->setTime(12, 15), 'Pedido semanal');
        $exit->handle($norteUser, $n1, $sur, null, 'Carlos Ruiz', $this->boxes($n1, 'TILMC', 5), now()->subDays(2)->setTime(10, 0));
        $exit->handle($centroUser, $c1, $sur, null, 'Laura Gómez', $this->boxes($c1, 'CAMCOC', 8), now()->subDays(1)->setTime(16, 40));
        $exit->handle($centroUser, $c1, $sur, null, 'Laura Gómez', $this->boxes($c1, 'SAL10KG', 6), now()->setTime(8, 20));

        app(WithdrawPartial::class)->handle($norteUser, $this->boxModels($n1, 'TENEMP', 1)[0], '4', $sur, 'Laura Gómez', now()->subDays(2)->setTime(13, 5), 'Consumo de cocina');

        $mistake = $exit->handle($norteUser, $n1, $sur, null, 'Pedro Sánchez', $this->boxes($n1, 'TENEMP', 3), now()->subDays(1)->setTime(9, 0));
        app(ReverseExit::class)->handle($admin, $mistake, 'Error de captura: las cajas no salieron del almacén.');

        // Transfers already received: complete, with a missing box, with a weight difference.
        $complete = $this->transfer($exit, $norteUser, $n1, $c1, 'Ana Torres', $this->boxes($n1, 'TENEMP', 5), now()->subDays(4)->setTime(15, 0));
        $this->receive($centroUser, $complete, $complete->items->pluck('box_id')->all(), notes: 'Todo llegó en buen estado y a la temperatura correcta.');

        $second = $this->transfer($exit, $centroUser, $c1, $n1, 'Ana Torres', $this->boxes($c1, 'NUGMP', 6), now()->subDays(3)->setTime(9, 0));
        $this->receive($norteUser, $second, $second->items->pluck('box_id')->all(), hours: 2);

        $missing = $this->transfer($exit, $centroUser, $c1, $n1, 'Ana Torres', $this->boxes($c1, 'BACAL', 3), now()->subDays(2)->setTime(14, 0));
        $this->receive($norteUser, $missing, array_slice($missing->items->pluck('box_id')->all(), 0, 2), notes: 'Solo llegaron dos cajas; la tercera no venía en el camión.', hours: 6);

        $difference = $this->transfer($exit, $norteUser, $n1, $c1, 'Ana Torres', $this->boxes($n1, 'PAPCUR', 2), now()->subDays(1)->setTime(10, 30));
        $differenceIds = $difference->items->pluck('box_id')->all();
        $this->receive($centroUser, $difference, $differenceIds, [$differenceIds[0] => '11.800'], 'Una caja con menos peso del esperado.', 4);

        // Cancelled by the origin.
        $cancelled = $this->transfer($exit, $centroUser, $c1, $n1, 'Ana Torres', $this->boxes($c1, 'PAVAHU', 2), now()->subHours(30));
        $this->cancel($admin, $cancelled, 'El destino no tenía espacio disponible.');

        // Still in transit: two waiting for the Norte chamber and two waiting for the Centro chamber.
        $this->transfer($exit, $centroUser, $c1, $n1, 'Ana Torres', $this->boxes($c1, 'TILMC', 8), now()->subHours(5), 'Surtido para sucursal Norte');
        $this->transfer($exit, $centroUser, $c1, $n1, 'Luis Ramírez', $this->boxes($c1, 'NUGMP', 4), now()->subHours(28));
        $this->transfer($exit, $norteUser, $n1, $c1, 'Ana Torres', $this->boxes($n1, 'TENEMP', 6), now()->subHours(3), 'Reposición para Centro');
        $this->transfer($exit, $norteUser, $n1, $c1, 'Luis Ramírez', $this->boxes($n1, 'PAPCUR', 3), now()->subHours(20));

        $adjust = app(AdjustInventory::class);
        $adjust->handle($admin, $this->boxModels($c1, 'TILMC', 1)[0], AdjustmentType::Lost, 'Merma por falla de la cadena de frío.', requestedBy: $centroUser);
        $adjust->handle($admin, $this->boxModels($n1, 'PAPCUR', 1)[0], AdjustmentType::WeightCorrection, 'La báscula estaba descalibrada; peso real verificado.', '12.100', requestedBy: $norteUser);
        $adjust->handle($admin, $this->boxModels($c1, 'TENEMP', 1)[0], AdjustmentType::Damaged, 'Caja aplastada al descargar.', requestedBy: $centroUser);
    }

    private function product(string $code): Product
    {
        return Product::query()->where('code', $code)->firstOrFail();
    }

    /**
     * @return list<Box>
     */
    private function boxModels(Chamber $chamber, string $productCode, int $count): array
    {
        $boxes = Box::query()
            ->where('current_chamber_id', $chamber->id)
            ->where('status', 'available')
            ->where('product_id', $this->product($productCode)->id)
            ->whereColumn('current_weight_kg', 'initial_weight_kg')
            ->orderBy('id')
            ->limit($count)
            ->get();

        if ($boxes->count() < $count) {
            throw new RuntimeException("No hay {$count} cajas de {$productCode} en {$chamber->name}.");
        }

        return array_values($boxes->all());
    }

    /**
     * @return list<int>
     */
    private function boxes(Chamber $chamber, string $productCode, int $count): array
    {
        return array_map(fn (Box $box): int => $box->id, $this->boxModels($chamber, $productCode, $count));
    }

    /**
     * @param  list<int>  $boxIds
     */
    private function transfer(RegisterExit $exit, User $user, Chamber $origin, Chamber $dest, string $requester, array $boxIds, CarbonInterface $at, ?string $notes = null): Transfer
    {
        /** @var StockExit $created */
        $created = $exit->handle($user, $origin, $dest->branch, $dest, $requester, $boxIds, $at, $notes);

        return $created->transfer()->with('items')->firstOrFail();
    }

    /**
     * Receive a transfer as if it happened some hours after it was sent.
     *
     * @param  array<int, int|string>  $receivedIds
     * @param  array<int, string>  $weights
     */
    private function receive(User $user, Transfer $transfer, array $receivedIds, array $weights = [], ?string $notes = null, int $hours = 3): void
    {
        $this->at($transfer, $hours, fn () => app(ReceiveTransfer::class)->handle($user, $transfer, $receivedIds, $weights, $notes));
    }

    private function cancel(User $user, Transfer $transfer, string $reason): void
    {
        $this->at($transfer, 1, fn () => app(CancelTransfer::class)->handle($user, $transfer, $reason));
    }

    private function at(Transfer $transfer, int $hoursAfterDispatch, callable $action): void
    {
        Date::setTestNow(CarbonImmutable::instance($transfer->dispatched_at)->addHours($hoursAfterDispatch));

        try {
            $action();
        } finally {
            Date::setTestNow();
        }
    }
}
