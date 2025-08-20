<?php

namespace SiteManager\Http\Controllers;

use SiteManager\Models\Board;
use SiteManager\Models\BoardPost;
use SiteManager\Models\BoardComment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommentController extends Controller
{
    /**
     * 뷰 파일을 선택합니다. 프로젝트에 있으면 프로젝트 뷰를, 없으면 패키지 뷰를 반환합니다.
     */
    /**
     * 뷰 파일을 선택합니다. 현재 게시판의 스킨을 자동으로 감지하여 적용합니다.
     */
    private function selectView(string $viewName): string
    {
        // 현재 요청에서 게시판 정보 가져오기
        $board = $this->getCurrentBoard();
        $skin = $board?->skin ?? 'default';
        
        // 스킨이 있고 'default'가 아닌 경우
        if ($skin && $skin !== 'default') {
            // 1. 프로젝트의 스킨 뷰 확인 (예: views/board/gallery/partials/comment.blade.php)
            $skinViewPath = resource_path("views/board/{$skin}/partials/{$viewName}.blade.php");
            
            if (file_exists($skinViewPath)) {
                return "board.{$skin}.partials.{$viewName}";
            }
            
            // 2. 패키지의 스킨 뷰 확인 (예: sitemanager::board.gallery.partials.comment)
            $packageSkinViewPath = $this->getPackageViewPath("board.{$skin}.partials.{$viewName}");
            if (file_exists($packageSkinViewPath)) {
                return "sitemanager::board.{$skin}.partials.{$viewName}";
            }
        }
        
        // 3. 프로젝트의 기본 뷰 확인 (예: views/board/partials/comment.blade.php)
        $projectViewPath = resource_path("views/board/partials/{$viewName}.blade.php");
        
        if (file_exists($projectViewPath)) {
            return "board.partials.{$viewName}";
        }
        
        // 4. 패키지의 기본 뷰 사용 (예: sitemanager::board.partials.comment)
        return "sitemanager::board.partials.{$viewName}";
    }
    
    /**
     * 현재 요청에서 게시판 정보를 가져옵니다.
     */
    private function getCurrentBoard(): ?Board
    {
        // URL에서 board_slug 파라미터 확인
        $boardSlug = request()->route('board') ?? request()->route('board_slug');
        
        if ($boardSlug) {
            return Board::where('slug', $boardSlug)->first();
        }
        
        // POST 요청에서 board_id 확인
        if (request()->has('board_id')) {
            return Board::find(request('board_id'));
        }
        
        // 게시글 ID에서 게시판 정보 추출
        if (request()->route('post') || request()->route('post_id')) {
            $postId = request()->route('post') ?? request()->route('post_id');
            $post = BoardPost::find($postId);
            return $post?->board;
        }
        
        return null;
    }

    /**
     * 패키지 뷰 파일의 실제 경로를 반환합니다.
     */
    private function getPackageViewPath(string $viewName): string
    {
        $viewPath = str_replace('.', '/', $viewName);
        return __DIR__ . "/../../../resources/views/{$viewPath}.blade.php";
    }

    /**
     * HTML 태그 필터링 - 기본적인 서식 태그만 허용
     */
    private function filterHtml($content)
    {
        // 허용할 태그들: bold, italic, underline, strikethrough, 줄바꿈, 링크
        $allowedTags = '<b><strong><i><em><u><s><strike><del><br><a>';
        
        // 허용되지 않은 태그 제거
        $content = strip_tags($content, $allowedTags);
        
        // 추가 보안: script 태그와 위험한 내용 제거 (style 태그는 제거하지만 style 속성은 허용)
        $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $content);
        
        // 위험한 이벤트 핸들러 제거 (onclick, onload 등)
        $content = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $content);
        
        // 링크에서 javascript: 프로토콜 제거
        $content = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', 'href="#"', $content);
        
        // style 속성에서 위험한 내용 제거 (expression, javascript 등)
        $content = preg_replace('/style\s*=\s*["\'][^"\']*(?:expression|javascript|vbscript)[^"\']*["\']/i', '', $content);
        
        return trim($content);
    }

    /**
     * 댓글 저장
     */
    public function store(Request $request, string $slug, $postId): JsonResponse
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        // 권한 체크: 댓글 쓰기 권한 확인
        if ($board->menu_id && !can('writeComments', $board)) {
            return response()->json(['error' => '댓글을 작성할 권한이 없습니다.'], 403);
        }
        
        // 댓글 허용 여부 체크
        if (!$board->getSetting('allow_comments', true)) {
            return response()->json(['error' => '댓글이 허용되지 않습니다.'], 403);
        }

        // 로그인 체크
        if ($board->getSetting('require_login', false) && !Auth::check()) {
            return response()->json(['error' => '로그인이 필요합니다.'], 401);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:2000',
            'post_id' => 'required|integer',
            'parent_id' => 'nullable|integer',
            'author_name' => 'nullable|string|max:50',
            'author_email' => 'nullable|email|max:100',
        ]);

        // 동적 모델 클래스 생성
        $postModelClass = BoardPost::forBoard($slug);
        $commentModelClass = BoardComment::forBoard($slug);

        // 게시글 존재 확인
        $post = $postModelClass::findOrFail($postId);
        
        // 요청된 post_id와 URL의 postId가 일치하는지 확인
        if ($validated['post_id'] != $post->id) {
            return response()->json(['error' => '게시글 정보가 일치하지 않습니다.'], 400);
        }

        // 부모 댓글 확인 (대댓글인 경우)
        $parentId = $request->input('parent_id') ?: null;
        $parentComment = null;
        if ($parentId) {
            $parentComment = $commentModelClass::findOrFail($parentId);
        }

        DB::beginTransaction();
        
        try {
            // 댓글 데이터 준비
            $commentData = [
                'post_id' => $post->id,
                'parent_id' => $parentId,
                'content' => $this->filterHtml($validated['content']),
            ];

            // 로그인한 사용자
            if (Auth::check()) {
                $commentData['member_id'] = Auth::id();
                $commentData['author_name'] = Auth::user()->name;
            } else {
                // 비회원 댓글
                $commentData['author_name'] = $request->input('author_name', '익명');
            }

            // 댓글 승인 여부 결정
            $requireModeration = $board->getSetting('moderate_comments', false);
            $commentData['status'] = $requireModeration ? 'pending' : 'approved';

            // 댓글 생성
            $comment = $commentModelClass::create($commentData);

            // 게시글의 댓글 수 업데이트
            $post->updateCommentCount();

            DB::commit();

            // 댓글 HTML 렌더링
            $commentHtml = view($this->selectView('comment'), compact('comment', 'board', 'post') + ['level' => 0])->render();

            return response()->json([
                'success' => true,
                'message' => $requireModeration ? '댓글이 등록되었습니다. 승인 후 표시됩니다.' : '댓글이 등록되었습니다.',
                'comment' => $comment,
                'comment_html' => $commentHtml,
                'comment_count' => $post->fresh()->comment_count,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Comment creation failed', [
                'error' => $e->getMessage(),
                'board_slug' => $slug,
                'post_id' => $postId,
                'data' => $commentData ?? []
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '댓글 등록 중 오류가 발생했습니다.'
            ], 500);
        }
    }

    /**
     * 댓글 수정
     */
    public function update(Request $request, string $slug, $postId, $commentId): JsonResponse
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $commentModelClass = BoardComment::forBoard($slug);
        $postModelClass = BoardPost::forBoard($slug);
        $comment = $commentModelClass::findOrFail($commentId);
        $post = $postModelClass::findOrFail($postId);

        // 권한 체크 (본인 댓글이거나 댓글 관리 권한이 있는지)
        $isOwner = Auth::id() === $comment->member_id;
        $canManage = can('manageComments', $board);
        
        if (!$isOwner && !$canManage) {
            return response()->json(['error' => '수정 권한이 없습니다.'], 403);
        }

        try {
            $comment->update([
                'content' => $this->filterHtml($validated['content']),
                'is_edited' => true,
            ]);

            $commentHtml = view($this->selectView('comment'), compact('comment', 'board', 'post') + ['level' => 0])->render();

            return response()->json([
                'success' => true,
                'message' => '댓글이 수정되었습니다.',
                'comment' => $comment->fresh(),
                'comment_html' => $commentHtml,
            ]);

        } catch (\Exception $e) {
            Log::error('Comment update failed', [
                'error' => $e->getMessage(),
                'comment_id' => $commentId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '댓글 수정 중 오류가 발생했습니다.'
            ], 500);
        }
    }

    /**
     * 댓글 삭제
     */
    public function destroy(string $slug, $postId, $commentId): JsonResponse
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        $commentModelClass = BoardComment::forBoard($slug);
        $postModelClass = BoardPost::forBoard($slug);
        
        $comment = $commentModelClass::findOrFail($commentId);
        $post = $postModelClass::findOrFail($postId);

        // 권한 체크 (본인 댓글이거나 댓글 관리 권한이 있는지)
        $isOwner = Auth::id() === $comment->member_id;
        $canManage = can('manageComments', $board);
        
        if (!$isOwner && !$canManage) {
            return response()->json(['error' => '삭제 권한이 없습니다.'], 403);
        }

        DB::beginTransaction();
        
        try {
            // 대댓글이 있는 경우 내용만 삭제하고 "[삭제된 댓글입니다]"로 표시
            $hasReplies = $commentModelClass::where('parent_id', $comment->id)->exists();
            
            if ($hasReplies) {
                $comment->update([
                    'content' => '[삭제된 댓글입니다]',
                    'status' => 'deleted',
                ]);
            } else {
                $comment->delete();
            }

            // 게시글의 댓글 수 업데이트
            $post->updateCommentCount();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '댓글이 삭제되었습니다.',
                'comment_count' => $post->fresh()->comment_count,
                'deleted_completely' => !$hasReplies,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Comment deletion failed', [
                'error' => $e->getMessage(),
                'comment_id' => $commentId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '댓글 삭제 중 오류가 발생했습니다.'
            ], 500);
        }
    }

    /**
     * 댓글 목록 조회 (AJAX)
     */
    public function index(string $slug, $postId): JsonResponse
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        $commentModelClass = BoardComment::forBoard($slug);
        $postModelClass = BoardPost::forBoard($slug);
        
        $post = $postModelClass::findOrFail($postId);
        
        // 승인된 댓글만 조회 (최신순)
        $comments = $commentModelClass::where('post_id', $postId)
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->get();

        $commentsHtml = view($this->selectView('comments'), compact('comments', 'board'))->render();

        return response()->json([
            'success' => true,
            'comments' => $comments,
            'comments_html' => $commentsHtml,
            'comment_count' => $comments->count(),
        ]);
    }
}
