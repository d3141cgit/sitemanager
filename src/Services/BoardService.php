<?php

namespace SiteManager\Services;

use SiteManager\Models\Board;
use SiteManager\Models\Menu;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BoardService
{
    /**
     * 새 게시판 생성
     */
    public function createBoard(array $data): Board
    {
        try {
            // 먼저 동적 테이블 생성 (DDL 작업)
            $this->createBoardTables($data['slug']);
            
            // 트랜잭션 시작하여 게시판 레코드 생성
            return DB::transaction(function () use ($data) {
                return Board::create([
                    'menu_id' => $data['menu_id'] ?? null,
                    'slug' => $data['slug'],
                    'name' => $data['name'],
                    'skin' => $data['skin'] ?? 'default',
                    'posts_per_page' => $data['posts_per_page'] ?? 15,
                    'categories' => $data['categories'] ?? [],
                    'settings' => $data['settings'] ?? [],
                ]);
            });
        } catch (\Exception $e) {
            // 테이블 생성 후 게시판 레코드 생성에 실패한 경우 테이블 정리
            $this->dropBoardTables($data['slug']);
            throw $e;
        }
    }

    /**
     * 게시판 테이블 생성
     */
    public function createBoardTables(string $slug): void
    {
        $postsTable = "board_posts_{$slug}";
        $commentsTable = "board_comments_{$slug}";

        // 게시글 테이블 생성
        if (!Schema::hasTable($postsTable)) {
            Schema::create($postsTable, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('board_id');
                $table->unsignedBigInteger('member_id')->nullable();
                $table->string('author_name', 100)->nullable();
                $table->string('title', 500);
                $table->longText('content')->nullable();
                $table->enum('content_type', ['html', 'markdown', 'text'])->default('html');
                $table->string('excerpt', 1000)->nullable();
                $table->string('slug', 200)->nullable();
                $table->string('category', 100)->nullable();
                $table->json('tags')->nullable();
                $table->enum('status', ['draft', 'published', 'private'])->default('published');
                $table->boolean('is_notice')->default(false);
                $table->boolean('is_featured')->default(false);
                $table->unsignedInteger('view_count')->default(0);
                $table->unsignedInteger('comment_count')->default(0);
                $table->unsignedInteger('file_count')->default(0);
                $table->json('meta')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('board_id')->references('id')->on('boards')->onDelete('cascade');
                $table->foreign('member_id')->references('id')->on('members')->onDelete('set null');
                
                $table->index(['board_id', 'status']);
                $table->index('created_at');
                $table->index('slug');
                $table->index('category');
                $table->fullText(['title', 'content']);
            });
        }

        // 댓글 테이블 생성
        if (!Schema::hasTable($commentsTable)) {
            Schema::create($commentsTable, function (Blueprint $table) use ($postsTable, $commentsTable) {
                $table->id();
                $table->unsignedBigInteger('post_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->unsignedBigInteger('member_id')->nullable();
                $table->string('author_name', 100)->nullable();
                $table->text('content');
                $table->enum('status', ['approved', 'pending', 'spam'])->default('approved');
                $table->unsignedInteger('file_count')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('post_id')->references('id')->on($postsTable)->onDelete('cascade');
                $table->foreign('parent_id')->references('id')->on($commentsTable)->onDelete('cascade');
                $table->foreign('member_id')->references('id')->on('members')->onDelete('set null');
                
                $table->index('post_id');
                $table->index('parent_id');
            });
        }
    }

    /**
     * 게시판 삭제
     */
    public function deleteBoard(Board $board): void
    {
        // 테이블 삭제
        $this->dropBoardTables($board->slug);
        
        // 파일 정리
        $this->cleanupBoardFiles($board->slug);
        
        // 게시판 설정 삭제
        $board->delete();
    }

    /**
     * 게시판 테이블 삭제
     */
    public function dropBoardTables(string $slug): void
    {
        $postsTable = "board_posts_{$slug}";
        $commentsTable = "board_comments_{$slug}";

        if (Schema::hasTable($commentsTable)) {
            Schema::dropIfExists($commentsTable);
        }
        
        if (Schema::hasTable($postsTable)) {
            Schema::dropIfExists($postsTable);
        }
    }

    /**
     * 게시판 파일 정리
     */
    private function cleanupBoardFiles(string $slug): void
    {
        // board_files 테이블이 존재하는 경우에만 삭제
        if (Schema::hasTable('board_files')) {
            DB::table('board_files')
                ->where('board_slug', $slug)
                ->delete();
        }
        
        // 실제 파일들도 삭제 (구현 필요)
        // FileUploadService 등을 활용하여 S3/로컬 파일 정리
    }

    /**
     * 메뉴 ID로 게시판 찾기
     */
    public function getBoardByMenuId(int $menuId): ?Board
    {
        return Board::where('menu_id', $menuId)->first();
    }

    /**
     * 슬러그로 게시판 찾기
     */
    public function getBoardBySlug(string $slug): ?Board
    {
        return Board::where('slug', $slug)->first();
    }

    /**
     * 게시판 설정 업데이트
     */
    public function updateBoard(Board $board, array $data): Board
    {
        $board->update([
            'name' => $data['name'] ?? $board->name,
            'skin' => $data['skin'] ?? $board->skin,
            'posts_per_page' => $data['posts_per_page'] ?? $board->posts_per_page,
            'categories' => $data['categories'] ?? $board->categories,
            'settings' => array_merge($board->settings ?? [], $data['settings'] ?? []),
        ]);

        return $board->fresh();
    }

    /**
     * 게시판 목록 조회
     */
    public function getAllBoards()
    {
        return Board::with('menu')->orderBy('created_at')->get();
    }

    /**
     * 게시판 테이블명 변경
     */
    public function renameBoardTables(string $oldSlug, string $newSlug): void
    {
        $oldPostsTable = "board_posts_{$oldSlug}";
        $newPostsTable = "board_posts_{$newSlug}";
        $oldCommentsTable = "board_comments_{$oldSlug}";
        $newCommentsTable = "board_comments_{$newSlug}";

        // 테이블 이름 변경
        Schema::rename($oldPostsTable, $newPostsTable);
        Schema::rename($oldCommentsTable, $newCommentsTable);

        // 댓글 테이블의 외래키 제약조건 업데이트
        Schema::table($newCommentsTable, function (Blueprint $table) use ($newPostsTable, $newCommentsTable) {
            $table->dropForeign(['post_id']);
            $table->dropForeign(['parent_id']);
            
            $table->foreign('post_id')->references('id')->on($newPostsTable)->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on($newCommentsTable)->onDelete('cascade');
        });
    }

    /**
     * 게시판 테이블 재생성
     */
    public function regenerateBoardTables(string $slug): void
    {
        // 기존 테이블 삭제
        $this->dropBoardTables($slug);
        
        // 새로 생성
        $this->createBoardTables($slug);
    }
}
