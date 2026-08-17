<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('accounting_entries', function (Blueprint $table) {
            // Contrôle qualité périodique (PRD 4.2) : distinct de `ocr_status`, qui ne
            // porte que sur la fiabilité de l'import — ceci revérifie les écritures déjà
            // validées, à intervalle régulier, tant qu'aucune écriture n'est jamais figée.
            $table->string('quality_status')->default('pending')->after('ocr_text'); // pending, compliant, non_compliant
            $table->timestamp('quality_reviewed_at')->nullable()->after('quality_status');
            $table->json('quality_issues')->nullable()->after('quality_reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_entries', function (Blueprint $table) {
            $table->dropColumn(['quality_status', 'quality_reviewed_at', 'quality_issues']);
        });
    }
};
