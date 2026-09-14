<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_document_erpnext_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kyc_document_id')->unique()->constrained('kyc_documents')->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('erpnext_file_name')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_document_erpnext_syncs');
    }
};
