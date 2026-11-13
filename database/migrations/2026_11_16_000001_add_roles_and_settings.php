<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Peran pengguna (admin, kasir, akuntan) dan pengaturan koperasi (nama, ada/tidaknya akuntan).
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('kasir')->after('password'); // akun baru: hak paling kecil
        });
        DB::table('users')->update(['role' => 'admin']); // akun yang sudah ada sebelum fitur peran adalah pengelola

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('settings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
