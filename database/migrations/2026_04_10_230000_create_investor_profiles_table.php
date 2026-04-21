<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('risk_score', 5, 2);
            $table->decimal('performance_score', 5, 2);
            $table->string('profile_code', 32);
            $table->string('profile_label', 160);
            $table->text('profile_detail')->nullable();
            $table->string('classement_code', 32)->nullable();
            $table->string('classement_libelle', 160)->nullable();
            $table->json('operational_breakdown')->nullable();
            $table->json('financial_snapshot')->nullable();
            $table->timestamp('computed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_profiles');
    }
};
