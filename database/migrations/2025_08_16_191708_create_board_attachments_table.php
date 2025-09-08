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
        Schema::create('board_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('board_slug');
            $table->enum('attachment_type', ['post', 'comment'])->default('post'); // 첨부파일 타입
            $table->unsignedBigInteger('attachment_id'); // post_id 또는 comment_id 값
            $table->string('filename');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('file_extension', 10); // 파일 확장자 (예: jpg, pdf, docx)
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type'); // MIME 타입 (예: image/jpeg, application/pdf)
            $table->string('category')->nullable(); // 파일 카테고리/종류 (예: image, document, archive)
            $table->text('description')->nullable(); // 파일 설명
            $table->unsignedInteger('sort_order')->default(0); // 파일 정렬 순서
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();

            $table->index(['attachment_type', 'attachment_id', 'board_slug']);
            $table->index('board_slug');
            $table->index('category');
            $table->index('sort_order');
            $table->index('attachment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('board_attachments');
    }
};
