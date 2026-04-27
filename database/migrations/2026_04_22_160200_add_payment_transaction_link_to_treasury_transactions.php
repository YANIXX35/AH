<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->foreignId('payment_transaction_id')
                ->nullable()
                ->after('user_id')
                ->constrained('payment_transactions')
                ->nullOnDelete();
            $table->index('payment_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->dropForeign(['payment_transaction_id']);
            $table->dropIndex(['payment_transaction_id']);
            $table->dropColumn('payment_transaction_id');
        });
    }
};
