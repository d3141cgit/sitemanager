<?php

namespace SiteManager\Http\Controllers;

use SiteManager\Models\Board;
use SiteManager\Models\BoardPost;
use SiteManager\Models\BoardComment;
use SiteManager\Models\BoardAttachment;
use SiteManager\Services\BoardService;
use SiteManager\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CommentController extends Controller
{
    public function __construct(
        private BoardService $boardService,
        private FileUploadService $fileUploadService
    ) {}

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
        // 서비스 프로바이더에서 등록된 패키지 뷰 네임스페이스 사용
        try {
            $viewFactory = app('view');
            $finder = $viewFactory->getFinder();
            
            // 패키지 뷰 경로 찾기
            $packageViewName = "sitemanager::{$viewName}";
            return $finder->find($packageViewName);
            
        } catch (\Exception $e) {
            // 뷰를 찾을 수 없는 경우 빈 문자열 반환
            return '';
        }
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
        // 디버깅을 위한 로깅 추가
        Log::info('Comment store request received', [
            'slug' => $slug,
            'post_id' => $postId,
            'request_id' => Str::random(8),
            'user_id' => Auth::id(),
            'content_length' => strlen($request->input('content', '')),
            'has_files' => $request->hasFile('files'),
            'content_type' => $request->header('Content-Type'),
            'method' => $request->method(),
        ]);
        
        $board = Board::where('slug', $slug)->firstOrFail();
        
        // 권한 체크: 댓글 쓰기 권한 확인
        if ($board->menu_id && !can('writeComments', $board)) {
            return response()->json(['error' => '댓글을 작성할 권한이 없습니다.'], 403);
        }
        
        // 댓글 허용 여부 체크
        if (!$board->getSetting('allow_comments', true)) {
            return response()->json(['error' => '댓글이 허용되지 않습니다.'], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:2000',
            'post_id' => 'required|integer',
            'parent_id' => 'nullable|integer',
            'author_name' => 'nullable|string|max:50',
            'author_email' => 'nullable|email|max:100',
            'files.*' => 'nullable|file|max:10240', // 최대 10MB
        ]);

        // 파일 업로드 권한 확인
        $hasFiles = $request->hasFile('files');
        if ($hasFiles && !can('uploadCommentFiles', $board)) {
            return response()->json(['error' => '댓글 파일 업로드 권한이 없습니다.'], 403);
        }

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

            // 파일 업로드 처리
            if ($hasFiles) {
                $this->handleCommentFileUploads($request, $comment, $board);
            }

            // 댓글에 권한 정보 추가
            $comment->permissions = $this->boardService->calculateCommentPermissions($board, $comment);
            
            // 첨부파일과 함께 댓글 다시 로드
            $comment->load('attachments');

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
        
        // 파일 업로드 권한 체크
        $hasFileUploadPermission = can('uploadCommentFiles', $board);
        
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
            'files.*' => $hasFileUploadPermission ? 'file|max:10240' : '', // 10MB max per file
            '_method' => 'string|in:PUT', // Laravel method spoofing 지원
            'deleted_attachments' => 'nullable|string', // JSON 문자열로 전송된 삭제 예정 첨부파일 ID들
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
            DB::beginTransaction();
            
            // 디버깅 로그 추가
            Log::info('Comment update request', [
                'comment_id' => $commentId,
                'deleted_attachments_raw' => $validated['deleted_attachments'] ?? null,
                'has_files' => $request->hasFile('files'),
                'request_data' => $request->all()
            ]);
            
            $comment->update([
                'content' => $this->filterHtml($validated['content']),
                'is_edited' => true,
            ]);

            // 삭제 예정 첨부파일 처리
            if (!empty($validated['deleted_attachments'])) {
                $deletedAttachmentIds = json_decode($validated['deleted_attachments'], true);
                if (is_array($deletedAttachmentIds) && !empty($deletedAttachmentIds)) {
                    Log::info('Processing deleted attachments', [
                        'comment_id' => $commentId,
                        'deleted_attachment_ids' => $deletedAttachmentIds
                    ]);
                    $this->handleDeletedAttachments($comment, $deletedAttachmentIds);
                }
            }

            // 파일 업로드 처리 (권한이 있고 파일이 있는 경우)
            if ($hasFileUploadPermission && $request->hasFile('files')) {
                $this->handleCommentFileUploads($request, $comment, $board);
            }

            DB::commit();

            // 권한 정보 추가 (BoardService를 통해 계산)
            $comment->permissions = $this->boardService->calculateCommentPermissions($board, $comment);

            // 첨부파일 정보도 함께 로드
            $comment->load('attachments');

            // 업데이트된 첨부파일 HTML 렌더링
            $attachmentsHtml = '';
            if ($comment->attachments && $comment->attachments->count() > 0) {
                $attachmentsHtml = view('sitemanager::board.partials.comment-attachments', [
                    'comment' => $comment
                ])->render();
            }

            return response()->json([
                'success' => true,
                'message' => '댓글이 수정되었습니다.',
                'comment' => $comment->fresh()->load('attachments'),
                'attachments_html' => $attachmentsHtml,
                'has_attachments' => $comment->attachments->count() > 0,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
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
        
        try {
            $result = $this->boardService->deleteComment($board, $postId, $commentId, Auth::id());
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Comment deletion failed', [
                'error' => $e->getMessage(),
                'comment_id' => $commentId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        }
    }

    /**
     * 댓글 목록 조회 (AJAX)
     */
    public function index(string $slug, $postId): JsonResponse
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        // 권한 체크: 댓글 읽기 권한 확인
        if ($board->menu_id && !can('readComments', $board)) {
            return response()->json(['error' => '댓글을 조회할 권한이 없습니다.'], 403);
        }
        
        $postModelClass = BoardPost::forBoard($slug);
        $post = $postModelClass::findOrFail($postId);
        
        // 서비스 레이어를 통해 댓글 조회
        $comments = $this->boardService->getPostComments($board, $postId);

        $commentsHtml = view($this->selectView('comments'), compact('comments', 'board'))->render();

        return response()->json([
            'success' => true,
            'comments' => $comments,
            'comments_html' => $commentsHtml,
            'comment_count' => $comments ? $comments->count() : 0,
        ]);
    }

    /**
     * 댓글 승인
     */
    public function approve(string $slug, $postId, $commentId): JsonResponse
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        // 댓글 관리 권한 체크
        if (!can('manageComments', $board)) {
            return response()->json(['error' => 'Access denied.'], 403);
        }
        
        try {
            $commentModelClass = BoardComment::forBoard($slug);
            $comment = $commentModelClass::findOrFail($commentId);
            
            $comment->update(['status' => 'approved']);
            
            return response()->json([
                'success' => true,
                'message' => 'Comment has been approved.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Comment approval failed', [
                'error' => $e->getMessage(),
                'comment_id' => $commentId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while approving the comment.'
            ], 500);
        }
    }

    /**
     * 댓글 파일 업로드 처리
     */
    private function handleCommentFileUploads(Request $request, $comment, Board $board): void
    {
        if (!$request->hasFile('files')) {
            return;
        }

        $folder = "attachments/board/{$board->slug}";
        
        foreach ($request->file('files') as $file) {
            if (!$file->isValid()) {
                continue;
            }

            try {
                // FileUploadService를 사용하여 파일 업로드
                $uploadResult = $this->fileUploadService->uploadFile($file, $folder);
                
                // 파일 카테고리 결정
                $category = $this->determineFileCategory($uploadResult['mime_type'], $uploadResult['extension']);
                
                // DB에 첨부파일 정보 저장
                BoardAttachment::create([
                    'post_id' => $comment->post_id,
                    'comment_id' => $comment->id,
                    'board_slug' => $board->slug,
                    'attachment_type' => 'comment',
                    'filename' => $uploadResult['filename'],
                    'original_name' => $uploadResult['name'],
                    'file_path' => $uploadResult['path'],
                    'file_extension' => $uploadResult['extension'],
                    'file_size' => $uploadResult['size'],
                    'mime_type' => $uploadResult['mime_type'],
                    'category' => $category,
                    'sort_order' => 0,
                ]);
                
            } catch (\Exception $e) {
                Log::error('Comment file upload failed', [
                    'comment_id' => $comment->id,
                    'file_name' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ]);
                // 개별 파일 업로드 실패시 다음 파일로 계속 진행
                continue;
            }
        }
    }

    /**
     * 파일 카테고리 결정
     */
    private function determineFileCategory(string $mimeType, string $extension): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } elseif (in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz'])) {
            return 'archive';
        } elseif (in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'])) {
            return 'document';
        }
        
        return 'other';
    }

    /**
     * 댓글 첨부파일 삭제
     */
    public function deleteAttachment(Request $request, string $slug, $postId, $commentId, $attachmentId): JsonResponse
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        $commentModelClass = BoardComment::forBoard($slug);
        $comment = $commentModelClass::findOrFail($commentId);
        
        // 권한 체크 (본인 댓글이거나 댓글 관리 권한이 있는지)
        $isOwner = Auth::id() === $comment->member_id;
        $canManage = can('manageComments', $board);
        
        if (!$isOwner && !$canManage) {
            return response()->json(['error' => '삭제 권한이 없습니다.'], 403);
        }

        try {
            $attachment = BoardAttachment::where('id', $attachmentId)
                ->where('comment_id', $commentId)
                ->where('attachment_type', 'comment')
                ->firstOrFail();

            // 실제 파일 삭제
            $this->fileUploadService->deleteFile($attachment->file_path);
            
            // DB에서 삭제
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => '첨부파일이 삭제되었습니다.',
            ]);

        } catch (\Exception $e) {
            Log::error('Comment attachment deletion failed', [
                'error' => $e->getMessage(),
                'comment_id' => $commentId,
                'attachment_id' => $attachmentId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '첨부파일 삭제 중 오류가 발생했습니다.'
            ], 500);
        }
    }

    /**
     * 삭제 예정 첨부파일들을 실제로 삭제 처리
     */
    private function handleDeletedAttachments($comment, array $deletedAttachmentIds): void
    {
        if (empty($deletedAttachmentIds)) {
            return;
        }

        // 해당 댓글의 첨부파일들만 삭제 (보안을 위해 comment_id 확인)
        $attachments = BoardAttachment::whereIn('id', $deletedAttachmentIds)
            ->where('comment_id', $comment->id)
            ->where('attachment_type', 'comment')
            ->get();

        foreach ($attachments as $attachment) {
            try {
                // 실제 파일 삭제
                $this->fileUploadService->deleteFile($attachment->file_path);
                
                // DB에서 삭제
                $attachment->delete();

                Log::info('Comment attachment deleted via edit', [
                    'attachment_id' => $attachment->id,
                    'comment_id' => $comment->id,
                    'file_path' => $attachment->file_path,
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to delete attachment during comment edit', [
                    'attachment_id' => $attachment->id,
                    'comment_id' => $comment->id,
                    'error' => $e->getMessage(),
                ]);
                // 개별 첨부파일 삭제 실패는 전체 작업을 중단하지 않음
            }
        }
    }
}
