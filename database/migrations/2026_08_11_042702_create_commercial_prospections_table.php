<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rapports de prospection libres des commerciaux : texte libre et/ou
     * fichier importé, aucun champ métier obligatoire. Voir CommercialProspection
     * pour la règle "au moins content OU file_path".
     */
    public function up(): void
    {
        Schema::create('commercial_prospections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->longText('content')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_type', 20)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_comment')->nullable();
            $table->timestamps();

            $table->index(['commercial_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_prospections');
    }
};
