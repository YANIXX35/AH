<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->string('stripe_payment_channel', 30)->nullable()->after('stripe_payment_intent_id');
            $table->string('stripe_bank_scheme', 20)->nullable()->after('stripe_payment_channel');
            $table->index('stripe_payment_channel');
            $table->index('stripe_bank_scheme');
        });
    }

    public function down(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->dropIndex(['stripe_payment_channel']);
            $table->dropIndex(['stripe_bank_scheme']);
            $table->dropColumn(['stripe_payment_channel', 'stripe_bank_scheme']);
        });
    }
};
