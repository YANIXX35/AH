<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_comptable_accounts', function (Blueprint $table) {
            // Supprimer la contrainte d'unicité obsolète sur (user_id, prefix) qui bloquait l'import de plusieurs comptes d'une même classe
            try {
                $table->dropUnique('plan_comptable_accounts_user_id_prefix_unique');
            } catch (Throwable $e) {
                // Fallback si la contrainte a déjà été supprimée
            }
        });
    }

    public function down(): void
    {
        Schema::table('plan_comptable_accounts', function (Blueprint $table) {
            try {
                $table->unique(['user_id', 'prefix']);
            } catch (Throwable $e) {
                // Ignore
            }
        });
    }
};
