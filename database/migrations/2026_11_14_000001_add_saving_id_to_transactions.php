<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Menautkan transaksi simpanan ke rekeningnya, dasar untuk rekening koran.
return new class extends Migration {
    public function up(): void {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('saving_id')->nullable()->after('member_id')->constrained()->nullOnDelete();
        });

        // Transaksi simpanan lama: rekeningnya dikenali dari akun simpanan di jurnal (31101/31102/20201) dan anggotanya.
        $types = ['31101' => 'Pokok', '31102' => 'Wajib', '20201' => 'Sukarela'];
        $rows = DB::table('transactions')->where('category', 'Simpanan')->whereNull('saving_id')->whereNotNull('member_id')->get();
        foreach ($rows as $t) {
            $code = DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_entries.reference', $t->trx_no)
                ->whereIn('journal_lines.account_code', array_keys($types))
                ->value('journal_lines.account_code');
            $savingId = $code ? DB::table('savings')->where('member_id', $t->member_id)->where('type', $types[$code])->value('id') : null;
            if ($savingId) {
                DB::table('transactions')->where('id', $t->id)->update(['saving_id' => $savingId]);
            }
        }
    }

    public function down(): void {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('saving_id');
        });
    }
};
