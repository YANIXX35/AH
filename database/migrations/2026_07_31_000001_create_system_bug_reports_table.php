<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_bug_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dashboard', 50)->default('Inconnu');
            $table->text('page_url');
            $table->string('route_name')->nullable();
            $table->string('error_class');
            $table->text('message');
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->mediumText('stack_trace')->nullable();
            $table->string('severity', 20)->default('HIGH');
            $table->string('status', 20)->default('OPEN');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->index('dashboard');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_bug_reports');
    }
};
