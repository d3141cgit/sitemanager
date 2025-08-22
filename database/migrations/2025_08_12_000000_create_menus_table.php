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
        Schema::create('menus', function (Blueprint $table) {
            $table->id()->comment('PK');
            $table->unsignedTinyInteger('section')->default(1)->comment('메뉴 섹션 (1:main, 2:member, 3:about)');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('상위 메뉴 id');
            $table->string('title', 100)->comment('메뉴명');
            $table->text('description')->nullable(); // 설명
            $table->string('type', 30)->default('route')->comment('메뉴 타입: route, url, text');
            $table->string('target', 255)->nullable()->comment('라우트명 또는 URL');
            $table->boolean('hidden')->default(false)->comment('메뉴 숨김 여부');
            $table->unsignedTinyInteger('permission')->default(1)->comment('기본 권한 (bitmask)');
            $table->json('images')->nullable()->comment('이미지 정보 (thumbnail, seo, header 등)');
            
            // Nested Set Model 필드 (섹션별로 독립적)
            $table->unsignedInteger('_lft')->default(0)->index()->comment('Nested Set Left');
            $table->unsignedInteger('_rgt')->default(0)->index()->comment('Nested Set Right');
            $table->unsignedInteger('depth')->default(0)->comment('계층 깊이');
            
            $table->timestamps();
            
            // 인덱스 추가
            $table->index(['section']);
            $table->index(['parent_id']);
            $table->index(['hidden']);
            $table->index(['section', '_lft']); // 섹션별 정렬을 위한 복합 인덱스
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
