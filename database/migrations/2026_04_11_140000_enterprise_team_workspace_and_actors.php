<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprise_licenses', function (Blueprint $table) {
            $table->foreignId('primary_workspace_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('accounting_entries', function (Blueprint $table) {
            $table->foreignId('actor_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('accounting_documents', function (Blueprint $table) {
            $table->foreignId('actor_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->foreignId('actor_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('treasury_audit_logs', function (Blueprint $table) {
            $table->foreignId('actor_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('treasury_audit_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actor_user_id');
        });

        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actor_user_id');
        });

        Schema::table('accounting_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actor_user_id');
        });

        Schema::table('accounting_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actor_user_id');
        });

        Schema::table('enterprise_licenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('primary_workspace_user_id');
        });
    }
};
