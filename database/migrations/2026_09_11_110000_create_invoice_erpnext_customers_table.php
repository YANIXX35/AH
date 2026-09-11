<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_erpnext_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('dedup_key');
            $table->string('erpnext_customer_name');
            $table->timestamps();

            $table->unique(['user_id', 'dedup_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_erpnext_customers');
    }
};
