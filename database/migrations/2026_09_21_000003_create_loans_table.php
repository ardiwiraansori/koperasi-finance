<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('loan_no')->unique();
            $table->string('product')->default('Pinjaman Reguler');
            $table->decimal('principal', 18, 2);
            $table->decimal('interest_rate', 6, 2)->default(12);
            $table->unsignedSmallInteger('term_months');
            $table->decimal('installment', 18, 2)->default(0);
            $table->decimal('outstanding', 18, 2)->default(0);
            $table->string('status')->default('Menunggu Approval');
            $table->date('applied_at');
            $table->date('next_due_at')->nullable();
            $table->timestamps();
            $table->index(['status','next_due_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('loans'); }
};
