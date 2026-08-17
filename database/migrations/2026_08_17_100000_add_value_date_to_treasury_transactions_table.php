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
        Schema::table('treasury_transactions', function (Blueprint $table) {
            // Trésorerie réelle vs théorique (PRD 4.4) : `transaction_date` reste la date
            // de l'opération, `value_date` est la date à laquelle les fonds sont
            // effectivement disponibles en banque. Pour Mobile Money, les deux
            // coïncident toujours (encaissement réel et immédiat) — voir
            // MobileMoneyReconciliationService. Pour un instrument bancaire classique
            // (chèque, virement), value_date peut être postérieure.
            $table->date('value_date')->nullable()->after('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->dropColumn('value_date');
        });
    }
};
