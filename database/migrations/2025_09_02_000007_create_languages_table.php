<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 255)->unique()->comment('언어 키 (영문 기본값)');
            $table->string('ko', 500)->nullable()->comment('한국어');
            $table->string('tw', 500)->nullable()->comment('중국어 번체 (대만)');
            $table->text('location')->nullable()->comment('사용 위치 (콤마로 구분된 컨텍스트 목록)');
            
            $table->index(['key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
