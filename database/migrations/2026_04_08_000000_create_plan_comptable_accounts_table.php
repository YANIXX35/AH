<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_comptable_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('prefix', 3);
            $table->string('label');
            $table->string('category')->default('other');
            $table->string('subtype')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_comptable_accounts');
    }
};
