<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plan comptable de référence (SYSCOHADA) utilisé pour installer le plan
     * par défaut de tout nouveau compte. Contrairement à
     * database/data/syscohada_plan_comptable_default.php (figé dans le code,
     * écrasé à chaque déploiement), cette table peut être mise à jour par un
     * platform admin depuis l'interface sans perdre les changements au
     * prochain git pull.
     */
    public function up(): void
    {
        Schema::create('plan_comptable_defaults', function (Blueprint $table) {
            $table->id();
            $table->string('numero_compte', 20)->unique();
            $table->string('libelle_compte');
            $table->string('prefix', 3);
            $table->string('classe', 2);
            $table->string('category');
            $table->string('subtype')->nullable();
            $table->string('type_compte')->nullable();
            $table->text('observation')->nullable();
            $table->string('nature')->nullable();
            $table->string('categorie_bceao')->nullable();
            $table->string('flux_tafire')->nullable();
            $table->string('eligible_tva')->nullable();
            $table->boolean('eligible_echeancier')->default(false);
            $table->boolean('lie_immobilisation')->default(false);
            $table->boolean('is_actif')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_comptable_defaults');
    }
};
