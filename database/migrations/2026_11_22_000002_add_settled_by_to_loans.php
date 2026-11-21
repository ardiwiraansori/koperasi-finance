<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Top-up: pinjaman lama yang dilunasi dari pencairan pinjaman baru menunjuk ke pinjaman baru itu.
return new class extends Migration {
    public function up(): void {
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('settled_by_loan_id')->nullable()->constrained('loans')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settled_by_loan_id');
        });
    }
};
