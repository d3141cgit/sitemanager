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
                $table->id()->comment('게시글 ID');
                $table->unsignedBigInteger('board_id')->comment('게시판 ID');
                $table->unsignedBigInteger('member_id')->nullable()->comment('작성자 회원 ID');
                $table->string('author_name', 100)->nullable()->comment('작성자명 (비회원용)');
                $table->string('title', 500)->comment('게시글 제목');
                $table->longText('content')->nullable()->comment('게시글 내용');
                $table->enum('content_type', ['html', 'markdown', 'text'])->default('html')->comment('내용 형식');
                $table->string('excerpt', 1000)->nullable()->comment('요약 (SEO용)');
                $table->string('slug', 200)->nullable()->comment('URL 슬러그 (SEO용)');
                $table->string('category', 100)->nullable()->comment('카테고리');
                $table->json('tags')->nullable()->comment('태그 목록');
                $table->enum('status', ['draft', 'published', 'private'])->default('published')->comment('게시 상태');
                $table->string('options', 500)->nullable()->comment('게시글 옵션 (is_notice|show_image|no_indent 등, | 구분자)');
                $table->unsignedInteger('view_count')->default(0)->comment('조회수');
                $table->unsignedInteger('comment_count')->default(0)->comment('댓글 수');
                $table->unsignedInteger('file_count')->default(0)->comment('첨부파일 수');
                $table->timestamp('published_at')->nullable()->comment('게시 일시');
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
                $table->id()->comment('댓글 ID');
                $table->unsignedBigInteger('post_id')->comment('게시글 ID');
                $table->unsignedBigInteger('parent_id')->nullable()->comment('부모 댓글 ID (대댓글용)');
                $table->unsignedBigInteger('member_id')->nullable()->comment('작성자 회원 ID');
                $table->string('author_name', 100)->nullable()->comment('작성자명 (비회원용)');
                $table->text('content')->comment('댓글 내용');
                $table->enum('status', ['approved', 'pending', 'spam'])->default('approved')->comment('승인 상태');
                $table->unsignedInteger('file_count')->default(0)->comment('첨부파일 수');
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
            // ->orderByRaw("CASE WHEN options LIKE '%is_notice%' THEN 1 ELSE 0 END DESC")
            ->orderBy('published_at', 'desc');

        // 카테고리 필터링 (다중 카테고리 지원)
        if ($request->filled('categories')) {
            $categories = $request->input('categories');
            if (is_array($categories) && count($categories) > 0) {
                $query->where(function($q) use ($categories) {
                    foreach ($categories as $category) {
                        $q->orWhere('category', 'like', "%|{$category}|%");
                    }
                });
            }
        } elseif ($request->filled('category')) {
            // 기존 단일 카테고리 필터링 지원
            $category = $request->input('category');
            $query->where(function($q) use ($category) {
                // |category1|category2| 형식으로 저장된 카테고리에서 검색
                $q->where('category', 'like', "%|{$category}|%")
                  // 기존 단일 카테고리 형식도 지원
                  ->orWhere('category', $category);
            });
        }

        // 옵션 필터링
        if ($request->filled('options')) {
            $options = $request->input('options');
            if (is_array($options)) {
                foreach ($options as $option) {
                    if (!empty($option)) {
                        $query->where('options', 'like', "%{$option}%");
                    }
                }
            } elseif (is_string($options)) {
                $query->where('options', 'like', "%{$options}%");
            }
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
            ->where('options', 'like', '%is_notice%')
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
        
        $comments = $commentModelClass::with('member', 'children.member')
            ->topLevel()
            ->approved()
            ->forPost($postId)
            ->orderBy('created_at', 'desc')
            ->get();
            
        // 각 댓글에 권한 정보 추가
        $comments->each(function ($comment) use ($board) {
            $comment->permissions = $this->calculateCommentPermissions($board, $comment);
            
            // 자식 댓글들에도 권한 정보 추가
            if ($comment->children) {
                $comment->children->each(function ($child) use ($board) {
                    $child->permissions = $this->calculateCommentPermissions($board, $child);
                });
            }
        });
        
        return $comments;
    }
    
    /**
     * 댓글 권한 계산
     */
    private function calculateCommentPermissions(Board $board, $comment): array
    {
        $user = Auth::user();
        
        $canEdit = false;
        $canDelete = false;
        $canReply = false;
        
        if ($board->menu_id && $user) {
            // 본인 댓글인 경우 수정/삭제 가능 (member_id가 존재하고 일치하는 경우만)
            $isAuthor = $comment->member_id && $comment->member_id === $user->id;
            
            // 댓글 관리 권한
            $canManageComments = can('manageComments', $board);
            
            // 댓글 작성 권한 (답글용)
            $canWriteComments = can('writeComments', $board);
            
            // 수정 권한: 댓글 관리 권한 OR 작성자 본인
            $canEdit = $canManageComments || $isAuthor;
            
            // 삭제 권한: 댓글 관리 권한 OR 작성자 본인
            $canDelete = $canManageComments || $isAuthor;
            
            // 답글 권한: 댓글 작성 권한
            $canReply = $canWriteComments;
        }
        
        return [
            'canEdit' => $canEdit,
            'canDelete' => $canDelete,
            'canReply' => $canReply,
            'canManage' => $canManageComments, // 댓글 관리 권한 추가
        ];
    }

    /**
     * 게시물 첨부파일 조회
     */
    public function getPostAttachments(Board $board, $postId)
    {
        // 첨부파일 보기는 게시판의 파일 업로드 설정이 활성화된 경우에만
        // (업로드 권한과 관계없이 이미 업로드된 파일은 볼 수 있어야 함)
        if (!$board->getSetting('allow_file_upload', false)) {
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
            'options' => $this->buildOptionsString($data),
            'published_at' => now(),
        ];

        $post = $postModelClass::create($postData);

        // 슬러그와 excerpt 처리 (폼에서 전달된 값이 있으면 사용, 없으면 자동 생성)
        $post->slug = !empty($data['slug']) ? $data['slug'] : $post->generateSlug();
        $post->excerpt = !empty($data['excerpt']) ? $data['excerpt'] : $post->generateExcerpt();
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
            'options' => $this->buildOptionsString($data),
        ]);

        // 슬러그와 excerpt 처리
        // 폼에서 전달된 값이 있으면 사용, 없으면 자동 생성 (제목이 변경된 경우)
        if (!empty($data['slug'])) {
            $post->slug = $data['slug'];
        } elseif ($post->wasChanged('title')) {
            $post->slug = $post->generateSlug();
        }

        if (!empty($data['excerpt'])) {
            $post->excerpt = $data['excerpt'];
        } elseif ($post->wasChanged('title') || $post->wasChanged('content')) {
            $post->excerpt = $post->generateExcerpt();
        }

        $post->save();

        return $post;
    }

    /**
     * 옵션 문자열 생성
     */
    private function buildOptionsString(array $data): ?string
    {
        if (empty($data['options']) || !is_array($data['options'])) {
            return null;
        }
        
        // 배열에서 값이 있는 키들만 필터링
        $activeOptions = array_keys(array_filter($data['options'], function($value) {
            return !empty($value) && $value !== '0' && $value !== false;
        }));
        
        return empty($activeOptions) ? null : implode('|', $activeOptions);
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
