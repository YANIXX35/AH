
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_comptable_accounts', function (Blueprint $table) {
            // Add new columns for detailed SYSCOHADA accounts
            $table->string('numero_compte', 20)->nullable()->after('id'); // e.g., 100, 101, 102...
            $table->string('libelle_compte')->nullable()->after('numero_compte');
            $table->string('type_compte', 50)->nullable()->after('libelle_compte'); // Actif/Passif/Charge/Produit
            $table->string('sous_type', 100)->nullable()->after('type_compte');
            $table->string('classe', 2)->nullable()->after('sous_type'); // 1-7
            $table->text('observation')->nullable()->after('subtype');
            $table->boolean('is_actif')->default(true)->after('observation');
            $table->integer('sort_order')->default(0)->after('is_actif');

            // Update unique constraint: keep existing for backward compatibility, add new for numero_compte
            $table->unique(['user_id', 'numero_compte']);
        });
    }

    public function down(): void
    {
        Schema::table('plan_comptable_accounts', function (Blueprint $table) {
            $table->dropUnique('plan_comptable_accounts_user_id_numero_compte_unique');
            $table->dropColumn([
                'numero_compte', 'libelle_compte', 'type_compte',
                'sous_type', 'classe', 'observation', 'is_actif', 'sort_order',
            ]);
        });
    }
};
