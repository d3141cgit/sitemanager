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
    public function index(Request $request, string $slug): View
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        // 권한 체크
        if ($board->menu_id && !can('index', $board)) {
            abort(403, '게시판에 접근할 권한이 없습니다.');
        }
        
        // 서비스를 통해 데이터 조회
        $posts = $this->boardService->getFilteredPosts($board, $request);
        $notices = $this->boardService->getNotices($board);

        // SEO 데이터 구성
        $seoData = $this->buildBoardSeoData($board, $request);

        return view($this->selectView('index'), compact('board', 'posts', 'notices', 'seoData'));
    }

    /**
     * 게시글 상세
     */
    public function show(Request $request, string $slug, $id)
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        // 권한 체크
        if ($board->menu_id && !can('read', $board)) {
            abort(403, '게시글을 읽을 권한이 없습니다.');
        }
        
        // 서비스를 통해 데이터 조회
        $post = $this->boardService->getPost($board, $id);
        $comments = $this->boardService->getPostComments($board, $post->id);
        $attachments = $this->boardService->getPostAttachments($board, $post->id);
        $prevNext = $this->boardService->getPrevNextPosts($board, $post);
        
        // 조회수 증가
        $this->boardService->incrementViewCount($post, $request);

        // SEO 데이터 구성
        $seoData = $this->buildPostSeoData($board, $post, $attachments);

        return view($this->selectView('show'), array_merge(
            compact('board', 'post', 'comments', 'attachments', 'seoData'),
            $prevNext
        ));
    }

    /**
     * 게시글 작성 폼
     */
    public function create(string $slug)
    {
        $board = Board::where('slug', $slug)->firstOrFail();

        // 권한 체크
        if ($board->menu_id && !can('write', $board)) {
            abort(403, '게시글을 작성할 권한이 없습니다.');
        }

        return view($this->selectView('form'), compact('board'));
    }

    /**
     * 게시글 저장
     */
    public function store(Request $request, string $slug): RedirectResponse
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        // 권한 체크
        if ($board->menu_id && !can('write', $board)) {
            abort(403, '게시글을 작성할 권한이 없습니다.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'category' => 'nullable|string|max:50',
            'tags' => 'nullable|string',
            'options' => 'nullable|array',
            'files.*' => 'nullable|file|max:' . ($board->getSetting('max_file_size', 2048)),
            'file_names.*' => 'nullable|string|max:255',
            'file_descriptions.*' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        
        try {
            // 서비스를 통해 게시물 생성
            $post = $this->boardService->createPost($board, $validated);

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
        $post = $this->boardService->getPost($board, $id);

        // 권한 체크
        if (!$this->boardService->canManagePost($board, $post)) {
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
        $post = $this->boardService->getPost($board, $id);

        // 권한 체크
        if (!$this->boardService->canManagePost($board, $post)) {
            abort(403, '게시글을 수정할 권한이 없습니다.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'category' => 'nullable|string|max:50',
            'tags' => 'nullable|string',
            'options' => 'nullable|array',
            'files.*' => 'nullable|file|max:' . ($board->getSetting('max_file_size', 2048)),
            'file_names.*' => 'nullable|string|max:255',
            'file_descriptions.*' => 'nullable|string|max:500',
            'existing_file_names.*' => 'nullable|string|max:255',
            'existing_file_descriptions.*' => 'nullable|string|max:500',
            'removed_files' => 'nullable|string',
        ]);

        DB::beginTransaction();
        
        try {
            // 서비스를 통해 게시물 수정
            $post = $this->boardService->updatePost($board, $post, $validated);

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
        $post = $this->boardService->getPost($board, $id);

        // 권한 체크
        if (!$this->boardService->canManagePost($board, $post)) {
            abort(403, '게시글을 삭제할 권한이 없습니다.');
        }

        DB::beginTransaction();
        
        try {
            // 첨부파일 삭제
            foreach ($post->attachments as $attachment) {
                $this->boardService->deleteAttachment($attachment);
            }

            // 게시글 삭제 (댓글도 cascade로 같이 삭제됨)
            $this->boardService->deletePost($board, $post);

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
    }    /**
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
            if (Auth::check() && Auth::id() === $post->member_id) {
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
            //     'user_id' => Auth::id(),
            // ]);
        }
    }

    /**
     * 첨부파일 삭제
     */
    public function deleteAttachment($attachment_id)
    {
        $attachment = BoardAttachment::findOrFail($attachment_id);
        
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

    /**
     * Slug 중복 확인
     */
    public function checkSlug(Request $request, string $boardSlug)
    {
        $board = Board::where('slug', $boardSlug)->firstOrFail();
        
        $request->validate([
            'slug' => 'required|string|max:200',
            'post_id' => 'nullable|integer'
        ]);

        $slug = $request->input('slug');
        $postId = $request->input('post_id');

        $postModelClass = BoardPost::forBoard($board->slug);
        
        $query = $postModelClass::where('slug', $slug);
        
        // 수정 시에는 현재 게시글 제외
        if ($postId) {
            $query->where('id', '!=', $postId);
        }
        
        $exists = $query->exists();

        return response()->json([
            'available' => !$exists,
            'message' => $exists ? 'This slug is already taken.' : 'This slug is available.',
            'suggested_slug' => $exists ? $this->generateAlternativeSlug($board, $slug, $postId) : null
        ]);
    }

    /**
     * 대안 slug 생성
     */
    private function generateAlternativeSlug(Board $board, string $baseSlug, ?int $excludePostId = null): string
    {
        $postModelClass = BoardPost::forBoard($board->slug);
        $counter = 1;
        
        do {
            $alternativeSlug = $baseSlug . '-' . $counter;
            $query = $postModelClass::where('slug', $alternativeSlug);
            
            if ($excludePostId) {
                $query->where('id', '!=', $excludePostId);
            }
            
            $exists = $query->exists();
            $counter++;
        } while ($exists);
        
        return $alternativeSlug;
    }

    /**
     * 제목에서 slug 생성
     */
    public function generateSlugFromTitle(Request $request, string $boardSlug)
    {
        $board = Board::where('slug', $boardSlug)->firstOrFail();
        
        $request->validate([
            'title' => 'required|string|max:500'
        ]);

        // 임시 BoardPost 인스턴스 생성하여 slug 생성
        $postModelClass = BoardPost::forBoard($board->slug);
        $tempPost = new $postModelClass();
        $tempPost->title = $request->input('title');
        
        $generatedSlug = $tempPost->generateSlug();

        return response()->json([
            'slug' => $generatedSlug
        ]);
    }

    /**
     * 내용에서 excerpt 생성
     */
    public function generateExcerptFromContent(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'length' => 'nullable|integer|min:50|max:1000'
        ]);

        $content = $request->input('content');
        $length = $request->input('length', 200);
        
        // HTML 태그 제거하고 요약 생성
        $plainText = strip_tags($content);
        $excerpt = Str::limit($plainText, $length);

        return response()->json([
            'excerpt' => $excerpt
        ]);
    }

    /**
     * 게시판의 SEO 데이터 구성
     */
    private function buildBoardSeoData($board, $request)
    {
        $siteName = config_get('SITE_NAME');
        
        // 검색이나 카테고리 필터가 적용된 경우
        $searchTerm = $request->get('search');
        $category = $request->get('category');
        
        // 제목 구성
        $title = $board->name;
        if ($searchTerm) {
            $title = "'{$searchTerm}' 검색결과 | " . $board->name;
        } elseif ($category) {
            $title = $category . ' | ' . $board->name;
        }
        $title .= ' | ' . $siteName;
        
        // 설명 구성
        $description = $board->description;
        if (!$description) {
            if ($searchTerm) {
                $description = "'{$searchTerm}'에 대한 검색 결과를 {$board->name}에서 찾아보세요.";
            } elseif ($category) {
                $description = "{$board->name}의 {$category} 카테고리 게시물들을 확인하세요.";
            } else {
                $description = "{$board->name} 게시판의 최신 게시물들을 확인하세요.";
            }
        }
        
        // 키워드
        $keywords = [$board->name];
        if ($searchTerm) {
            $keywords[] = $searchTerm;
        }
        if ($category) {
            $keywords[] = $category;
        }
        $keywords[] = $siteName;
        
        // URL
        $boardUrl = route('board.index', $board->slug);
        $currentUrl = $request->fullUrl();
        
        // 게시판 대표 이미지 (메뉴에서 가져오기)
        $seoImage = null;
        if ($board->menu_id) {
            $menu = \SiteManager\Models\Menu::find($board->menu_id);
            if ($menu && !empty($menu->images)) {
                $images = is_array($menu->images) 
                    ? $menu->images 
                    : json_decode($menu->images, true);
                
                if (is_array($images)) {
                    // SEO 이미지 우선 사용
                    if (isset($images['seo']['url'])) {
                        $seoImage = \SiteManager\Services\FileUploadService::url($images['seo']['url']);
                    }
                    // 썸네일 이미지 사용
                    elseif (isset($images['thumbnail']['url'])) {
                        $seoImage = \SiteManager\Services\FileUploadService::url($images['thumbnail']['url']);
                    }
                    // 다른 이미지 사용
                    else {
                        $firstCategory = array_values($images)[0];
                        if (isset($firstCategory['url'])) {
                            $seoImage = \SiteManager\Services\FileUploadService::url($firstCategory['url']);
                        }
                    }
                }
            }
        }
        
        // 기본 이미지
        if (!$seoImage) {
            $seoImage = asset('images/logo.svg');
        }
        
        return [
            'title' => $title,
            'description' => $description,
            'keywords' => implode(', ', array_unique($keywords)),
            'og_title' => $board->name,
            'og_description' => $description,
            'og_image' => $seoImage,
            'og_url' => $currentUrl,
            'canonical_url' => $boardUrl,
            'og_type' => 'website',
        ];
    }

    /**
     * 게시글의 SEO 데이터 구성
     */
    private function buildPostSeoData($board, $post, $attachments)
    {
        $siteName = config_get('SITE_NAME');
        
        // 기본 제목 구성: 게시글 제목 | 게시판명 | 사이트명
        $title = $post->title . ' | ' . $board->name . ' | ' . $siteName;
        
        // 설명: excerpt가 있으면 사용, 없으면 본문에서 추출
        $description = $post->excerpt;
        if (!$description) {
            $plainText = strip_tags($post->content);
            $description = Str::limit($plainText, 160);
        }
        
        // 키워드: 게시글 제목, 게시판명, 카테고리 등
        $keywords = [$post->title, $board->name];
        if ($post->category) {
            $keywords[] = $post->category;
        }
        $keywords[] = $siteName;
        
        // URL 구성
        $postUrl = route('board.show', [$board->slug, $post->slug ?: $post->id]);
        
        // 첨부 이미지에서 SEO 이미지 찾기
        $seoImage = $this->findSeoImageFromAttachments($attachments);
        
        // 기본 이미지가 없으면 사이트 기본 이미지 사용
        if (!$seoImage) {
            $seoImage = asset('images/logo.svg');
        }
        
        return [
            'title' => $title,
            'description' => $description,
            'keywords' => implode(', ', array_unique($keywords)),
            'og_title' => $post->title,
            'og_description' => $description,
            'og_image' => $seoImage,
            'og_url' => $postUrl,
            'canonical_url' => $postUrl,
            'og_type' => 'article',
            'article_author' => $post->author,
            'article_published_time' => $post->created_at->toISOString(),
            'article_modified_time' => $post->updated_at->toISOString(),
            'article_section' => $board->name,
            'article_tag' => $post->category,
        ];
    }

    /**
     * 첨부파일에서 SEO용 이미지 찾기
     */
    private function findSeoImageFromAttachments($attachments)
    {
        if (!$attachments || $attachments->isEmpty()) {
            return null;
        }
        
        // 이미지 첨부파일만 필터링
        $imageAttachments = $attachments->filter(function($attachment) {
            $mimeType = $attachment->mime_type ?? '';
            return str_starts_with($mimeType, 'image/');
        });
        
        if ($imageAttachments->isEmpty()) {
            return null;
        }
        
        // 정렬 순서대로 첫 번째 이미지 사용 (보통 대표 이미지)
        $firstImage = $imageAttachments->sortBy('sort_order')->first();
        
        if ($firstImage && $firstImage->file_path) {
            // FileUploadService를 사용해서 전체 URL 생성
            return \SiteManager\Services\FileUploadService::url($firstImage->file_path);
        }
        
        return null;
    }
}
