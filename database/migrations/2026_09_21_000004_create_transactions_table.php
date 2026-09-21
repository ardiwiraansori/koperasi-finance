<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('trx_no')->unique();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category');
            $table->enum('direction', ['in','out']);
            $table->decimal('amount', 18, 2);
            $table->string('description');
            $table->dateTime('trx_date');
            $table->string('status')->default('Posted');
            $table->timestamps();
            $table->index(['trx_date','category']);
        });
    }
    public function down(): void { Schema::dropIfExists('transactions'); }
};
