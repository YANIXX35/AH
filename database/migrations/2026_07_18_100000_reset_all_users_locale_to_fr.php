<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->update(['locale' => 'fr']);
    }

    public function down(): void
    {
        // Pas de retour arrière pertinent pour une remise à zéro de préférence utilisateur.
    }
};
