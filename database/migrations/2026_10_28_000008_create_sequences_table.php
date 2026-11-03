<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('name', 30)->primary();
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('sequences')->insert(
            collect(['barcode', 'entry', 'exit', 'transfer', 'adjustment'])
                ->map(fn (string $name): array => [
                    'name' => $name,
                    'last_value' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
