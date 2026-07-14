<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_money_statement_imports', function (Blueprint $table) {
            $table->timestamp('consent_given_at')->nullable()->after('treasury_account_code');
            $table->string('consent_ip', 45)->nullable()->after('consent_given_at');
            $table->timestamp('personal_data_purged_at')->nullable()->after('consent_ip');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_money_statement_imports', function (Blueprint $table) {
            $table->dropColumn(['consent_given_at', 'consent_ip', 'personal_data_purged_at']);
        });
    }
};
