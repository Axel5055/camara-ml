<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Requesters are branches without a chamber that ask a chamber for product: they get the new "requester"
     * role, and each request (with its per-product lines) is kept for good once it is decided.
     */
    public function up(): void
    {
        DB::getPdo()->exec("ALTER TABLE users MODIFY role ENUM('admin', 'super_admin', 'manager', 'warehouse', 'requester') NOT NULL DEFAULT 'warehouse'");

        Schema::create('exit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('chamber_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('total_boxes_requested');
            $table->unsignedInteger('total_boxes_approved')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('exit_id')->nullable()->constrained('exits')->restrictOnDelete();
            $table->string('client_token', 64)->unique();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['chamber_id', 'status']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('exit_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('boxes_requested');
            $table->unsignedInteger('boxes_approved')->nullable();
            $table->timestamps();

            $table->unique(['exit_request_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_request_items');
        Schema::dropIfExists('exit_requests');

        DB::getPdo()->exec("UPDATE users SET role = 'warehouse' WHERE role = 'requester'");
        DB::getPdo()->exec("ALTER TABLE users MODIFY role ENUM('admin', 'super_admin', 'manager', 'warehouse') NOT NULL DEFAULT 'warehouse'");
    }
};
