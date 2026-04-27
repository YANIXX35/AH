<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('is_staff_reply');
            $table->timestamp('read_at')->nullable()->after('delivered_at');
            $table->index(['support_ticket_id', 'delivered_at']);
            $table->index(['support_ticket_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropColumn(['delivered_at', 'read_at']);
        });
    }
};
