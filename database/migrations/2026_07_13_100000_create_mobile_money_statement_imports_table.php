<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_money_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operator', 30);
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('treasury_account_code', 20)->nullable();
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_duplicate')->default(0);
            $table->unsignedInteger('rows_matched')->default(0);
            $table->string('status', 20)->default('processing');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'operator']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_money_statement_imports');
    }
};
