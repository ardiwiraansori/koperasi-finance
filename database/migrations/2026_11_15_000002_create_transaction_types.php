<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Jenis transaksi kasir (bahasa sehari-hari) yang terhubung ke akun jurnal. Kasir memilih jenis, bukan akun.
return new class extends Migration {
    public function up(): void {
        Schema::create('transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('category');              // Penerimaan | Pengeluaran
            $table->string('name');
            $table->string('account_code', 30);      // akun lawan kas (pendapatan / beban)
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['category', 'name']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('transaction_type_id')->nullable()->after('loan_id')->constrained('transaction_types')->nullOnDelete();
        });

        // Bawaan: sama dengan pilihan akun di Kasir sebelumnya, tetapi dengan nama sehari-hari.
        $now = now();
        $defaults = [
            ['Penerimaan', 'Biaya administrasi dan provisi', '41201'], ['Penerimaan', 'Denda keterlambatan', '41301'], ['Penerimaan', 'Penerimaan lain-lain', '49101'],
            ['Pengeluaran', 'Biaya operasional kantor', '51101'], ['Pengeluaran', 'Gaji dan honor', '51201'], ['Pengeluaran', 'Listrik, air, dan telepon', '51301'],
            ['Pengeluaran', 'ATK dan perlengkapan', '51401'], ['Pengeluaran', 'Pengeluaran lain-lain', '59101'],
        ];
        foreach ($defaults as $i => [$category, $name, $account]) {
            DB::table('transaction_types')->insert(['category' => $category, 'name' => $name, 'account_code' => $account, 'sort' => $i, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_type_id');
        });
        Schema::dropIfExists('transaction_types');
    }
};
