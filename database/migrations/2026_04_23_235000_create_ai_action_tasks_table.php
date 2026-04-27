<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_action_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 220);
            $table->text('description')->nullable();
            $table->string('source', 64)->default('ops_live');
            $table->string('priority', 24)->default('medium'); // low|medium|high
            $table->string('status', 24)->default('todo'); // todo|in_progress|done
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_tasks');
    }
};

