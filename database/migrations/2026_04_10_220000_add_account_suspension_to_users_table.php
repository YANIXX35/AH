<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suspension manuelle ou automatique (ex. abonnement non honoré après échéance).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('account_suspended')->default(false)->after('is_platform_admin');
            $table->timestamp('suspended_at')->nullable()->after('account_suspended');
            $table->text('suspended_reason')->nullable()->after('suspended_at');
            $table->boolean('auto_suspended_for_payment')->default(false)->after('suspended_reason');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'account_suspended',
                'suspended_at',
                'suspended_reason',
                'auto_suspended_for_payment',
            ]);
        });
    }
};
