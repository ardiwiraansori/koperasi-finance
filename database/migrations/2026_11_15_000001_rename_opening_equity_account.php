<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 39101 dipakai untuk saldo awal kas dan saldo awal simpanan, jadi namanya dibuat umum.
return new class extends Migration {
    public function up(): void {
        DB::table('journal_lines')->where('account_code', '39101')->update(['account_name' => 'Ekuitas Saldo Awal']);
    }
    public function down(): void {
        DB::table('journal_lines')->where('account_code', '39101')->update(['account_name' => 'Ekuitas Saldo Awal Kas']);
    }
};
