<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('validated_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('receipt_number')->unique();
            $table->unsignedInteger('amount');
            $table->unsignedInteger('balance_at_payment');
            $table->unsignedInteger('previously_paid_total');
            $table->text('note')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->index('commercial_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_payouts');
    }
};
