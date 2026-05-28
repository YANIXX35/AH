<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprise_licenses', function (Blueprint $table) {
            // NIF normalisé : la clé ne peut couvrir qu’une seule entreprise après attribution.
            $table->string('assigned_company_tax_id', 64)->nullable()->after('license_key');
            $table->index('assigned_company_tax_id');
        });
    }

    public function down(): void
    {
        Schema::table('enterprise_licenses', function (Blueprint $table) {
            $table->dropIndex(['assigned_company_tax_id']);
            $table->dropColumn('assigned_company_tax_id');
        });
    }
};
