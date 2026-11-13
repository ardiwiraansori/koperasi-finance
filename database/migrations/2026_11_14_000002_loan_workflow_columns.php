<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Alur pinjaman: tanggal pencairan, catatan (alasan tolak), dan tautan transaksi -> pinjaman untuk riwayat angsuran.
return new class extends Migration {
    public function up(): void {
        Schema::table('loans', function (Blueprint $table) {
            $table->date('disbursed_at')->nullable()->after('applied_at');
            $table->string('note')->nullable()->after('next_due_at');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('loan_id')->nullable()->after('saving_id')->constrained()->nullOnDelete();
        });

        // Pinjaman yang belum dicairkan tidak punya sisa pokok.
        DB::table('loans')->whereIn('status', ['Menunggu Approval', 'Disetujui', 'Ditolak'])->update(['outstanding' => 0]);
        // Pinjaman berjalan/lunas lama: anggap dicairkan pada tanggal pengajuan.
        DB::table('loans')->whereIn('status', ['Aktif', 'Tunggakan', 'Lunas'])->whereNull('disbursed_at')->update(['disbursed_at' => DB::raw('applied_at')]);

        // Angsuran lama ditautkan ke pinjaman anggotanya bila anggota itu hanya punya satu pinjaman yang pernah dicairkan.
        foreach (DB::table('transactions')->where('category', 'Angsuran')->whereNull('loan_id')->whereNotNull('member_id')->get() as $t) {
            $ids = DB::table('loans')->where('member_id', $t->member_id)->whereNotNull('disbursed_at')->pluck('id');
            if ($ids->count() === 1) {
                DB::table('transactions')->where('id', $t->id)->update(['loan_id' => $ids[0]]);
            }
        }
    }

    public function down(): void {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_id');
        });
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['disbursed_at', 'note']);
        });
    }
};
