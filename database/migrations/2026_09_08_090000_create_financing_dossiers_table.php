<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financing_dossiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('analyst_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reference', 32)->unique();

            // Workflow — mêmes valeurs et mêmes colonnes que investment_requests,
            // pour rester compatible avec le workflow déjà en place.
            $table->string('status', 32)->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            // Étape 1 — Demande de financement
            $table->string('financing_type', 64)->nullable();
            $table->string('financing_purpose', 64)->nullable();
            $table->decimal('amount_requested', 15, 2)->nullable();
            $table->string('currency', 10)->default('XOF');
            $table->unsignedInteger('desired_term_months')->nullable();
            $table->unsignedInteger('grace_period_months')->nullable();
            $table->string('repayment_frequency', 32)->nullable();
            $table->decimal('promoter_contribution', 15, 2)->nullable();
            $table->date('desired_disbursement_date')->nullable();
            $table->json('financing_summary_data')->nullable();

            // Étape 2 — Entreprise
            $table->string('legal_name')->nullable();
            $table->string('trade_name')->nullable();
            $table->string('legal_form', 64)->nullable();
            $table->string('rccm_number', 64)->nullable();
            $table->string('taxpayer_number', 64)->nullable();
            $table->date('incorporation_date')->nullable();
            $table->string('registered_office')->nullable();
            $table->string('city_country')->nullable();
            $table->string('business_sector', 64)->nullable();
            $table->string('main_activity')->nullable();
            $table->unsignedInteger('employee_count')->nullable();
            $table->string('website')->nullable();
            $table->text('company_history')->nullable();
            $table->decimal('share_capital', 15, 2)->nullable();
            $table->string('major_shareholders')->nullable();
            $table->string('beneficial_owners')->nullable();
            $table->string('authorized_representative')->nullable();

            // Champs repris d'InvestmentRequest, utilisés aux étapes 10/12
            $table->text('attachments_commitment')->nullable();
            $table->boolean('certifies_accuracy')->default(false);
            $table->string('photo_path')->nullable();
            $table->string('identity_document_front_path')->nullable();
            $table->string('identity_document_back_path')->nullable();
            $table->string('identity_document_type', 32)->nullable();
            $table->string('identity_document_number', 64)->nullable();
            $table->date('identity_document_expires_at')->nullable();

            // Étapes 3-11 — une colonne JSON par étape, remplie dans les sous-projets suivants
            $table->json('promoters_data')->nullable();
            $table->json('project_data')->nullable();
            $table->json('market_data')->nullable();
            $table->json('historical_financials_data')->nullable();
            $table->json('forecast_data')->nullable();
            $table->json('financing_plan_data')->nullable();
            $table->json('collateral_data')->nullable();
            $table->json('documents_data')->nullable();
            $table->json('scoring_data')->nullable();
            $table->json('review_data')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financing_dossiers');
    }
};
