<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Managers read like administrators but only see the branches (all their chambers) and the individual
     * chambers assigned to them: branches go in branch_user, chambers in the existing chamber_user.
     */
    public function up(): void
    {
        DB::getPdo()->exec("ALTER TABLE users MODIFY role ENUM('admin', 'super_admin', 'manager', 'warehouse') NOT NULL DEFAULT 'warehouse'");

        Schema::create('branch_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_user');

        DB::getPdo()->exec("UPDATE users SET role = 'admin' WHERE role = 'manager'");
        DB::getPdo()->exec("ALTER TABLE users MODIFY role ENUM('admin', 'super_admin', 'warehouse') NOT NULL DEFAULT 'warehouse'");
    }
};
