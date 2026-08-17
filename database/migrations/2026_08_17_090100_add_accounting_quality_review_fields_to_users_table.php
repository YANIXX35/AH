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
        Schema::table('users', function (Blueprint $table) {
            // Cadence trimestrielle par entreprise (PRD 4.2) : date de la dernière revue
            // qualité complète du grand-livre, utilisée par la commande planifiée pour
            // savoir quelles entreprises sont dues sans tout revérifier à chaque exécution.
            $table->timestamp('accounting_quality_reviewed_at')->nullable()->after('must_change_password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('accounting_quality_reviewed_at');
        });
    }
};
