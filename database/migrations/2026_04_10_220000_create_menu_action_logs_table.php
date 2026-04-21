<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('route_name', 191);
            $table->string('http_method', 16);
            $table->string('path', 512);
            $table->unsignedSmallInteger('status_code');
            $table->string('ip', 45)->nullable();
            $table->boolean('was_platform_admin')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_action_logs');
    }
};
