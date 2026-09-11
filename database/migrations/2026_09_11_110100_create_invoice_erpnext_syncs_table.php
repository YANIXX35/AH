<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_erpnext_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained('invoices')->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('erpnext_invoice_name')->nullable();
            $table->string('erpnext_customer_name')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_erpnext_syncs');
    }
};
