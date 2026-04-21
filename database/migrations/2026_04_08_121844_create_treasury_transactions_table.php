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
        Schema::create('treasury_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['encaissement', 'decaissement']); // Entrée ou sortie
            $table->string('transaction_type'); // Paiement client, Paiement fournisseur, etc.
            $table->decimal('amount', 12, 2);
            $table->string('description');
            $table->date('transaction_date');
            $table->string('reference')->nullable();
            $table->string('bank_account')->nullable();
            $table->enum('status', ['planifie', 'effectue', 'annule'])->default('planifie');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('transaction_date');
            $table->index('type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_transactions');
    }
};
