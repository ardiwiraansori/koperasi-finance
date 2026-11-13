<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Data identitas dan ahli waris pada formulir keanggotaan. Semua opsional agar kasir tidak macet bila KTP belum dibawa.
return new class extends Migration {
    public function up(): void {
        Schema::table('members', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->unique()->after('name');
            $table->string('gender', 1)->nullable()->after('nik');
            $table->string('birth_place', 60)->nullable()->after('gender');
            $table->date('birth_date')->nullable()->after('birth_place');
            $table->string('marital_status', 20)->nullable()->after('birth_date');
            $table->string('occupation', 80)->nullable()->after('marital_status');
            $table->string('heir_name', 100)->nullable()->after('address');
            $table->string('heir_relation', 30)->nullable()->after('heir_name');
            $table->string('heir_phone', 30)->nullable()->after('heir_relation');
        });
    }

    public function down(): void {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['nik']);
            $table->dropColumn(['nik', 'gender', 'birth_place', 'birth_date', 'marital_status', 'occupation', 'heir_name', 'heir_relation', 'heir_phone']);
        });
    }
};
