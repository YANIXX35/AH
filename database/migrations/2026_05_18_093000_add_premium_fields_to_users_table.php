<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_premium')) {
                $table->boolean('is_premium')->default(false)->after('two_factor_enabled');
            }
            if (! Schema::hasColumn('users', 'premium_status')) {
                $table->string('premium_status', 32)->default('free')->after('is_premium');
            }
            if (! Schema::hasColumn('users', 'premium_trial_ends_at')) {
                $table->timestamp('premium_trial_ends_at')->nullable()->after('premium_status');
            }
            if (! Schema::hasColumn('users', 'premium_ends_at')) {
                $table->timestamp('premium_ends_at')->nullable()->after('premium_trial_ends_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = ['is_premium', 'premium_status', 'premium_trial_ends_at', 'premium_ends_at'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
