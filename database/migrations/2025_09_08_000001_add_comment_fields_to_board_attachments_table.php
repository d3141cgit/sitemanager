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
        Schema::table('board_attachments', function (Blueprint $table) {
            // 댓글 ID 필드 추가 (nullable)
            $table->unsignedBigInteger('comment_id')->nullable()->after('post_id');
            
            // 첨부파일 타입 필드 추가 (post 또는 comment)
            $table->enum('attachment_type', ['post', 'comment'])->default('post')->after('board_slug');
            
            // 인덱스 추가
            $table->index(['comment_id', 'board_slug'], 'board_attachments_comment_board_index');
            $table->index('attachment_type', 'board_attachments_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('board_attachments', function (Blueprint $table) {
            // 인덱스 제거
            $table->dropIndex('board_attachments_comment_board_index');
            $table->dropIndex('board_attachments_type_index');
            
            // 필드 제거
            $table->dropColumn(['comment_id', 'attachment_type']);
        });
    }
};
