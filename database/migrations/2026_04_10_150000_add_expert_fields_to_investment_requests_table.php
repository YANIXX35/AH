<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Champs attendus pour un dossier d’investissement sous angle expert-comptable.
     */
    public function up(): void
    {
        Schema::table('investment_requests', function (Blueprint $table) {
            $table->string('legal_representative', 255)->nullable()->after('purpose');
            $table->date('fiscal_closing_at')->nullable()->after('legal_representative');
            $table->decimal('revenue_n1', 15, 2)->nullable()->after('fiscal_closing_at');
            $table->decimal('equity_n1', 15, 2)->nullable()->after('revenue_n1');
            $table->text('attachments_commitment')->nullable()->after('equity_n1');
            $table->boolean('certifies_accuracy')->default(false)->after('attachments_commitment');
        });
    }

    public function down(): void
    {
        Schema::table('investment_requests', function (Blueprint $table) {
            $table->dropColumn([
                'legal_representative',
                'fiscal_closing_at',
                'revenue_n1',
                'equity_n1',
                'attachments_commitment',
                'certifies_accuracy',
            ]);
        });
    }
};
