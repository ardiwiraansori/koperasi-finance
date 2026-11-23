<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Top-up: pinjaman lama yang dilunasi dari pencairan pinjaman baru menunjuk ke pinjaman baru itu.
return new class extends Migration {
    // Tanpa transaksi dan boleh diulang, sama alasannya dengan add_actors_to_loans.
    public $withinTransaction = false;

    public function up(): void {
        if (! Schema::hasColumn('loans', 'settled_by_loan_id')) {
            Schema::table('loans', fn (Blueprint $table) => $table->foreignId('settled_by_loan_id')->nullable()->constrained('loans')->nullOnDelete());
        }
    }

    public function down(): void {
        if (Schema::hasColumn('loans', 'settled_by_loan_id')) {
            Schema::table('loans', fn (Blueprint $table) => $table->dropConstrainedForeignId('settled_by_loan_id'));
        }
    }
};
