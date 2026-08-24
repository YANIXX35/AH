<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_entries', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('unpaid')->after('amount');
            $table->decimal('amount_paid', 15, 2)->default(0)->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_entries', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'amount_paid']);
        });
    }
};
