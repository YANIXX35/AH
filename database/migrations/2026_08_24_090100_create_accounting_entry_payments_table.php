<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_entry_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_entry_id')->constrained('accounting_entries')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('method', 30)->default('autre');
            $table->string('reference')->nullable();
            $table->foreignId('treasury_transaction_id')->nullable()->constrained('treasury_transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['accounting_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_entry_payments');
    }
};
