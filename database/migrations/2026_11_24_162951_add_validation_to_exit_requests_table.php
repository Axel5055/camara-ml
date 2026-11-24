<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Once the warehouse user checks an authorized request, its exit moves from "Por solicitud" to the direct
     * exits. Who validated it and when stay on the request for traceability.
     */
    public function up(): void
    {
        Schema::table('exit_requests', function (Blueprint $table) {
            $table->dateTime('validated_at')->nullable()->after('exit_id');
            $table->foreignId('validated_by')->nullable()->after('validated_at')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exit_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn('validated_at');
        });
    }
};
