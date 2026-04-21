<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Exercice et périodes comptables
            $table->date('fiscal_year_end_date')->nullable();
            $table->unsignedTinyInteger('fiscal_year_duration_months')->nullable();
            $table->date('accounting_period_from')->nullable();
            $table->date('accounting_period_to')->nullable();
            $table->date('accounts_effective_closing_date')->nullable();
            $table->date('previous_fiscal_year_end')->nullable();
            $table->unsignedTinyInteger('previous_fiscal_year_duration_months')->nullable();

            // Identifiants administratifs (complément RCCM existant)
            $table->string('company_directory_number', 128)->nullable();
            $table->string('social_security_number', 128)->nullable();
            $table->string('importer_code', 128)->nullable();
            $table->string('primary_activity_code', 32)->nullable();

            // Désignation / coordonnées étendues (FIRD)
            $table->string('company_designation', 512)->nullable();
            $table->string('company_fax', 64)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('po_box', 64)->nullable();
            $table->text('full_geographic_address')->nullable();

            // Activité
            $table->text('main_activity_description')->nullable();
            $table->decimal('production_capacity_utilization_pct', 5, 2)->nullable();

            // Contact complémentaire
            $table->string('contact_person_name', 255)->nullable();
            $table->text('contact_person_address')->nullable();
            $table->string('contact_person_title', 255)->nullable();

            // Expert-comptable / commissaires
            $table->string('accountant_type', 32)->nullable();
            $table->string('accountant_name', 512)->nullable();
            $table->text('accountant_address')->nullable();
            $table->string('accountant_phone', 64)->nullable();
            $table->json('company_auditors')->nullable();

            // Conformité états financiers
            $table->string('certified_financial_statements', 32)->nullable();
            $table->string('approved_by_general_assembly', 32)->nullable();

            // Signataire
            $table->string('financial_statements_signatory_name', 255)->nullable();
            $table->string('signatory_qualification', 255)->nullable();
            $table->date('financial_statements_signature_date')->nullable();

            // Domiciliations bancaires (jusqu’à 8 lignes en JSON)
            $table->json('company_bank_accounts')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'fiscal_year_end_date',
                'fiscal_year_duration_months',
                'accounting_period_from',
                'accounting_period_to',
                'accounts_effective_closing_date',
                'previous_fiscal_year_end',
                'previous_fiscal_year_duration_months',
                'company_directory_number',
                'social_security_number',
                'importer_code',
                'primary_activity_code',
                'company_designation',
                'company_fax',
                'postal_code',
                'po_box',
                'full_geographic_address',
                'main_activity_description',
                'production_capacity_utilization_pct',
                'contact_person_name',
                'contact_person_address',
                'contact_person_title',
                'accountant_type',
                'accountant_name',
                'accountant_address',
                'accountant_phone',
                'company_auditors',
                'certified_financial_statements',
                'approved_by_general_assembly',
                'financial_statements_signatory_name',
                'signatory_qualification',
                'financial_statements_signature_date',
                'company_bank_accounts',
            ]);
        });
    }
};
