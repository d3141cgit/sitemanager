<?php

namespace SiteManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use SiteManager\Services\FileUploadService;
use SiteManager\Models\EditorImage;

class EditorController extends Controller
{
    protected $fileUploadService;
    
    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }
    
    /**
     * 에디터 이미지 업로드
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'upload' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120' // 5MB 제한
        ]);

        try {
            $file = $request->file('upload');
            
            // 참조 정보 수집 (request에서 전달될 수 있음)
            $referenceType = $request->input('reference_type', null);
            $referenceSlug = $request->input('reference_slug', null);
            $referenceId = $request->input('reference_id', null);

            // create 상황 (referenceId가 없음)이면 NULL 사용
            // 글 저장 시 markAsUsedByContent()가 올바른 reference_id로 업데이트함
            // Note: reference_id 컬럼이 unsigned bigint라서 음수 사용 불가
            
            // 업로드 폴더 결정 (보드별 구분 지원)
            $uploadFolder = 'editor/images';
            if ($referenceType === 'board' && !empty($referenceSlug)) {
                $uploadFolder = "editor/images/{$referenceSlug}";
            }
            
            // FileUploadService를 사용하여 이미지 업로드
            $uploadResult = $this->fileUploadService->uploadImage(
                $file, 
                $uploadFolder,
                [
                    'max_size' => 5120, // 5MB
                    'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp']
                ]
            );
            
            // Eloquent를 사용하여 메타데이터 저장
            EditorImage::create([
                'original_name' => $uploadResult['name'],
                'filename' => $uploadResult['filename'], 
                'path' => $uploadResult['path'],
                'size' => $uploadResult['size'],
                'mime_type' => $uploadResult['mime_type'],
                'uploaded_by' => Auth::id(),
                'reference_type' => $referenceType,
                'reference_slug' => $referenceSlug,
                'reference_id' => $referenceId,
                'is_used' => false // 초기에는 false, 저장시 true로 변경
            ]);
            
            // CKEditor/Summernote 응답 형식
            return response()->json([
                'url' => $uploadResult['url'],
                'uploaded' => true
            ]);
            
        } catch (\Exception $e) {
            Log::error('Editor image upload failed: ' . $e->getMessage());
            
            // CKEditor/Summernote 에러 응답 형식
            return response()->json([
                'uploaded' => false,
                'error' => [
                    'message' => 'Image upload failed: ' . $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * 업로드된 이미지 목록 조회
     */
    public function getImages(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 20);
            $search = $request->get('search', '');
            
            $query = EditorImage::select(['id', 'filename', 'original_name', 'path', 'size', 'created_at'])
                ->orderBy('created_at', 'desc');
            
            if ($search) {
                $query->where('original_name', 'like', '%' . $search . '%');
            }
            
            $total = $query->count();
            $images = $query->skip(($page - 1) * $perPage)
                           ->take($perPage)
                           ->get()
                           ->map(function ($image) {
                               return [
                                   'id' => $image->id,
                                   'filename' => $image->filename,
                                   'original_name' => $image->original_name,
                                   'path' => $image->path,
                                   'size' => $image->size,
                                   'human_size' => $image->human_size,
                                   'url' => $image->url,
                                   'created_at' => $image->created_at
                               ];
                           });
            
            return response()->json([
                'success' => true,
                'images' => $images,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage)
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve images: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 이미지 삭제
     */
    public function deleteImage(Request $request, $filename)
    {
        try {
            $image = EditorImage::where('filename', $filename)->first();
            
            if (!$image) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found'
                ], 404);
            }
            
            // 권한 체크 (관리자이거나 업로드한 사용자인 경우)
            if (Auth::user()->id !== 1 && $image->uploaded_by !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            
            // 파일 삭제 (S3 또는 로컬)
            try {
                // S3 URL인 경우와 로컬 경로인 경우를 구분하여 삭제
                if (strpos($image->path, 'https://') === 0) {
                    // S3 URL인 경우 - FileUploadService를 통해 삭제
                    $this->fileUploadService->deleteFile($image->path);
                } else {
                    // 로컬 경로인 경우
                    if (Storage::disk('public')->exists($image->path)) {
                        Storage::disk('public')->delete($image->path);
                    }
                }
            } catch (\Exception $e) {
                // 파일 삭제 실패해도 데이터베이스에서는 삭제 진행
                Log::warning("Failed to delete file {$image->path}: " . $e->getMessage());
            }
            
            // 데이터베이스에서 삭제
            $image->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image: ' . $e->getMessage()
            ], 500);
        }
    }
}
