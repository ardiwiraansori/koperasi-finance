<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Siapa yang mengajukan, menyetujui, dan mencairkan sebuah pinjaman. Kosong untuk pinjaman lama atau akun yang sudah dihapus.
return new class extends Migration {
    // Tanpa pembungkus transaksi: di dalam transaksi PostgreSQL, perintah pertama yang gagal membuat semua perintah sesudahnya
    // dijawab "current transaction is aborted", sehingga galat aslinya tidak terlihat di log (kejadian di Render + Neon).
    // Dijalankan satu per satu dan boleh diulang: kolom yang sudah ada dilewati.
    public $withinTransaction = false;

    public function up(): void {
        foreach (['applied_by', 'approved_by', 'disbursed_by'] as $column) {
            if (! Schema::hasColumn('loans', $column)) {
                Schema::table('loans', fn (Blueprint $table) => $table->foreignId($column)->nullable()->constrained('users')->nullOnDelete());
            }
        }
    }

    public function down(): void {
        foreach (['applied_by', 'approved_by', 'disbursed_by'] as $column) {
            if (Schema::hasColumn('loans', $column)) {
                Schema::table('loans', fn (Blueprint $table) => $table->dropConstrainedForeignId($column));
            }
        }
    }
};
