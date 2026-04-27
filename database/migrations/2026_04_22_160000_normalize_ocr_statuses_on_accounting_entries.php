<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounting_entries')
            ->where('ocr_status', 'mismatched')
            ->update(['ocr_status' => 'mismatch']);

        DB::table('accounting_entries')
            ->whereNull('ocr_status')
            ->update(['ocr_status' => 'pending']);
    }

    public function down(): void
    {
        DB::table('accounting_entries')
            ->where('ocr_status', 'mismatch')
            ->update(['ocr_status' => 'mismatched']);
    }
};
