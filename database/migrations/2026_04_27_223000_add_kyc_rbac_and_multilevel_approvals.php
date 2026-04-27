<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role_key', 50)->nullable()->after('is_accountant');
            $table->json('module_permissions')->nullable()->after('role_key');
            $table->string('kyc_status', 20)->default('pending')->after('module_permissions');
            $table->timestamp('kyc_submitted_at')->nullable()->after('kyc_status');
            $table->timestamp('kyc_validated_at')->nullable()->after('kyc_submitted_at');
            $table->foreignId('kyc_validated_by_user_id')->nullable()->after('kyc_validated_at')->constrained('users')->nullOnDelete();
            $table->text('kyc_rejection_reason')->nullable()->after('kyc_validated_by_user_id');
            $table->index(['kyc_status', 'kyc_submitted_at']);
            $table->index(['role_key']);
        });

        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('stored_path');
            $table->string('original_name')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'submitted_at']);
        });

        Schema::table('admin_approval_requests', function (Blueprint $table) {
            $table->unsignedSmallInteger('required_approvals')->default(1)->after('status');
            $table->unsignedSmallInteger('current_approvals')->default(0)->after('required_approvals');
        });

        Schema::create('admin_approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_approval_request_id')->constrained('admin_approval_requests')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 20);
            $table->text('note')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
            $table->unique(['admin_approval_request_id', 'admin_user_id'], 'approval_actor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_approval_actions');

        Schema::table('admin_approval_requests', function (Blueprint $table) {
            $table->dropColumn(['required_approvals', 'current_approvals']);
        });

        Schema::dropIfExists('kyc_documents');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kyc_validated_by_user_id');
            $table->dropColumn([
                'role_key',
                'module_permissions',
                'kyc_status',
                'kyc_submitted_at',
                'kyc_validated_at',
                'kyc_rejection_reason',
            ]);
        });
    }
};
