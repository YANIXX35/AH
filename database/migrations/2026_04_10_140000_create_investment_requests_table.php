<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demandes d'investissement liées à l'utilisateur (étape préparation investisseurs).
     */
    public function up(): void
    {
        Schema::create('investment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_requested', 15, 2);
            $table->string('currency', 10)->default('XOF');
            $table->string('horizon', 32)->nullable();
            $table->text('purpose');
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_requests');
    }
};
