<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('accounting_documents')
            ->select('document_hash')
            ->whereNotNull('document_hash')
            ->groupBy('document_hash')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('document_hash');

        foreach ($duplicates as $hash) {
            $rows = DB::table('accounting_documents')
                ->select('id', 'user_id')
                ->where('document_hash', $hash)
                ->orderBy('id')
                ->get();

            foreach ($rows as $index => $row) {
                if ($index === 0) {
                    continue;
                }

                DB::table('accounting_documents')
                    ->where('id', $row->id)
                    ->update([
                        'document_hash' => $hash.'-'.$row->user_id.'-'.Str::lower(Str::random(6)),
                    ]);
            }
        }

        Schema::table('accounting_documents', function (Blueprint $table) {
            $table->dropUnique('accounting_documents_document_hash_unique');
            $table->unique(['user_id', 'document_hash'], 'accounting_documents_user_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_documents', function (Blueprint $table) {
            $table->dropUnique('accounting_documents_user_hash_unique');
            $table->unique('document_hash');
        });
    }
};
