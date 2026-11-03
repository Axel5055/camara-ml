<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The legacy product codes are up to 26 characters long, so the product code now accepts 30 and the
     * barcode (code + date + counter) 50.
     */
    public function up(): void
    {
        DB::getPdo()->exec('ALTER TABLE products DROP CHECK products_code_format');
        DB::getPdo()->exec('ALTER TABLE products MODIFY code VARCHAR(30) NOT NULL');
        DB::getPdo()->exec("ALTER TABLE products ADD CONSTRAINT products_code_format CHECK (code REGEXP '^[A-Z0-9]{2,30}$')");

        DB::getPdo()->exec('ALTER TABLE boxes MODIFY barcode VARCHAR(50) NOT NULL');
    }

    public function down(): void
    {
        DB::getPdo()->exec('ALTER TABLE boxes MODIFY barcode VARCHAR(40) NOT NULL');

        DB::getPdo()->exec('ALTER TABLE products DROP CHECK products_code_format');
        DB::getPdo()->exec('ALTER TABLE products MODIFY code VARCHAR(12) NOT NULL');
        DB::getPdo()->exec("ALTER TABLE products ADD CONSTRAINT products_code_format CHECK (code REGEXP '^[A-Z0-9]{2,12}$')");
    }
};
