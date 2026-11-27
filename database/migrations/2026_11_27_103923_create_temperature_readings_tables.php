<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Temperature log of the chambers: one reading per chamber, day and shift. Readings and their corrections are
     * append-only; the database rejects updates and deletes.
     *
     * @var list<string>
     */
    private array $appendOnly = ['temperature_readings', 'temperature_corrections'];

    public function up(): void
    {
        Schema::create('temperature_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chamber_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('reading_date');
            $table->string('shift', 10);
            // Null when it could not be measured; the reason is then mandatory.
            $table->decimal('temperature', 4, 1)->nullable();
            $table->string('unmeasured_reason', 500)->nullable();
            $table->dateTime('recorded_at');

            $table->unique(['chamber_id', 'reading_date', 'shift']);
            $table->index(['user_id', 'reading_date']);
        });

        Schema::create('temperature_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temperature_reading_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('temperature', 4, 1);
            $table->string('reason', 500);
            $table->dateTime('created_at');
        });

        foreach ($this->appendOnly as $table) {
            DB::getPdo()->exec("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in {$table} cannot be deleted'");
            DB::getPdo()->exec("CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in {$table} cannot be updated'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('temperature_corrections');
        Schema::dropIfExists('temperature_readings');
    }
};
