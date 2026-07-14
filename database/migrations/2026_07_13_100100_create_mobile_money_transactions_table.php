<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_money_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_import_id')->constrained('mobile_money_statement_imports')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('operator', 30);
            $table->string('external_reference')->nullable();
            $table->date('occurred_at');
            $table->decimal('amount', 15, 2);
            $table->string('direction', 10);
            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_number', 40)->nullable();
            $table->text('raw_line')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('treasury_transaction_id')->nullable()->constrained('treasury_transactions')->nullOnDelete();
            $table->foreignId('accounting_entry_id')->nullable()->constrained('accounting_entries')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'occurred_at', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_money_transactions');
    }
};
