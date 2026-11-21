<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Siapa yang mengajukan, menyetujui, dan mencairkan sebuah pinjaman. Kosong untuk pinjaman lama atau akun yang sudah dihapus.
return new class extends Migration {
    public function up(): void {
        Schema::table('loans', function (Blueprint $table) {
            foreach (['applied_by', 'approved_by', 'disbursed_by'] as $column) {
                $table->foreignId($column)->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void {
        Schema::table('loans', function (Blueprint $table) {
            foreach (['applied_by', 'approved_by', 'disbursed_by'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
        });
    }
};
