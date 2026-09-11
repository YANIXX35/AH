<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('erpnext_company_name')->nullable()->after('erpnext_customer_id');
            $table->string('erpnext_warehouse')->nullable()->after('erpnext_company_name');
            $table->string('erpnext_tax_template')->nullable()->after('erpnext_warehouse');
            $table->string('erpnext_income_account')->nullable()->after('erpnext_tax_template');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'erpnext_company_name',
                'erpnext_warehouse',
                'erpnext_tax_template',
                'erpnext_income_account',
            ]);
        });
    }
};
