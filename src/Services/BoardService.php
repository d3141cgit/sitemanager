<?php

namespace SiteManager\Services;

use SiteManager\Models\Board;
use SiteManager\Models\BoardPost;
use SiteManager\Models\BoardComment;
use SiteManager\Models\BoardAttachment;
use SiteManager\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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

    /**
     * 게시물 목록 조회 (필터링 포함)
     */
    public function getFilteredPosts(Board $board, Request $request)
    {
        $postModelClass = BoardPost::forBoard($board->slug);
        
        $query = $postModelClass::with('member')
            ->published()
            ->orderBy('is_notice', 'desc')
            ->orderBy('created_at', 'desc');

        // 카테고리 필터링
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        // 검색어 필터링
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('author_name', 'like', "%{$search}%");
            });
        }

        return $query->paginate($board->getSetting('posts_per_page', 20));
    }

    /**
     * 공지사항 목록 조회
     */
    public function getNotices(Board $board, int $limit = 5)
    {
        $postModelClass = BoardPost::forBoard($board->slug);
        
        return $postModelClass::with('member')
            ->notices()
            ->published()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 게시물 상세 조회
     */
    public function getPost(Board $board, $id)
    {
        $postModelClass = BoardPost::forBoard($board->slug);
        
        if (is_numeric($id)) {
            return $postModelClass::with('member')->findOrFail($id);
        }
        
        return $postModelClass::with('member')->where('slug', $id)->firstOrFail();
    }

    /**
     * 게시물 댓글 조회
     */
    public function getPostComments(Board $board, $postId)
    {
        if (!$board->getSetting('allow_comments', true)) {
            return null;
        }

        $commentModelClass = BoardComment::forBoard($board->slug);
        
        return $commentModelClass::with('member', 'children.member')
            ->topLevel()
            ->approved()
            ->forPost($postId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 게시물 첨부파일 조회
     */
    public function getPostAttachments(Board $board, $postId)
    {
        if (!$board->allowsFileUpload()) {
            return null;
        }

        return BoardAttachment::byPost($postId, $board->slug)
            ->ordered()
            ->get();
    }

    /**
     * 이전/다음 게시물 조회
     */
    public function getPrevNextPosts(Board $board, $post)
    {
        $postModelClass = BoardPost::forBoard($board->slug);
        
        $prevPost = $postModelClass::where('id', '<', $post->id)
            ->published()
            ->orderBy('id', 'desc')
            ->first();

        $nextPost = $postModelClass::where('id', '>', $post->id)
            ->published()
            ->orderBy('id', 'asc')
            ->first();

        return compact('prevPost', 'nextPost');
    }

    /**
     * 게시물 생성
     */
    public function createPost(Board $board, array $data): BoardPost
    {
        $postModelClass = BoardPost::forBoard($board->slug);

        $postData = [
            'board_id' => $board->id,
            'member_id' => Auth::id(),
            'author_name' => Auth::user()?->name,
            'title' => $data['title'],
            'content' => $data['content'],
            'content_type' => 'html',
            'category' => $data['category'] ?? null,
            'tags' => isset($data['tags']) && $data['tags'] ? explode(',', $data['tags']) : null,
            'status' => 'published',
            'is_notice' => $data['is_notice'] ?? false,
            'is_featured' => $data['is_featured'] ?? false,
            'published_at' => now(),
        ];

        $post = $postModelClass::create($postData);

        // SEO 슬러그 생성
        $post->slug = $post->generateSlug();
        $post->excerpt = $post->generateExcerpt();
        $post->save();

        return $post;
    }

    /**
     * 게시물 수정
     */
    public function updatePost(Board $board, $post, array $data): BoardPost
    {
        $post->update([
            'title' => $data['title'],
            'content' => $data['content'],
            'category' => $data['category'] ?? null,
            'tags' => isset($data['tags']) && $data['tags'] ? explode(',', $data['tags']) : null,
            'is_notice' => $data['is_notice'] ?? false,
            'is_featured' => $data['is_featured'] ?? false,
        ]);

        // 슬러그 재생성 (제목이 변경된 경우)
        if ($post->wasChanged('title')) {
            $post->slug = $post->generateSlug();
            $post->excerpt = $post->generateExcerpt();
            $post->save();
        }

        return $post;
    }

    /**
     * 게시물 삭제 권한 체크
     */
    public function canManagePost(Board $board, $post, $user = null): bool
    {
        $user = $user ?: Auth::user();
        
        if (!$user) {
            return false;
        }

        // 작성자 본인
        if ($post->member_id === $user->id) {
            return true;
        }
        
        // 게시판 관리 권한
        if ($board->menu_id && can('manage', $board)) {
            return true;
        }
        
        // 시스템 관리자
        if ($user->level >= config('member.admin_level', 200)) {
            return true;
        }

        return false;
    }

    /**
     * 조회수 증가 (중복 방지)
     */
    public function incrementViewCount($post, Request $request): void
    {
        $sessionKey = "viewed_post_{$post->getTable()}_{$post->id}";
        $now = now();
        $lastViewed = session($sessionKey);
        
        // 조회수 증가 조건: 처음 조회하거나 30분이 지난 경우
        if (!$lastViewed || $now->diffInMinutes($lastViewed) >= 30) {
            // 작성자 본인은 조회수 증가 안함
            if (Auth::check() && Auth::id() === $post->member_id) {
                session([$sessionKey => $now]);
                return;
            }
            
            $post->incrementViewCount();
            session([$sessionKey => $now]);
        }
    }

    /**
     * 게시글 삭제
     */
    public function deletePost(Board $board, $post): void
    {
        $post->delete();
    }

    /**
     * 첨부파일 삭제
     */
    public function deleteAttachment($attachment): void
    {
        $filePath = $attachment->file_path;
        
        // 파일이 존재하면 삭제
        if (Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }
        
        $attachment->delete();
    }
}
