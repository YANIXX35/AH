<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photo du représentant et pièce d’identité (recto / verso) pour le dossier de financement.
     */
    public function up(): void
    {
        Schema::table('investment_requests', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('certifies_accuracy');
            $table->string('identity_document_front_path')->nullable()->after('photo_path');
            $table->string('identity_document_back_path')->nullable()->after('identity_document_front_path');
            $table->string('identity_document_type', 32)->nullable()->after('identity_document_back_path');
            $table->string('identity_document_number', 64)->nullable()->after('identity_document_type');
            $table->date('identity_document_expires_at')->nullable()->after('identity_document_number');
        });
    }

    public function down(): void
    {
        Schema::table('investment_requests', function (Blueprint $table) {
            $table->dropColumn([
                'photo_path',
                'identity_document_front_path',
                'identity_document_back_path',
                'identity_document_type',
                'identity_document_number',
                'identity_document_expires_at',
            ]);
        });
    }
};
