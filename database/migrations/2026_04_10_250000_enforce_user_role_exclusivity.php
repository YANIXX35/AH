<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Un seul rôle métier : administrateur plateforme prioritaire, sinon comptable.
        DB::table('users')->where('is_platform_admin', true)->update(['is_accountant' => false]);
        DB::table('users')->where('is_accountant', true)->update(['is_platform_admin' => false]);
    }

    public function down(): void
    {
        //
    }
};
