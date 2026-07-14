<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_documents', function (Blueprint $table) {
            // Distinct de `confidence` (mélange technique moteur OCR + champs) : ratio de
            // présence des champs obligatoires (numéro de pièce, dates, identification du
            // tiers), exposé comme signal de confiance métier — PRD 4.1.
            $table->decimal('compliance_rate', 5, 2)->default(0)->after('confidence');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_documents', function (Blueprint $table) {
            $table->dropColumn('compliance_rate');
        });
    }
};
