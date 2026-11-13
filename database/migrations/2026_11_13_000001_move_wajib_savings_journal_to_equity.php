<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Simpanan Wajib adalah ekuitas (31102), bukan kewajiban (20202). Memperbaiki jurnal data awal.
return new class extends Migration {
    public function up(): void {
        DB::table('journal_lines')->where('account_code', '20202')->update(['account_code' => '31102', 'account_name' => 'Simpanan Wajib']);
    }
    public function down(): void {
        DB::table('journal_lines')->where('account_code', '31102')->update(['account_code' => '20202', 'account_name' => 'Simpanan Wajib Anggota']);
    }
};
