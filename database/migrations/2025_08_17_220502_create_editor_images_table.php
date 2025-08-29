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
        Schema::create('editor_images', function (Blueprint $table) {
            $table->id();
            $table->string('original_name', 255)->comment('원본 파일명');
            $table->string('filename', 255)->unique()->comment('저장된 파일명');
            $table->string('path', 500)->comment('파일 경로');
            $table->unsignedBigInteger('size')->comment('파일 크기 (bytes)');
            $table->string('mime_type', 100)->comment('MIME 타입');
            $table->unsignedBigInteger('uploaded_by')->nullable()->comment('업로드한 사용자 ID');
            
            // 참조 식별자 필드
            $table->string('reference_type', 50)->nullable()->comment('참조 타입 (board, page, etc.)');
            $table->string('reference_slug', 100)->nullable()->comment('참조 슬러그 (board slug, page slug, etc.)');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('참조 ID (post_id, page_id, etc.)');
            $table->boolean('is_used')->default(false)->comment('사용 여부');
            
            $table->timestamps();
            
            // 인덱스
            $table->index('uploaded_by');
            $table->index('created_at');
            $table->index(['uploaded_by', 'created_at']);
            $table->index(['reference_type', 'reference_slug', 'reference_id']);
            $table->index('is_used');
            
            // 외래키 (사용자 테이블이 있는 경우)
            // $table->foreign('uploaded_by')->references('id')->on('members')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('editor_images');
    }
};
