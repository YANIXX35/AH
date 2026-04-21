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
        Schema::table('accounting_entries', function (Blueprint $table) {
            $table->string('ocr_status')->default('pending')->after('attachment_path'); // pending, verified, mismatched, failed
            $table->decimal('ocr_detected_amount', 14, 2)->nullable()->after('ocr_status');
            $table->timestamp('ocr_verified_at')->nullable()->after('ocr_detected_amount');
            $table->longText('ocr_text')->nullable()->after('ocr_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_entries', function (Blueprint $table) {
            $table->dropColumn(['ocr_status', 'ocr_detected_amount', 'ocr_verified_at', 'ocr_text']);
        });
    }
};
