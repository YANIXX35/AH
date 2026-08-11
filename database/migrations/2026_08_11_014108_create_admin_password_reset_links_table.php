<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Liens de réinitialisation de mot de passe générés par un platform admin
     * pour un utilisateur donné, à usage unique et à durée de vie courte,
     * transmissibles par n'importe quel canal (WhatsApp, SMS, en main propre)
     * sans dépendre de l'accès à la boîte e-mail de l'utilisateur.
     */
    public function up(): void
    {
        Schema::create('admin_password_reset_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('used_from_ip')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_password_reset_links');
    }
};
