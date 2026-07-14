<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_quality_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('pending'); // pending | validated | flagged
            // Trace la méthode ayant produit ce statut (ex. "provisional-reliability-v1"),
            // pour que le statut déjà enregistré reste interprétable si la méthode change
            // plus tard sans recalcul rétroactif — voir App\Domain\Accounting\QualityControlService.
            $table->string('method_version', 60)->nullable();
            $table->decimal('reliability_score_snapshot', 5, 2)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period_start', 'period_end'], 'aqr_user_period_unique');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_quality_reviews');
    }
};
