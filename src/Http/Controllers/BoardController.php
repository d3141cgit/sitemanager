<?php

namespace SiteManager\Http\Controllers;

use SiteManager\Models\Board;
use SiteManager\Models\BoardPost;
use SiteManager\Models\BoardComment;
use SiteManager\Models\BoardAttachment;
use SiteManager\Services\BoardService;
use SiteManager\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BoardController extends Controller
{
    public function __construct(
        private BoardService $boardService,
        private FileUploadService $fileUploadService
    ) {}

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
            // 1. 프로젝트의 스킨 뷰 확인 (예: views/board/gallery/index.blade.php)
            $skinViewPath = resource_path("views/board/{$skin}/{$viewName}.blade.php");
            
            if (file_exists($skinViewPath)) {
                return "board.{$skin}.{$viewName}";
            }
            
            // 2. 패키지의 스킨 뷰 확인 (예: sitemanager::board.gallery.index)
            $packageSkinViewPath = $this->getPackageViewPath("board.{$skin}.{$viewName}");
            if (file_exists($packageSkinViewPath)) {
                return "sitemanager::board.{$skin}.{$viewName}";
            }
        }
        
        // 3. 프로젝트의 기본 뷰 확인 (예: views/board/index.blade.php)
        $projectViewPath = resource_path("views/board/{$viewName}.blade.php");
        
        if (file_exists($projectViewPath)) {
            return "board.{$viewName}";
        }
        
        // 4. 패키지의 기본 뷰 사용 (예: sitemanager::board.index)
        return "sitemanager::board.{$viewName}";
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
     * 게시판 메인 (게시글 목록)
     */
    public function index(string $slug): View
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        // 권한 체크: 메뉴에 연결된 경우만 권한 확인
        if ($board->menu_id && !can('index', $board)) {
            abort(403, '게시판에 접근할 권한이 없습니다.');
        }
        
        // 동적 모델 클래스 생성
        $postModelClass = BoardPost::forBoard($slug);
        
        // 게시글 목록 조회
        $posts = $postModelClass::with('member')
            ->published()
            ->orderBy('is_notice', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($board->getSetting('posts_per_page', 20));

        // 공지사항 조회
        $notices = $postModelClass::with('member')
            ->notices()
            ->published()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view($this->selectView('index'), compact('board', 'posts', 'notices'));
    }

    /**
     * 게시글 상세
     */
    public function show(Request $request, string $slug, $id)
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        // 권한 체크: 메뉴에 연결된 경우만 권한 확인
        if ($board->menu_id && !can('read', $board)) {
            abort(403, '게시글을 읽을 권한이 없습니다.');
        }
        
        // 동적 모델 클래스 생성
        $postModelClass = BoardPost::forBoard($slug);
        $commentModelClass = BoardComment::forBoard($slug);
        
        // 게시글 조회 (슬러그 또는 ID)
        if (is_numeric($id)) {
            $post = $postModelClass::with('member')->findOrFail($id);
        } else {
            $post = $postModelClass::with('member')->where('slug', $id)->firstOrFail();
        }

        // 로그인하지 않은 경우 로그인 필수 체크
        if ($board->getSetting('require_login', false) && !Auth::check()) {
            return redirect()->route('login')->with('error', '로그인이 필요합니다.');
        }

        // 조회수 증가 (중복 방지)
        $this->incrementViewCountIfValid($post, $request);

        // 댓글 조회 (댓글 허용 시)
        $comments = null;
        if ($board->getSetting('allow_comments', true)) {
            $comments = $commentModelClass::with('member', 'children.member')
                ->topLevel()
                ->approved()
                ->forPost($post->id)
                ->orderBy('created_at', 'desc')  // 최신 댓글이 위로
                ->get();
        }

        // 첨부파일 조회 (파일 업로드 허용 시)
        $attachments = null;
        if ($board->allowsFileUpload()) {
            $attachments = BoardAttachment::byPost($post->id, $board->slug)
                ->ordered()
                ->get();
        }

        // 이전/다음 게시글
        $prevPost = $postModelClass::where('id', '<', $post->id)
            ->published()
            ->orderBy('id', 'desc')
            ->first();

        $nextPost = $postModelClass::where('id', '>', $post->id)
            ->published()
            ->orderBy('id', 'asc')
            ->first();

        return view($this->selectView('show'), compact('board', 'post', 'comments', 'attachments', 'prevPost', 'nextPost'));
    }

    /**
     * 게시글 작성 폼
     */
    public function create(string $slug)
    {
        $board = Board::where('slug', $slug)->firstOrFail();

        // 권한 체크: 메뉴에 연결된 경우만 권한 확인
        if ($board->menu_id && !can('write', $board)) {
            abort(403, '게시글을 작성할 권한이 없습니다.');
        }

        // 로그인 체크
        if ($board->getSetting('require_login', false) && !Auth::check()) {
            return redirect()->route('login')->with('error', '로그인이 필요합니다.');
        }

        return view($this->selectView('form'), compact('board'));
    }

    /**
     * 게시글 저장
     */
    public function store(Request $request, string $slug): RedirectResponse
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        // 권한 체크: 메뉴에 연결된 경우만 권한 확인
        if ($board->menu_id && !can('write', $board)) {
            abort(403, '게시글을 작성할 권한이 없습니다.');
        }
        
        // 로그인 체크
        if ($board->getSetting('require_login', false) && !Auth::check()) {
            return redirect()->route('login')->with('error', '로그인이 필요합니다.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'category' => 'nullable|string|max:50',
            'tags' => 'nullable|string',
            'is_notice' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'files.*' => 'nullable|file|max:' . ($board->getSetting('max_file_size', 2048)),
            'file_names.*' => 'nullable|string|max:255',
            'file_descriptions.*' => 'nullable|string|max:500',
        ]);

        // 동적 모델 클래스 생성
        $postModelClass = BoardPost::forBoard($slug);

        DB::beginTransaction();
        
        try {
            // 게시글 생성
            $postData = [
                'board_id' => $board->id,
                'member_id' => Auth::id(),
                'author_name' => Auth::user()?->name,
                'title' => $validated['title'],
                'content' => $validated['content'],
                'content_type' => 'html',
                'category' => $validated['category'] ?? null,
                'tags' => isset($validated['tags']) && $validated['tags'] ? explode(',', $validated['tags']) : null,
                'status' => 'published',
                'is_notice' => $validated['is_notice'] ?? false,
                'is_featured' => $validated['is_featured'] ?? false,
                'published_at' => now(),
            ];

            $post = $postModelClass::create($postData);

            // SEO 슬러그 생성
            $post->slug = $post->generateSlug();
            $post->excerpt = $post->generateExcerpt();
            $post->save();

            // 파일 업로드 처리
            if ($request->hasFile('files') && $board->allowsFileUpload()) {
                $this->handleFileUploads(
                    $request->file('files'), 
                    $board, 
                    $post,
                    $request->input('file_names', []),
                    $request->input('file_descriptions', [])
                );
            }

            DB::commit();

            return redirect()
                ->route('board.show', [$slug, $post->slug ?: $post->id])
                ->with('success', '게시글이 등록되었습니다.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', '게시글 등록 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 게시글 수정 폼
     */
    public function edit(string $slug, $id): View
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        $postModelClass = BoardPost::forBoard($slug);
        
        if (is_numeric($id)) {
            $post = $postModelClass::findOrFail($id);
        } else {
            $post = $postModelClass::where('slug', $id)->firstOrFail();
        }

        // 권한 체크: 작성자 본인이거나 관리 권한이 있어야 함
        $user = Auth::user();
        $canEdit = false;

        if ($user) {
            // 작성자 본인
            if ($post->member_id === $user->id) {
                $canEdit = true;
            }
            // 게시판 관리 권한
            elseif ($board->menu_id && can('manage', $board)) {
                $canEdit = true;
            }
            // 시스템 관리자
            elseif ($user->level >= config('member.admin_level', 200)) {
                $canEdit = true;
            }
        }

        if (!$canEdit) {
            abort(403, '게시글을 수정할 권한이 없습니다.');
        }

        return view($this->selectView('form'), compact('board', 'post'));
    }

    /**
     * 게시글 수정
     */
    public function update(Request $request, string $slug, $id): RedirectResponse
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        $postModelClass = BoardPost::forBoard($slug);
        
        if (is_numeric($id)) {
            $post = $postModelClass::findOrFail($id);
        } else {
            $post = $postModelClass::where('slug', $id)->firstOrFail();
        }

        // 권한 체크: 작성자 본인이거나 관리 권한이 있어야 함 (edit과 동일)
        $user = Auth::user();
        $canEdit = false;

        if ($user) {
            // 작성자 본인
            if ($post->member_id === $user->id) {
                $canEdit = true;
            }
            // 게시판 관리 권한
            elseif ($board->menu_id && can('manage', $board)) {
                $canEdit = true;
            }
            // 시스템 관리자
            elseif ($user->level >= config('member.admin_level', 200)) {
                $canEdit = true;
            }
        }

        if (!$canEdit) {
            abort(403, '게시글을 수정할 권한이 없습니다.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'category' => 'nullable|string|max:50',
            'tags' => 'nullable|string',
            'is_notice' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'files.*' => 'nullable|file|max:' . ($board->getSetting('max_file_size', 2048)),
            'file_names.*' => 'nullable|string|max:255',
            'file_descriptions.*' => 'nullable|string|max:500',
            'existing_file_names.*' => 'nullable|string|max:255',
            'existing_file_descriptions.*' => 'nullable|string|max:500',
            'removed_files' => 'nullable|string',
        ]);

        DB::beginTransaction();
        
        try {
            // 게시글 수정
            $post->update([
                'title' => $validated['title'],
                'content' => $validated['content'],
                'category' => $validated['category'] ?? null,
                'tags' => isset($validated['tags']) && $validated['tags'] ? explode(',', $validated['tags']) : null,
                'is_notice' => $validated['is_notice'] ?? false,
                'is_featured' => $validated['is_featured'] ?? false,
            ]);

            // 슬러그 재생성 (제목이 변경된 경우)
            if ($post->wasChanged('title')) {
                $post->slug = $post->generateSlug();
                $post->excerpt = $post->generateExcerpt();
                $post->save();
            }

            // 파일 처리
            if ($board->allowsFileUpload()) {
                // 기존 첨부파일 정보 업데이트
                $existingFileNames = $request->input('existing_file_names', []);
                $existingFileDescriptions = $request->input('existing_file_descriptions', []);
                
                foreach ($existingFileNames as $attachmentId => $originalName) {
                    $attachment = $post->attachments()->find($attachmentId);
                    if ($attachment) {
                        $attachment->update([
                            'original_name' => $originalName ?: $attachment->original_name,
                            'description' => $existingFileDescriptions[$attachmentId] ?? $attachment->description,
                        ]);
                    }
                }
                
                // 새 파일 업로드
                if ($request->hasFile('files')) {
                    $this->handleFileUploads(
                        $request->file('files'), 
                        $board, 
                        $post,
                        $request->input('file_names', []),
                        $request->input('file_descriptions', [])
                    );
                }
            }

            DB::commit();

            return redirect()
                ->route('board.show', [$slug, $post->slug ?: $post->id])
                ->with('success', '게시글이 수정되었습니다.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', '게시글 수정 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 게시글 삭제
     */
    public function destroy(string $slug, $id): RedirectResponse
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        $postModelClass = BoardPost::forBoard($slug);
        
        if (is_numeric($id)) {
            $post = $postModelClass::findOrFail($id);
        } else {
            $post = $postModelClass::where('slug', $id)->firstOrFail();
        }

        // 권한 체크: 작성자 본인이거나 관리 권한이 있어야 함 (edit과 동일)
        $user = Auth::user();
        $canDelete = false;

        if ($user) {
            // 작성자 본인
            if ($post->member_id === $user->id) {
                $canDelete = true;
            }
            // 게시판 관리 권한
            elseif ($board->menu_id && can('manage', $board)) {
                $canDelete = true;
            }
            // 시스템 관리자
            elseif ($user->level >= config('member.admin_level', 200)) {
                $canDelete = true;
            }
        }

        if (!$canDelete) {
            abort(403, '게시글을 삭제할 권한이 없습니다.');
        }

        DB::beginTransaction();
        
        try {
            // 관련 파일들 삭제
            $this->deletePostFiles($post);
            
            // 게시글 삭제 (soft delete)
            $post->delete();

            DB::commit();

            return redirect()
                ->route('board.index', $slug)
                ->with('success', '게시글이 삭제되었습니다.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->with('error', '게시글 삭제 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 파일 업로드 처리
     */
    private function handleFileUploads(array $files, Board $board, $post, array $fileNames = [], array $fileDescriptions = []): array
    {
        $uploadedAttachments = [];
        $allowedTypes = $board->getAllowedFileTypes();
        $maxSize = $board->getMaxFileSize();
        $maxFiles = $board->getMaxFilesPerPost();
        
        // 현재 게시글의 첨부파일 수 확인
        $currentFileCount = BoardAttachment::byPost($post->id, $board->slug)->count();
        $remainingSlots = $maxFiles - $currentFileCount;
        
        if ($remainingSlots <= 0) {
            return $uploadedAttachments;
        }
        
        // FileUploadService를 사용하여 파일 업로드
        $uploadedFiles = $this->fileUploadService->uploadBoardAttachments($files, $board->slug, $post->id, [
            'allowed_types' => $allowedTypes,
            'max_size' => $maxSize,
            'max_files' => $remainingSlots
        ]);
        
        // BoardAttachment 모델에 저장
        foreach ($uploadedFiles as $index => $fileInfo) {
            try {
                // 사용자 정의 파일명과 설명 처리
                $customName = isset($fileNames[$index]) && !empty($fileNames[$index]) 
                    ? $fileNames[$index] 
                    : $fileInfo['original_name'];
                    
                $description = isset($fileDescriptions[$index]) && !empty($fileDescriptions[$index])
                    ? $fileDescriptions[$index]
                    : null;
                
                $attachment = BoardAttachment::create([
                    'post_id' => $post->id,
                    'board_slug' => $board->slug,
                    'filename' => $fileInfo['filename'],
                    'original_name' => $customName, // 사용자 정의 파일명 사용
                    'file_path' => $fileInfo['path'],
                    'file_extension' => $fileInfo['file_extension'],
                    'file_size' => $fileInfo['file_size'],
                    'mime_type' => $fileInfo['mime_type'],
                    'category' => $fileInfo['category'],
                    'description' => $description, // 사용자 정의 설명 추가
                    'sort_order' => $currentFileCount + $index,
                    'download_count' => 0,
                ]);
                
                $uploadedAttachments[] = $attachment;
                
            } catch (\Exception $e) {
                // 업로드된 파일 삭제 (DB 저장 실패 시)
                $this->fileUploadService->deleteFile($fileInfo['path']);
                
                Log::error('BoardAttachment creation failed', [
                    'board' => $board->slug,
                    'post_id' => $post->id,
                    'file' => $fileInfo['original_name'],
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        
        return $uploadedAttachments;
    }

    /**
     * 파일 제거 처리
     */
    private function removeFiles(array $existingFiles, array $removedFileIds): array
    {
        $filesToDelete = [];
        $remainingFiles = [];
        
        foreach ($existingFiles as $file) {
            if (in_array($file['id'], $removedFileIds)) {
                $filesToDelete[] = $file;
            } else {
                $remainingFiles[] = $file;
            }
        }
        
        // 실제 파일 삭제
        foreach ($filesToDelete as $file) {
            $this->fileUploadService->deleteFile($file['path'], $file['disk']);
        }
        
        return $remainingFiles;
    }

    /**
     * 게시글 파일 삭제
     */
    private function deletePostFiles($post): void
    {
        if (empty($post->files)) {
            return;
        }
        
        foreach ($post->files as $file) {
            $this->fileUploadService->deleteFile($file['path'], $file['disk']);
        }
    }

    /**
     * 파일 다운로드
     */
    public function downloadFile(string $slug, string $attachmentId)
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        // 권한 체크: 게시판을 볼 수 있어야 파일도 다운로드 가능
        if ($board->menu_id && !can('read', $board)) {
            abort(403, '파일에 접근할 권한이 없습니다.');
        }
        
        // 첨부파일 찾기
        $attachment = BoardAttachment::where('id', $attachmentId)
            ->where('board_slug', $board->slug)
            ->firstOrFail();
        
        // 다운로드 카운트 증가
        $attachment->increment('download_count');
        
        // 파일 다운로드
        return $this->fileUploadService->downloadFile(
            $attachment->file_path, 
            $this->fileUploadService->getDisk(), 
            $attachment->original_name
        );
    }

    /**
     * 조회수 증가 (중복 방지)
     */
    private function incrementViewCountIfValid($post, Request $request)
    {
        // 세션 키 생성 (게시글별 고유키)
        $sessionKey = "viewed_post_{$post->getTable()}_{$post->id}";
        
        // 현재 시간
        $now = now();
        
        // 세션에서 마지막 조회 시간 확인
        $lastViewed = session($sessionKey);
        
        // 조회수 증가 조건:
        // 1. 처음 조회하는 경우 (세션에 기록 없음)
        // 2. 마지막 조회로부터 30분이 지난 경우
        if (!$lastViewed || $now->diffInMinutes($lastViewed) >= 30) {
            // 게시글 작성자 본인은 조회수 증가에서 제외 (선택사항)
            if (auth()->check() && auth()->id() === $post->member_id) {
                // 작성자 본인은 조회수 증가 안함
                session([$sessionKey => $now]);
                return;
            }
            
            // 조회수 증가
            $post->incrementViewCount();
            
            // 세션에 현재 시간 저장
            session([$sessionKey => $now]);
            
            // 로그 기록 (필요시)
            // Log::info("View count incremented", [
            //     'post_id' => $post->id,
            //     'table' => $post->getTable(),
            //     'ip' => $request->ip(),
            //     'user_agent' => $request->userAgent(),
            //     'user_id' => auth()->id(),
            // ]);
        }
    }

    /**
     * 첨부파일 삭제
     */
    public function deleteAttachment($attachmentId)
    {
        $attachment = BoardAttachment::findOrFail($attachmentId);
        
        // 권한 체크: 게시글 작성자나 관리자만 삭제 가능
        $board = Board::where('slug', $attachment->board_slug)->first();
        $postModelClass = BoardPost::forBoard($attachment->board_slug);
        $post = $postModelClass::find($attachment->post_id);
        
        $user = Auth::user();
        $canDelete = false;

        if ($user) {
            // 작성자 본인
            if ($post && $post->member_id === $user->id) {
                $canDelete = true;
            }
            // 게시판 관리 권한
            elseif ($board && $board->menu_id && can('manage', $board)) {
                $canDelete = true;
            }
            // 시스템 관리자
            elseif ($user->level >= config('member.admin_level', 200)) {
                $canDelete = true;
            }
        }

        if (!$canDelete) {
            return response()->json(['success' => false, 'message' => '파일을 삭제할 권한이 없습니다.'], 403);
        }

        try {
            $attachment->delete();
            return response()->json(['success' => true, 'message' => '파일이 삭제되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '파일 삭제 중 오류가 발생했습니다.'], 500);
        }
    }

    /**
     * 첨부파일 순서 업데이트
     */
    public function updateAttachmentSortOrder(Request $request)
    {
        $request->validate([
            'attachments' => 'required|array',
            'attachments.*.id' => 'required|integer|exists:board_attachments,id',
            'attachments.*.sort_order' => 'required|integer|min:1'
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->attachments as $attachmentData) {
                $attachment = BoardAttachment::findOrFail($attachmentData['id']);
                
                // 권한 체크: 게시글 작성자나 관리자만 수정 가능
                $board = Board::where('slug', $attachment->board_slug)->first();
                $postModelClass = BoardPost::forBoard($attachment->board_slug);
                $post = $postModelClass::find($attachment->post_id);
                
                $user = Auth::user();
                $canUpdate = false;

                if ($user) {
                    // 작성자 본인
                    if ($post && $post->member_id === $user->id) {
                        $canUpdate = true;
                    }
                    // 게시판 관리 권한
                    elseif ($board && $board->menu_id && can('manage', $board)) {
                        $canUpdate = true;
                    }
                    // 시스템 관리자
                    elseif ($user->level >= config('member.admin_level', 200)) {
                        $canUpdate = true;
                    }
                }

                if (!$canUpdate) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => '파일 순서를 변경할 권한이 없습니다.'], 403);
                }

                // 순서 업데이트
                $attachment->update(['sort_order' => $attachmentData['sort_order']]);
            }

            DB::commit();
            
            return response()->json([
                'success' => true, 
                'message' => '파일 순서가 업데이트되었습니다.',
                'updated_count' => count($request->attachments)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update attachment sort order: ' . $e->getMessage());
            
            return response()->json([
                'success' => false, 
                'message' => '파일 순서 업데이트 중 오류가 발생했습니다.'
            ], 500);
        }
    }
}
