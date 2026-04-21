<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->default('Africa/Abidjan')->after('avatar');
            $table->string('locale', 10)->default('fr')->after('timezone');
            $table->string('currency', 10)->default('XOF')->after('locale');
            $table->boolean('email_notifications')->default(true)->after('currency');
            $table->boolean('weekly_digest')->default(false)->after('email_notifications');
            $table->boolean('marketing_emails')->default(false)->after('weekly_digest');
            $table->boolean('two_factor_enabled')->default(false)->after('marketing_emails');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'timezone',
                'locale',
                'currency',
                'email_notifications',
                'weekly_digest',
                'marketing_emails',
                'two_factor_enabled',
            ]);
        });
    }
};
