<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_analyst_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('analyst_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('note');
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['analyst_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_analyst_notes');
    }
};
