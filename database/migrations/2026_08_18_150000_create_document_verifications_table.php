<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('type', 20);
            $table->unsignedBigInteger('user_id');
            $table->string('company_name');
            $table->string('company_sigle')->nullable();
            $table->string('company_tax_id')->nullable();
            $table->string('exercise_year', 4);
            $table->decimal('total_actif', 18, 2)->nullable();
            $table->decimal('total_passif', 18, 2)->nullable();
            $table->decimal('resultat_net', 18, 2)->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_verifications');
    }
};
