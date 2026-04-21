<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->string('payment_module', 30)->nullable()->after('transaction_type');
            $table->string('payment_provider', 60)->nullable()->after('payment_module');
            $table->string('mobile_method', 40)->nullable()->after('payment_provider');
            $table->string('mobile_number', 30)->nullable()->after('mobile_method');
            $table->string('card_network', 30)->nullable()->after('mobile_number');
            $table->string('card_last4', 4)->nullable()->after('card_network');
            $table->string('bank_name', 100)->nullable()->after('card_last4');
            $table->string('bank_reference', 120)->nullable()->after('bank_name');

            $table->index('payment_module');
            $table->index('payment_provider');
        });
    }

    public function down(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->dropIndex(['payment_module']);
            $table->dropIndex(['payment_provider']);

            $table->dropColumn([
                'payment_module',
                'payment_provider',
                'mobile_method',
                'mobile_number',
                'card_network',
                'card_last4',
                'bank_name',
                'bank_reference',
            ]);
        });
    }
};
