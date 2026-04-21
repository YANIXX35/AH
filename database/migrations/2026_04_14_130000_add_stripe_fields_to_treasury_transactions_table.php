<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->string('stripe_checkout_session_id', 100)->nullable()->after('bank_reference');
            $table->string('stripe_payment_intent_id', 100)->nullable()->after('stripe_checkout_session_id');
            $table->string('stripe_charge_id', 100)->nullable()->after('stripe_payment_intent_id');
            $table->string('stripe_payout_id', 100)->nullable()->after('stripe_charge_id');
            $table->string('stripe_status', 40)->nullable()->after('stripe_payout_id');
            $table->text('stripe_failure_reason')->nullable()->after('stripe_status');
            $table->timestamp('stripe_paid_at')->nullable()->after('stripe_failure_reason');
            $table->string('stripe_last_event_id', 100)->nullable()->after('stripe_paid_at');

            $table->index('stripe_checkout_session_id');
            $table->index('stripe_payment_intent_id');
            $table->index('stripe_last_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->dropIndex(['stripe_checkout_session_id']);
            $table->dropIndex(['stripe_payment_intent_id']);
            $table->dropIndex(['stripe_last_event_id']);

            $table->dropColumn([
                'stripe_checkout_session_id',
                'stripe_payment_intent_id',
                'stripe_charge_id',
                'stripe_payout_id',
                'stripe_status',
                'stripe_failure_reason',
                'stripe_paid_at',
                'stripe_last_event_id',
            ]);
        });
    }
};
