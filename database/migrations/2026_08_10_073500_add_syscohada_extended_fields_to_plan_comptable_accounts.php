<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_comptable_accounts', function (Blueprint $table) {
            $table->string('nature')->nullable()->after('observation');
            $table->string('categorie_bceao')->nullable()->after('nature');
            $table->string('flux_tafire')->nullable()->after('categorie_bceao');
            $table->string('eligible_tva')->nullable()->after('flux_tafire');
            $table->boolean('eligible_echeancier')->default(false)->after('eligible_tva');
            $table->boolean('lie_immobilisation')->default(false)->after('eligible_echeancier');
        });
    }

    public function down(): void
    {
        Schema::table('plan_comptable_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'nature', 'categorie_bceao', 'flux_tafire',
                'eligible_tva', 'eligible_echeancier', 'lie_immobilisation',
            ]);
        });
    }
};
