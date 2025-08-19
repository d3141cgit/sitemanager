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
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('menu_id')->nullable(); // 연결된 메뉴
            $table->string('slug', 50)->unique(); // URL용 슬러그
            $table->string('name', 100); // 게시판 이름
            $table->string('skin', 50)->default('default'); // 스킨
            $table->integer('posts_per_page')->default(15); // 페이지당 게시글 수
            $table->json('categories')->nullable(); // 카테고리 목록
            $table->json('settings')->nullable(); // 게시판 설정 (JSON) - use_categories, use_files, use_comments, use_tags 포함
            $table->enum('status', ['active', 'inactive'])->default('active'); // 상태
            $table->timestamps();
            
            $table->index('status');
            $table->index('slug');
            
            // 외래키 제약조건
            $table->foreign('menu_id')->references('id')->on('menus')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boards');
    }
};
