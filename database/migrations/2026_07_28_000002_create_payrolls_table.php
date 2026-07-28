<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->string('period_month'); // e.g. "Juillet 2026"
            $table->date('payment_date');
            $table->string('payment_method')->default('bank_transfer'); // bank_transfer, wave, orange_money, mtn, check, cash
            $table->string('payment_account')->nullable(); // e.g. "NSIA Banque CI" or "Compte Principal"
            $table->decimal('total_gross', 15, 2)->default(0);
            $table->decimal('total_cnps', 15, 2)->default(0);
            $table->decimal('total_its', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->string('file_path')->nullable();
            $table->string('status')->default('draft'); // draft, synced, cancelled
            $table->foreignId('accounting_entry_id')->nullable()->constrained('accounting_entries')->onDelete('set null');
            $table->foreignId('treasury_transaction_id')->nullable()->constrained('treasury_transactions')->onDelete('set null');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->onDelete('cascade');
            $table->string('employee_name');
            $table->string('employee_matricule')->nullable();
            $table->string('employee_job')->nullable();
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('bonuses', 15, 2)->default(0);
            $table->decimal('cnps_employee', 15, 2)->default(0);
            $table->decimal('cnps_employer', 15, 2)->default(0);
            $table->decimal('its_tax', 15, 2)->default(0);
            $table->decimal('net_payable', 15, 2)->default(0);
            $table->string('payment_details')->nullable(); // e.g. RIB or Mobile Money phone
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
    }
};
