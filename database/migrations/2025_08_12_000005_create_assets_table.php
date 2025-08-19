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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('original')->comment('원본 파일 경로');
            $table->string('copied')->unique()->comment('복사된 파일명');
            $table->string('hash', 32)->comment('파일 해시');
            $table->string('ext', 10)->comment('파일 확장자');
            $table->integer('mtime')->comment('파일 수정 시간');
            $table->integer('size')->comment('파일 크기');
            $table->timestamps();

            $table->index(['original', 'mtime']);
            $table->index('hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
