<?php

namespace SiteManager\Http\Controllers\SiteManager;

use SiteManager\Http\Controllers\Controller;
use SiteManager\Models\EditorImage;
use SiteManager\Models\BoardAttachment;
use SiteManager\Models\Board;
use SiteManager\Models\BoardPost;
use SiteManager\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SiteManagerFileController extends Controller
{
    public function __construct(
        private FileUploadService $fileUploadService
    ) {}

    /**
     * 에디터 이미지 관리 페이지
     */
    public function editorImages(Request $request): View
    {
        $query = EditorImage::query();

        // 필터링
        if ($request->filled('board_slug')) {
            $query->where('reference_slug', $request->board_slug);
        }

        if ($request->filled('used_status')) {
            if ($request->used_status === 'used') {
                $query->where('is_used', true);
            } elseif ($request->used_status === 'unused') {
                $query->where('is_used', false);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                  ->orWhere('filename', 'like', "%{$search}%");
            });
        }

        // 정렬 처리
        $orderby = $request->get('orderby', 'created_at');
        $desc = $request->get('desc', '1');
        
        // 허용된 정렬 필드 목록
        $allowedOrderBy = ['id', 'original_name', 'filename', 'size', 'created_at'];
        
        if (in_array($orderby, $allowedOrderBy)) {
            if ($desc === '1') {
                $query->orderBy($orderby, 'desc');
            } else {
                $query->orderBy($orderby, 'asc');
            }
        } else {
            // 기본 정렬
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', config('sitemanager.ui.pagination_per_page', 20));
        $perPage = min(max((int)$perPage, 1), 100); // 1-100 범위로 제한

        $images = $query->paginate($perPage)->appends($request->query());
        $boards = Board::orderBy('name')->get();

        return view('sitemanager::sitemanager.files.editor-images', compact('images', 'boards'));
    }

    /**
     * 게시판 첨부파일 관리 페이지
     */
    public function boardAttachments(Request $request): View
    {
        $query = BoardAttachment::query();

        // 필터링
        if ($request->filled('board_slug')) {
            $boardSlug = $request->board_slug;
            $query->where('board_slug', $boardSlug);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                  ->orWhere('filename', 'like', "%{$search}%");
            });
        }

        // 정렬 처리
        $orderby = $request->get('orderby', 'created_at');
        $desc = $request->get('desc', '1');
        
        // 허용된 정렬 필드 목록
        $allowedOrderBy = ['id', 'original_name', 'filename', 'file_size', 'created_at'];
        
        if (in_array($orderby, $allowedOrderBy)) {
            if ($desc === '1') {
                $query->orderBy($orderby, 'desc');
            } else {
                $query->orderBy($orderby, 'asc');
            }
        } else {
            // 기본 정렬
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', config('sitemanager.ui.pagination_per_page', 20));
        $perPage = min(max((int)$perPage, 1), 100); // 1-100 범위로 제한

        $attachments = $query->paginate($perPage)->appends($request->query());
        $boards = Board::orderBy('name')->get();

        return view('sitemanager::sitemanager.files.board-attachments', compact('attachments', 'boards'));
    }

    /**
     * 에디터 이미지 사용 여부 체크
     */
    public function checkEditorImageUsage(EditorImage $image)
    {
        try {
            $found = false;
            $foundInModules = [];
            
            // 1. 게시판에서 검색
            $boards = Board::where('status', 'active')->get();
            
            foreach ($boards as $board) {
                $postModelClass = BoardPost::forBoard($board->slug);
                
                $postsWithImage = $postModelClass::where('content', 'LIKE', '%' . $image->filename . '%')
                    // ->orWhere('content', 'LIKE', '%' . $image->original_name . '%')
                    ->select('id', 'title', 'created_at')
                    ->get();
                
                if ($postsWithImage->count() > 0) {
                    $found = true;
                    $foundInModules[] = [
                        'type' => 'board',
                        'title' => $board->name,
                        'slug' => $board->slug,
                        'posts_count' => $postsWithImage->count(),
                        'posts' => $postsWithImage->map(function($post) use ($board) {
                            return [
                                'id' => $post->id,
                                'title' => $post->title,
                                'created_at' => $post->created_at->format('Y-m-d'),
                                'url' => "/sitemanager/board/{$board->slug}/posts/{$post->id}"
                            ];
                        })->toArray()
                    ];
                }
            }
            
            // 2. 다른 모듈들에서 검색 (확장 가능)
            $otherModules = $this->checkImageUsageInOtherModules($image);
            $foundInModules = array_merge($foundInModules, $otherModules);
            
            if (!empty($otherModules)) {
                $found = true;
            }
            
            return response()->json([
                'found' => $found,
                'boards' => $foundInModules, // boards라는 이름이지만 실제로는 모든 모듈 포함
                'filename' => $image->filename,
                'original_name' => $image->original_name
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to check image usage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 다른 모듈들에서 이미지 사용 여부 확인 (확장 가능)
     */
    private function checkImageUsageInOtherModules(EditorImage $image): array
    {
        $foundInModules = [];
        
        // 향후 다른 모듈 추가 시 아래 패턴으로 추가:
        
        /*
        // Example: Page 모듈 추가
        try {
            if (Schema::hasColumn('pages', 'content')) {
                $pagesWithImage = \SiteManager\Models\Page::where('content', 'LIKE', '%' . $image->filename . '%')
                    ->orWhere('content', 'LIKE', '%' . $image->original_name . '%')
                    ->select('id', 'title', 'created_at')
                    ->get();
                    
                if ($pagesWithImage->count() > 0) {
                    $foundInModules[] = [
                        'type' => 'page',
                        'title' => 'Pages',
                        'slug' => 'page',
                        'posts_count' => $pagesWithImage->count(),
                        'posts' => $pagesWithImage->map(function($page) {
                            return [
                                'id' => $page->id,
                                'title' => $page->title,
                                'created_at' => $page->created_at->format('Y-m-d'),
                                'url' => "/sitemanager/pages/{$page->id}/edit"
                            ];
                        })->toArray()
                    ];
                }
            }
        } catch (\Exception $e) {
            // 모듈이 없거나 오류 발생시 무시
        }
        */
        
        /*
        // Example: Notice 모듈 추가
        try {
            $noticesWithImage = \SiteManager\Models\Notice::where('content', 'LIKE', '%' . $image->filename . '%')
                ->orWhere('content', 'LIKE', '%' . $image->original_name . '%')
                ->select('id', 'title', 'created_at')
                ->get();
                
            if ($noticesWithImage->count() > 0) {
                $foundInModules[] = [
                    'type' => 'notice',
                    'title' => 'Notices',
                    'slug' => 'notice',
                    'posts_count' => $noticesWithImage->count(),
                    'posts' => $noticesWithImage->map(function($notice) {
                        return [
                            'id' => $notice->id,
                            'title' => $notice->title,
                            'created_at' => $notice->created_at->format('Y-m-d'),
                            'url' => "/sitemanager/notices/{$notice->id}/edit"
                        ];
                    })->toArray()
                ];
            }
        } catch (\Exception $e) {
            // 모듈이 없거나 오류 발생시 무시
        }
        */
        
        return $foundInModules;
    }

    /**
     * 에디터 이미지 사용 상태 업데이트
     */
    public function updateEditorImageUsage(EditorImage $image, Request $request)
    {
        $request->validate([
            'is_used' => 'required|boolean'
        ]);

        try {
            DB::beginTransaction();

            $updateData = ['is_used' => $request->is_used];

            // is_used를 true로 설정할 때, reference 정보가 잘못되어 있다면 올바른 정보로 업데이트
            if ($request->is_used === true) {
                $correctReference = $this->findCorrectReference($image);
                if ($correctReference) {
                    $updateData['reference_type'] = $correctReference['reference_type'];
                    $updateData['reference_slug'] = $correctReference['reference_slug'];
                    $updateData['reference_id'] = $correctReference['reference_id'];
                }
            }

            $image->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Usage status updated successfully',
                'is_used' => $image->is_used,
                'reference_updated' => isset($correctReference) ? true : false
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update usage status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 이미지가 실제로 사용되고 있는 올바른 reference 정보 찾기
     */
    private function findCorrectReference(EditorImage $image): ?array
    {
        // 1. 게시판에서 검색
        $boards = Board::where('status', 'active')->get();
        
        foreach ($boards as $board) {
            $postModelClass = BoardPost::forBoard($board->slug);
            
            $postsWithImage = $postModelClass::where('content', 'LIKE', '%' . $image->filename . '%')
                ->orWhere('content', 'LIKE', '%' . $image->original_name . '%')
                ->select('id')
                ->first();
            
            if ($postsWithImage) {
                return [
                    'reference_type' => 'board',
                    'reference_slug' => $board->slug,
                    'reference_id' => $postsWithImage->id
                ];
            }
        }
        
        // 2. 다른 모듈에서 검색 (향후 모듈 추가 시 아래 패턴으로 추가)
        
        /*
        // Example: Page 모듈에서 검색
        try {
            if (Schema::hasColumn('pages', 'content')) {
                $pageWithImage = \SiteManager\Models\Page::where('content', 'LIKE', '%' . $image->filename . '%')
                    ->orWhere('content', 'LIKE', '%' . $image->original_name . '%')
                    ->select('id')
                    ->first();
                    
                if ($pageWithImage) {
                    return [
                        'reference_type' => 'page',
                        'reference_slug' => 'page',
                        'reference_id' => $pageWithImage->id
                    ];
                }
            }
        } catch (\Exception $e) {
            // 모듈이 없는 경우 무시
        }
        */
        
        /*
        // Example: Notice 모듈에서 검색
        try {
            $noticeWithImage = \SiteManager\Models\Notice::where('content', 'LIKE', '%' . $image->filename . '%')
                ->orWhere('content', 'LIKE', '%' . $image->original_name . '%')
                ->select('id')
                ->first();
                
            if ($noticeWithImage) {
                return [
                    'reference_type' => 'notice',
                    'reference_slug' => 'notice', 
                    'reference_id' => $noticeWithImage->id
                ];
            }
        } catch (\Exception $e) {
            // 모듈이 없는 경우 무시
        }
        */
        
        return null;
    }
    public function deleteEditorImage(EditorImage $image): RedirectResponse
    {
        try {
            // 사용 중인 이미지는 삭제 금지
            if ($image->is_used) {
                return back()->with('error', 'Cannot delete image that is currently in use. Please mark it as unused first.');
            }

            DB::beginTransaction();

            // 파일 삭제
            $this->deleteFile($image->path);

            // 데이터베이스 레코드 삭제
            $image->delete();

            DB::commit();

            return back()->with('success', 'Editor image deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete editor image {$image->id}: " . $e->getMessage());
            
            return back()->with('error', 'Failed to delete editor image: ' . $e->getMessage());
        }
    }

    /**
     * 게시판 첨부파일 삭제
     */
    public function deleteBoardAttachment(BoardAttachment $attachment): RedirectResponse
    {
        try {
            DB::beginTransaction();

            // 파일 삭제
            $this->deleteFile($attachment->file_path);

            // 데이터베이스 레코드 삭제
            $attachment->delete();

            DB::commit();

            return back()->with('success', 'Board attachment deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete board attachment {$attachment->id}: " . $e->getMessage());
            
            return back()->with('error', 'Failed to delete board attachment: ' . $e->getMessage());
        }
    }

    /**
     * 에디터 이미지 교체
     */
    public function replaceEditorImage(Request $request, EditorImage $image): RedirectResponse
    {
        $request->validate([
            'replacement_file' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120' // 5MB
        ]);

        try {
            DB::beginTransaction();

            $file = $request->file('replacement_file');
            
            // 기존 파일 삭제
            $this->deleteFile($image->path);
            
            // 새 파일을 같은 경로 구조로 업로드
            $uploadFolder = 'editor/images';
            if ($image->reference_type === 'board' && $image->reference_slug) {
                $uploadFolder = "editor/images/{$image->reference_slug}";
            }
            
            // 새 파일 업로드
            $uploadResult = $this->fileUploadService->uploadImage($file, $uploadFolder);
            
            // 새 파일을 기존 파일명으로 복사
            if (isset($uploadResult['path'])) {
                $oldS3Key = $image->path;
                $newS3Key = $uploadResult['path'];
                
                // URL에서 키 추출
                if (strpos($oldS3Key, 'https://') === 0) {
                    $urlParts = parse_url($oldS3Key);
                    $oldS3Key = ltrim($urlParts['path'], '/');
                }
                
                if (strpos($newS3Key, 'https://') === 0) {
                    $urlParts = parse_url($newS3Key);
                    $newS3Key = ltrim($urlParts['path'], '/');
                }
                
                // 새 파일을 기존 경로로 복사
                Storage::disk('s3')->copy($newS3Key, $oldS3Key);
                
                // 임시 파일 삭제
                Storage::disk('s3')->delete($newS3Key);
            }
            
            // 데이터베이스 메타데이터 업데이트 (경로는 동일하게 유지)
            $image->update([
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'updated_at' => now()
            ]);

            DB::commit();

            return back()->with('success', 'Image replaced successfully! Please hard refresh (Ctrl+F5) to see changes.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to replace editor image {$image->id}: " . $e->getMessage());
            
            return back()->with('error', 'Failed to replace editor image: ' . $e->getMessage());
        }
    }

    /**
     * 게시판 첨부파일 교체
     */
    public function replaceBoardAttachment(Request $request, BoardAttachment $attachment): RedirectResponse
    {
        $request->validate([
            'replacement_file' => 'required|file|max:51200' // 50MB
        ]);

        try {
            DB::beginTransaction();

            $file = $request->file('replacement_file');
            
            // 기존 파일 삭제
            $this->deleteFile($attachment->file_path);

            // 새 파일 업로드
            $uploadResult = $this->fileUploadService->uploadFile($file, 'attachments');

            // 데이터베이스 업데이트
            $attachment->update([
                'original_name' => $uploadResult['name'],
                'filename' => $uploadResult['filename'],
                'file_path' => $uploadResult['path'],
                'file_size' => $uploadResult['size'],
                'mime_type' => $uploadResult['mime_type']
            ]);

            DB::commit();

            return back()->with('success', 'Board attachment replaced successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to replace board attachment {$attachment->id}: " . $e->getMessage());
            
            return back()->with('error', 'Failed to replace board attachment: ' . $e->getMessage());
        }
    }

    /**
     * 파일 삭제 (S3 또는 로컬)
     */
    private function deleteFile(string $filePath): void
    {
        try {
            if (strpos($filePath, 'https://') === 0) {
                // S3 URL인 경우
                $this->fileUploadService->deleteFile($filePath);
            } else {
                // 로컬 파일인 경우
                if (Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to delete file {$filePath}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 게시물 내용에서 이미지 URL 업데이트
     */
    private function updateImageUrlInContent(EditorImage $image, string $newUrl): void
    {
        if ($image->reference_type !== 'board' || !$image->reference_slug || !$image->reference_id) {
            return;
        }

        $postModelClass = BoardPost::forBoard($image->reference_slug);
        $post = $postModelClass::find($image->reference_id);

        if (!$post) {
            return;
        }

        // 기존 이미지 URL을 새 URL로 교체
        $oldUrl = $image->url;
        $content = str_replace($oldUrl, $newUrl, $post->content);
        
        if ($content !== $post->content) {
            $post->update(['content' => $content]);
        }
    }

    /**
     * 게시물 보기 (새 창용)
     */
    public function viewPost($boardSlug, $postId)
    {
        try {
            $postModelClass = BoardPost::forBoard($boardSlug);
            $post = $postModelClass::findOrFail($postId);
            
            return view('sitemanager::sitemanager.files.post-view', [
                'post' => $post,
                'boardSlug' => $boardSlug
            ]);
        } catch (\Exception $e) {
            return response()->view('sitemanager::sitemanager.files.post-view', [
                'error' => 'Post not found or access denied.',
                'boardSlug' => $boardSlug
            ], 404);
        }
    }
}
