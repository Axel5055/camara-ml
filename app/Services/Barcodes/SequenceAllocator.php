<?php

namespace App\Services\Barcodes;

use App\Exceptions\InventoryException;
use Illuminate\Support\Facades\DB;

class SequenceAllocator
{
    /**
     * Reserve a block of $count consecutive values and return the first one.
     *
     * Call this BEFORE opening the business transaction: the row lock is then released as soon as the
     * block is reserved, so concurrent operations only wait for milliseconds. If the business transaction
     * later fails, the reserved values are simply skipped (gaps are acceptable; duplicates are not).
     */
    public function next(string $name, int $count = 1): int
    {
        if ($count < 1) {
            throw new InventoryException('La cantidad a reservar debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($name, $count): int {
            $current = DB::table('sequences')->where('name', $name)->lockForUpdate()->value('last_value');

            if ($current === null) {
                throw new InventoryException("La secuencia '{$name}' no existe.");
            }

            DB::table('sequences')->where('name', $name)->update([
                'last_value' => $current + $count,
                'updated_at' => now(),
            ]);

            return $current + 1;
        });
    }

    public function reference(string $prefix, int $number): string
    {
        return sprintf('%s-%06d', $prefix, $number);
    }
}
