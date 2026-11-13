<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Akun portal anggota: satu anggota paling banyak satu akun.
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->foreignId('member_id')->nullable()->unique()->constrained()->cascadeOnDelete());
    }
    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('member_id'));
    }
};
