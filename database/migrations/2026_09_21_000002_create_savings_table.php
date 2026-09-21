<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('savings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('account_no')->unique();
            $table->string('type');
            $table->decimal('balance', 18, 2)->default(0);
            $table->timestamps();
            $table->index(['member_id','type']);
        });
    }
    public function down(): void { Schema::dropIfExists('savings'); }
};
