<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50)->index();
            $table->string('provider_reference', 120)->nullable()->index();
            $table->string('status', 50)->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('country', 2);
            $table->string('correspondent', 80);
            $table->string('payer_msisdn', 40);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
