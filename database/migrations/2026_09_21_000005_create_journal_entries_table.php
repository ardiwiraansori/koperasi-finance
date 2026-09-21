<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('journal_no')->unique();
            $table->date('journal_date');
            $table->string('reference')->nullable();
            $table->string('description');
            $table->string('source')->default('AUTO JOURNAL');
            $table->string('status')->default('Posted');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('journal_entries'); }
};
