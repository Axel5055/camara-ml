<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entry_requests', function (Blueprint $table) {
            $table->dateTime('ticket_prompted_at')->nullable()->after('entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('entry_requests', function (Blueprint $table) {
            $table->dropColumn('ticket_prompted_at');
        });
    }
};
