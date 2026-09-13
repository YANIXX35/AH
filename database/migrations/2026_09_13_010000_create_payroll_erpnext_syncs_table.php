<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_erpnext_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->unique()->constrained('payroll_runs')->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('erpnext_accrual_entry_name')->nullable();
            $table->string('erpnext_payment_entry_name')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_erpnext_syncs');
    }
};
