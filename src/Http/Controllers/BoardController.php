<?php

namespace SiteManager\Http\Controllers;

use SiteManager\Models\Board;
use SiteManager\Models\BoardPost;
use SiteManager\Models\BoardComment;
use SiteManager\Models\BoardAttachment;
use SiteManager\Models\EditorImage;
use SiteManager\Services\BoardService;
use SiteManager\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
        
        // 1. 프로젝트의 스킨 뷰 확인 (예: views/board/default/index.blade.php 또는 views/board/gallery/index.blade.php)
        $skinViewPath = resource_path("views/board/{$skin}/{$viewName}.blade.php");
        
        if (file_exists($skinViewPath)) {
            return "board.{$skin}.{$viewName}";
        }
        
        // 2. 스킨이 'default'가 아닌 경우에만 default 스킨으로 fallback (default 스킨이 있는 경우에만)
        if ($skin !== 'default') {
            $defaultViewPath = resource_path("views/board/default/{$viewName}.blade.php");
            if (file_exists($defaultViewPath)) {
                return "board.default.{$viewName}";
            }
        }
        
        // 3. 프로젝트에 패키지 뷰를 override한 버전이 있는지 확인
        $projectOverridePath = resource_path("views/sitemanager/board/{$viewName}.blade.php");
        if (file_exists($projectOverridePath)) {
            return "sitemanager.board.{$viewName}";
        }
        
        // 4. 패키지의 기본 뷰 사용 (예: sitemanager::board.index)
        return "sitemanager::board.{$viewName}";
    }
    
    /**
     * 프로젝트 레이아웃 경로를 반환합니다.
     */
    private function getLayoutPath(): string
    {
        // 프로젝트에 app 레이아웃이 있는지 확인
        $projectLayoutPath = resource_path('views/layouts/app.blade.php');
        
        if (file_exists($projectLayoutPath)) {
            return 'layouts.app';
        }
        
        // 기본적으로 패키지 레이아웃 사용
        return 'sitemanager::layouts.app';
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
     * 게시판 메인 (게시글 목록)
     */
    public function index(Request $request, string $slug): View
    {
        $board = Board::with('menu')->where('slug', $slug)->firstOrFail();
        
        // 권한 체크
        if ($board->menu_id && !can('index', $board)) {
            abort(403, '게시판에 접근할 권한이 없습니다.');
        }
        
        // 서비스를 통해 데이터 조회
        $posts = $this->boardService->getFilteredPosts($board, $request);
        $notices = $this->boardService->getNotices($board);

        // SEO 데이터 구성
        $seoData = $this->buildBoardSeoData($board, $request);

        return view($this->selectView('index'), compact('board', 'posts', 'notices', 'seoData') + [
            'currentMenuId' => $board->menu_id, // NavigationComposer에서 사용할 현재 메뉴 ID
            'currentSkin' => $board->skin ?? 'default', // 현재 스킨 정보
            'layoutPath' => $this->getLayoutPath() // 프로젝트 레이아웃 경로
        ]);
    }

    /**
     * 게시글 상세
     */
    public function show(Request $request, string $slug, $id)
    {
        $board = Board::with('menu')->where('slug', $slug)->firstOrFail();
        
        // 권한 체크
        if ($board->menu_id && !can('read', $board)) {
            abort(403, 'You do not have permission to read this post.');
        }
        
        // 서비스를 통해 데이터 조회
        $post = $this->boardService->getPost($board, $id);
        
        // 비밀글 접근 권한 확인
        if ($post->isSecret() && !$post->canAccess(Auth::id())) {
            // 비밀번호 입력 폼 표시 (스킨 적용)
            return view($this->selectView('password-form'), array_merge(compact('board', 'post'), [
                'currentMenuId' => $board->menu_id, // NavigationComposer에서 사용할 현재 메뉴 ID
                'layoutPath' => $this->getLayoutPath(), // 프로젝트 레이아웃 경로
                'additionalBreadcrumb' => [
                    'title' => '[Private] '.$post->title,
                    'url' => null
                ]
            ]));
        }
        
        $comments = $this->boardService->getPostComments($board, $post->id);
        $attachments = $this->boardService->getPostAttachments($board, $post->id);
        $prevNext = $this->boardService->getPrevNextPosts($board, $post);
        
        // 조회수 증가
        $this->boardService->incrementViewCount($post, $request);

        // 게시글 관련 권한 계산
        $permissions = $this->calculatePostPermissions($board, $post);

        // SEO 데이터 구성
        $seoData = $this->buildPostSeoData($board, $post, $attachments);

        return view($this->selectView('show'), array_merge(
            compact('board', 'post', 'comments', 'attachments', 'seoData'),
            $prevNext,
            $permissions,
            [
                'currentMenuId' => $board->menu_id, // NavigationComposer에서 사용할 현재 메뉴 ID
                'currentSkin' => $board->skin ?? 'default', // 현재 스킨 정보
                'layoutPath' => $this->getLayoutPath(), // 프로젝트 레이아웃 경로
                'additionalBreadcrumb' => [
                    'title' => $post->title,
                    'url' => null
                ]
            ]
        ));
    }

    /**
     * 게시글 관련 권한 계산
     */
    private function calculatePostPermissions(Board $board, $post): array
    {
        $user = Auth::user();
        
        $canEdit = false;
        $canDelete = false;
        $canWriteComments = false;
        $canUploadFiles = false;
        
        if ($board->menu_id) {
            // 메뉴에서 가져온 최종 권한들 (이미 member, group, level, admin의 최대값으로 계산됨)
            $canWrite = can('write', $board);
            $canManage = can('manage', $board);
            $canWriteComments = can('writeComments', $board);
            $canUploadFiles = can('uploadFiles', $board);
            
            // 작성자 본인 여부 (로그인한 사용자이면서 member_id가 일치하는 경우만)
            $isAuthor = $user && $post->member_id && $post->member_id === $user->id;
            
            // 수정 권한: 메뉴 관리 권한 OR (작성자 본인 && 글 작성 권한)
            $canEdit = $canManage || ($isAuthor && $canWrite);
            
            // 삭제 권한: 메뉴 관리 권한 OR (작성자 본인 && 글 작성 권한)
            $canDelete = $canManage || ($isAuthor && $canWrite);
            
            // 댓글 작성 권한: 게시판 설정 허용 && 메뉴 권한
            if ($board->getSetting('allow_comments', true)) {
                $canWriteComments = $canWriteComments;
            } else {
                $canWriteComments = false;
            }
            
            // 파일 업로드 권한: 게시판 설정 허용 && 메뉴 권한
            if ($board->getSetting('allow_file_upload', false)) {
                $canUploadFiles = $canUploadFiles;
            } else {
                $canUploadFiles = false;
            }
        }
        
        return [
            'canEdit' => $canEdit,
            'canDelete' => $canDelete,
            'canWriteComments' => $canWriteComments,
            'canUploadFiles' => $canUploadFiles,
        ];
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
        ];
    }

    /**
     * 게시글 작성 폼
     */
    public function create(string $slug)
    {
        $board = Board::where('slug', $slug)->firstOrFail();

        // 권한 체크
        if ($board->menu_id && !can('write', $board)) {
            abort(403, 'You do not have permission to write a post.');
        }

        return view($this->selectView('form'), compact('board') + [
            'currentMenuId' => $board->menu_id, // NavigationComposer에서 사용할 현재 메뉴 ID
            'layoutPath' => $this->getLayoutPath() // 프로젝트 레이아웃 경로
        ]);
    }

    /**
     * 게시글 저장
     */
    public function store(Request $request, string $slug): RedirectResponse
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        
        // 권한 체크
        if ($board->menu_id && !can('write', $board)) {
            abort(403, 'You do not have permission to write a post.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'slug' => 'nullable|string|max:200',
            'excerpt' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:50',
            'categories' => 'nullable|array', // categories[] 배열 지원
            'categories.*' => 'nullable|string|max:50', // 각 카테고리 항목 검증
            'tags' => 'nullable|string',
            'secret_password' => 'nullable|string|min:4|max:20',
            'options' => 'nullable|array',
            'files.*' => 'nullable|file|max:' . ($board->getSetting('max_file_size', 2048)),
            'file_names.*' => 'nullable|string|max:255',
            'file_descriptions.*' => 'nullable|string|max:500',
        ]);

        // categories 배열이 있으면 category 필드로 변환
        if (!empty($validated['categories'])) {
            $validated['category'] = '|' . implode('|', $validated['categories']) . '|';
        } elseif (!empty($validated['category'])) {
            // 단일 category가 있으면 |category| 형식으로 변환
            $validated['category'] = '|' . $validated['category'] . '|';
        }

        DB::beginTransaction();
        
        try {
            // 서비스를 통해 게시물 생성
            $post = $this->boardService->createPost($board, $validated);
            
            // 비밀번호 처리
            if (!empty($validated['secret_password'])) {
                $post->setSecretPassword($validated['secret_password']);
                $post->save();
            }

            // 파일 업로드 처리
            if ($request->hasFile('files') && $board->allowsFileUpload()) {
                $this->handleFileUploads(
                    $request->file('files'), 
                    $board, 
                    $post,
                    $request->input('file_names', []),
                    $request->input('file_descriptions', []),
                    $request->input('file_categories', [])
                );
            }

            // 에디터에서 업로드된 파일들을 첨부파일로 등록
            // $this->extractAndRegisterEditorFiles($post->content, $post);
            
            // 에디터 이미지를 사용됨으로 표시
            EditorImage::markAsUsedByContent($post->content, 'board', $post->board->slug, $post->id);
            
            // 임시 참조 ID가 있다면 실제 ID로 업데이트 (create시)
            if ($request->has('temp_reference_id')) {
                $tempId = $request->input('temp_reference_id');
                if ($tempId < 0) {
                    EditorImage::updateTempReference($tempId, $post->id);
                }
            }

            DB::commit();

            return redirect()
                ->route('board.show', [$slug, $post->slug ?: $post->id])
                ->with('success', 'The post has been created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'An error occurred while creating the post: ' . $e->getMessage());
        }
    }

    /**
     * 게시글 수정 폼
     */
    public function edit(string $slug, $id)
    {
        $board = Board::where('slug', $slug)->firstOrFail();
        $post = $this->boardService->getPost($board, $id);

        // 권한 체크
        if (!$this->boardService->canManagePost($board, $post)) {
            abort(403, 'You do not have permission to edit this post.');
        }

        // 비밀글 접근 권한 확인 (작성자가 아닌 경우)
        if ($post->isSecret() && !$post->canAccess(Auth::id()) && $post->member_id !== Auth::id()) {
            return redirect()->route('board.show', [$slug, $id]);
        }

        return view($this->selectView('form'), compact('board', 'post') + [
            'currentMenuId' => $board->menu_id, // NavigationComposer에서 사용할 현재 메뉴 ID
            'layoutPath' => $this->getLayoutPath() // 프로젝트 레이아웃 경로
        ]);
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
            abort(403, 'You do not have permission to edit this post.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'slug' => 'nullable|string|max:200',
            'excerpt' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:50',
            'categories' => 'nullable|array', // categories[] 배열 지원
            'categories.*' => 'nullable|string|max:50', // 각 카테고리 항목 검증
            'tags' => 'nullable|string',
            'secret_password' => 'nullable|string|min:4|max:20',
            'remove_secret_password' => 'nullable|boolean',
            'options' => 'nullable|array',
            'files.*' => 'nullable|file|max:' . ($board->getSetting('max_file_size', 2048)),
            'file_names.*' => 'nullable|string|max:255',
            'file_descriptions.*' => 'nullable|string|max:500',
            'existing_file_names.*' => 'nullable|string|max:255',
            'existing_file_descriptions.*' => 'nullable|string|max:500',
            'removed_files' => 'nullable|string',
        ]);

        // categories 배열이 있으면 category 필드로 변환
        if (!empty($validated['categories'])) {
            $validated['category'] = '|' . implode('|', $validated['categories']) . '|';
        } elseif (!empty($validated['category'])) {
            // 단일 category가 있으면 |category| 형식으로 변환
            $validated['category'] = '|' . $validated['category'] . '|';
        }

        DB::beginTransaction();
        
        try {
            // 서비스를 통해 게시물 수정
            $post = $this->boardService->updatePost($board, $post, $validated);
            
            // 비밀번호 처리
            if ($request->has('remove_secret_password') && $request->remove_secret_password) {
                // 비밀번호 해제
                $post->secret_password = null;
                $post->save();
            } elseif (!empty($validated['secret_password'])) {
                // 새 비밀번호 설정
                $post->setSecretPassword($validated['secret_password']);
                $post->save();
            }

            // 파일 처리
            if ($board->allowsFileUpload()) {
                // 기존 첨부파일 정보 업데이트
                $existingFileNames = $request->input('existing_file_names', []);
                $existingFileDescriptions = $request->input('existing_file_descriptions', []);
                $existingFileCategories = $request->input('existing_file_categories', []);
                
                foreach ($existingFileNames as $attachmentId => $originalName) {
                    $attachment = $post->attachments()->find($attachmentId);
                    if ($attachment) {
                        $attachment->update([
                            'original_name' => $originalName ?: $attachment->original_name,
                            'description' => $existingFileDescriptions[$attachmentId] ?? $attachment->description,
                            'category' => $existingFileCategories[$attachmentId] ?? $attachment->category,
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
                        $request->input('file_descriptions', []),
                        $request->input('file_categories', [])
                    );
                }
            }

            // 에디터에서 업로드된 파일들을 첨부파일로 등록
            // $this->extractAndRegisterEditorFiles($post->content, $post);
            
            // 에디터 이미지를 사용됨으로 표시
            EditorImage::markAsUsedByContent($post->content, 'board', $post->board->slug, $post->id);

            DB::commit();

            return redirect()
                ->route('board.show', [$slug, $post->slug ?: $post->id])
                ->with('success', 'The post has been updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'An error occurred while updating the post: ' . $e->getMessage());
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
            abort(403, 'You do not have permission to delete this post.');
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
                ->with('success', 'The post has been deleted.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->with('error', 'An error occurred while deleting the post: ' . $e->getMessage());
        }
    }

    /**
     * 에디터에서 업로드된 파일들을 첨부파일로 등록 (주석 처리 - EditorImage 사용)
     */
    /*
    private function extractAndRegisterEditorFiles(string $content, $post)
    {
        // 에디터 이미지 경로 패턴 (S3 및 로컬 모두 포함)
        $pattern = '/\/editor\/images\/[^"\s<>]+\.(jpg|jpeg|png|gif|webp|svg|bmp)/i';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $fullPath = $match[0]; // /editor/images/2025/08/filename.jpg
                $extension = strtolower($match[1]);
                $filename = basename($fullPath);
                $relativePath = ltrim($fullPath, '/'); // editor/images/2025/08/filename.jpg
                
                // 이미 등록된 파일인지 확인
                $existingAttachment = BoardAttachment::where('post_id', $post->id)
                    ->where(function($query) use ($filename, $relativePath) {
                        $query->where('filename', $filename)
                              ->orWhere('file_path', $relativePath);
                    })
                    ->first();
                
                if (!$existingAttachment) {
                    try {
                        $attachment = new BoardAttachment();
                        $attachment->board_slug = $post->board->slug;
                        $attachment->post_id = $post->id;
                        $attachment->file_path = $relativePath;
                        $attachment->filename = $filename;
                        $attachment->original_name = $filename;
                        $attachment->file_extension = $extension;
                        $attachment->file_size = $this->getEditorFileSize($relativePath);
                        $attachment->mime_type = $this->getMimeTypeFromExtension($extension);
                        $attachment->category = 'editor_image';
                        $attachment->description = '에디터에서 업로드된 이미지';
                        $attachment->sort_order = 998;
                        $attachment->download_count = 0;
                        $attachment->save();
                        
                    } catch (\Exception $e) {
                        Log::error("에디터 이미지 등록 실패: {$filename} - " . $e->getMessage());
                    }
                }
            }
        }
    }
    */

    /**
     * 에디터 파일 크기 확인
     */
    private function getEditorFileSize(string $relativePath): int
    {
        try {
            // FileUploadService를 통해 파일 크기 확인
            $disk = $this->fileUploadService->getDisk();
            if ($disk && Storage::disk($disk)->exists($relativePath)) {
                return Storage::disk($disk)->size($relativePath);
            }
        } catch (\Exception $e) {
            Log::warning("에디터 파일 크기 확인 실패: {$relativePath} - " . $e->getMessage());
        }
        
        return 0; // 크기를 확인할 수 없는 경우
    }

    /**
     * 파일 확장자를 MIME 타입으로 변환
     */
    private function getMimeTypeFromExtension(?string $extension): ?string
    {
        if (empty($extension)) {
            return null;
        }

        $extension = strtolower(trim($extension, '.'));

        $mimeTypes = [
            // 이미지
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    private function handleFileUploads(array $files, Board $board, $post, array $fileNames = [], array $fileDescriptions = [], array $fileCategories = []): array
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
            'max_files' => $remainingSlots,
            'file_categories' => $board->getFileCategories()
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
                
                // 사용자가 선택한 카테고리가 있으면 사용, 없으면 FileUploadService에서 결정한 카테고리 사용
                $category = isset($fileCategories[$index]) && !empty($fileCategories[$index])
                    ? $fileCategories[$index]
                    : $fileInfo['category'];
                
                $attachment = BoardAttachment::create([
                    'post_id' => $post->id,
                    'board_slug' => $board->slug,
                    'filename' => $fileInfo['filename'],
                    'original_name' => $customName, // 사용자 정의 파일명 사용
                    'file_path' => $fileInfo['path'],
                    'file_extension' => $fileInfo['file_extension'],
                    'file_size' => $fileInfo['file_size'],
                    'mime_type' => $fileInfo['mime_type'],
                    'category' => $category, // 사용자가 선택한 카테고리 또는 자동 결정된 카테고리
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
            abort(403, 'You do not have permission to access this file.');
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
            return response()->json(['success' => false, 'message' => 'You do not have permission to delete this file.'], 403);
        }

        try {
            $attachment->delete();
            return response()->json(['success' => true, 'message' => 'The file has been deleted.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'An error occurred while deleting the file.'], 500);
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
                    return response()->json(['success' => false, 'message' => 'You do not have permission to change the file order.'], 403);
                }

                // 순서 업데이트
                $attachment->update(['sort_order' => $attachmentData['sort_order']]);
            }

            DB::commit();
            
            return response()->json([
                'success' => true, 
                'message' => 'The file order has been updated.',
                'updated_count' => count($request->attachments)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update attachment sort order: ' . $e->getMessage());
            
            return response()->json([
                'success' => false, 
                'message' => 'An error occurred while updating the file order.'
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
        
        // 연결된 메뉴에서 기본 SEO 데이터 가져오기 (관계를 통해)
        $menu = $board->menu; // 이미 with('menu')로 로드됨
        
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
        
        // 설명 구성 - 메뉴의 설명을 우선 사용
        $description = $board->description;
        if (!$description && $menu && $menu->description) {
            $description = $menu->description;
        }
        if (!$description) {
            if ($searchTerm) {
                $description = "Find search results for '{$searchTerm}' in {$board->name}.";
            } elseif ($category) {
                $description = "Check out posts in the {$category} category of {$board->name}.";
            } else {
                $description = "Check out the latest posts on the {$board->name} board.";
            }
        }
        
        // 키워드 - 메뉴 제목도 포함
        $keywords = [$board->name];
        if ($menu && $menu->title && $menu->title !== $board->name) {
            $keywords[] = $menu->title;
        }
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
        
        // 기본 이미지
        if (!$seoImage) {
            $seoImage = asset('images/logo.svg');
        }
        
        // JSON-LD 구조화 데이터 생성 (게시판 목록용)
        $jsonLdData = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $board->name,
            'description' => $description,
            'url' => $currentUrl,
            'image' => $seoImage,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => request()->getSchemeAndHttpHost()
            ]
        ];
        
        // 검색 결과인 경우 SearchResultsPage로 변경
        if ($searchTerm) {
            $jsonLdData['@type'] = 'SearchResultsPage';
            $jsonLdData['query'] = $searchTerm;
        }
        
        return [
            'title' => $title,
            'description' => $description,
            'keywords' => implode(', ', array_unique($keywords)),
            'og_title' => $searchTerm ? "'{$searchTerm}' Search Result" : $board->name,
            'og_description' => $description,
            'og_image' => $seoImage,
            'og_url' => $currentUrl,
            'canonical_url' => $boardUrl,
            'og_type' => 'website',
            'json_ld' => $jsonLdData,
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
        
        // JSON-LD 구조화 데이터 생성
        $jsonLdData = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $description,
            'image' => $seoImage,
            'author' => [
                '@type' => 'Person',
                'name' => $post->author
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $siteName,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => asset('images/logo.svg')
                ]
            ],
            'datePublished' => $post->created_at->toISOString(),
            'dateModified' => $post->updated_at->toISOString(),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $postUrl
            ]
        ];

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
            'json_ld' => $jsonLdData,
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

    /**
     * 비밀글 비밀번호 확인
     */
    public function verifyPassword(Request $request, string $slug, $id)
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        $board = Board::where('slug', $slug)->firstOrFail();
        $post = $this->boardService->getPost($board, $id);

        if (!$post->isSecret()) {
            return redirect()->route('board.show', [$slug, $id]);
        }

        if ($post->checkSecretPassword($request->password)) {
            $post->markPasswordVerified();
            return redirect()->route('board.show', [$slug, $id]);
        }

        return back()
            ->withInput()
            ->withErrors(['password' => 'Passwords do not match.']);
    }
}
