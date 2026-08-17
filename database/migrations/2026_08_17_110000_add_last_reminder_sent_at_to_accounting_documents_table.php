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
        Schema::table('accounting_documents', function (Blueprint $table) {
            // Relance des pièces en attente de revue manuelle : évite de renvoyer une
            // notification chaque jour tant que la pièce reste non traitée.
            $table->timestamp('last_reminder_sent_at')->nullable()->after('compliance_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_documents', function (Blueprint $table) {
            $table->dropColumn('last_reminder_sent_at');
        });
    }
};
