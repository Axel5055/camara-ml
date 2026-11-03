<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Until now "admin" meant full control. That role becomes "super_admin", and "admin" is freed for the new
     * read-only administrator.
     */
    public function up(): void
    {
        DB::getPdo()->exec("ALTER TABLE users MODIFY role ENUM('admin', 'super_admin', 'warehouse') NOT NULL DEFAULT 'warehouse'");
        DB::getPdo()->exec("UPDATE users SET role = 'super_admin' WHERE role = 'admin'");
    }

    public function down(): void
    {
        DB::getPdo()->exec("UPDATE users SET role = 'warehouse' WHERE role = 'admin'");
        DB::getPdo()->exec("UPDATE users SET role = 'admin' WHERE role = 'super_admin'");
        DB::getPdo()->exec("ALTER TABLE users MODIFY role ENUM('admin', 'warehouse') NOT NULL DEFAULT 'warehouse'");
    }
};
