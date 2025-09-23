<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique()->comment('로그인 아이디');
            $table->string('password')->nullable();
            $table->string('title', 30)->nullable()->comment('직책 or 경칭(Mr., Mrs. 등)');
            $table->string('name', 100)->comment('이름');
            $table->string('email', 100)->unique()->nullable();
            $table->unsignedTinyInteger('level')->default(1)->comment('회원 등급');
            $table->boolean('active')->default(true)->comment('계정 활성화 상태');
            $table->string('profile_photo', 255)->nullable()->comment('프로필 사진 경로');
            $table->string('phone', 20)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
