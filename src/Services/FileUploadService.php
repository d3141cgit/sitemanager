<?php

namespace SiteManager\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileUploadService
{
    protected $disk;
    protected $useS3;
    protected static $instance = null;
    protected static $initialized = false;
    
    public function __construct()
    {
        $this->useS3 = $this->isS3Available();
        $this->disk = $this->useS3 ? 's3' : 'public';
        
        // 첫 번째 초기화에서만 로그 남기기
        if (!static::$initialized) {
            if ($this->useS3) {
                Log::debug('FileUploadService initialized with S3 storage', [
                    'bucket' => config('filesystems.disks.s3.bucket'),
                    'region' => config('filesystems.disks.s3.region')
                ]);
            } else {
                Log::debug('FileUploadService initialized with local storage');
            }
            static::$initialized = true;
        }
    }
    
    /**
     * Check if S3 is properly configured
     */
    private function isS3Available(): bool
    {
        $awsKey = config('filesystems.disks.s3.key');
        $awsSecret = config('filesystems.disks.s3.secret');
        $awsBucket = config('filesystems.disks.s3.bucket');
        $awsRegion = config('filesystems.disks.s3.region');
        
        // S3가 사용 가능하려면 최소한 key, secret, bucket이 필요
        return !empty($awsKey) && 
               !empty($awsSecret) && 
               !empty($awsBucket) && 
               !empty($awsRegion);
    }
    
    /**
     * Upload file and return file information
     */
    public function uploadFile(UploadedFile $file, string $folder = 'uploads', array $options = []): array
    {
        try {
            // Validate file
            $this->validateFile($file, $options);
            
            // Generate unique filename
            $filename = $this->generateUniqueFilename($file);
            
            // Create full path
            $path = $folder . '/' . date('Y/m') . '/' . $filename;
            
            // Upload file
            $uploadedPath = Storage::disk($this->disk)->putFileAs(
                $folder . '/' . date('Y/m'),
                $file,
                $filename
            );
            
            if (!$uploadedPath) {
                throw new Exception('Failed to upload file');
            }
            
            // Get file URL
            $url = $this->getFileUrl($uploadedPath);
            
            // Return file information
            return [
                'id' => Str::uuid()->toString(),
                'name' => $file->getClientOriginalName(),
                'filename' => $filename,
                'path' => $uploadedPath,
                'url' => $url,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
                'disk' => $this->disk,
                'uploaded_at' => now()->toISOString()
            ];
            
        } catch (Exception $e) {
            Log::error('File upload failed: ' . $e->getMessage(), [
                'file' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Upload multiple files
     */
    public function uploadMultipleFiles(array $files, string $folder = 'uploads', array $options = []): array
    {
        $uploadedFiles = [];
        $errors = [];
        
        foreach ($files as $file) {
            try {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $uploadedFiles[] = $this->uploadFile($file, $folder, $options);
                }
            } catch (Exception $e) {
                $errors[] = [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'uploaded' => $uploadedFiles,
            'errors' => $errors
        ];
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile(UploadedFile $file, array $options = []): void
    {
        // Check if file is valid
        if (!$file->isValid()) {
            throw new Exception('Invalid file upload');
        }
        
        // Check file size
        $maxSize = $options['max_size'] ?? config('sitemanager.board.max_file_size', 2048); // KB
        if ($file->getSize() > ($maxSize * 1024)) {
            throw new Exception("File size exceeds maximum allowed size of {$maxSize}KB");
        }
        
        // Check file type
        $allowedTypes = $options['allowed_types'] ?? config('sitemanager.board.allowed_extensions');
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (!in_array($extension, $allowedTypes)) {
            throw new Exception("File type '{$extension}' is not allowed. Allowed types: " . implode(', ', $allowedTypes));
        }
        
        // Check MIME type for security
        $allowedMimes = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'txt' => ['text/plain'],
            'zip' => ['application/zip']
        ];
        
        if (isset($allowedMimes[$extension])) {
            $fileMime = $file->getMimeType();
            if (!in_array($fileMime, $allowedMimes[$extension])) {
                throw new Exception("File MIME type '{$fileMime}' does not match extension '{$extension}'");
            }
        }
    }
    
    /**
     * Generate unique filename
     */
    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        // Sanitize filename
        $basename = Str::slug($basename);
        $basename = substr($basename, 0, 50); // Limit length
        
        // Add timestamp and random string for uniqueness
        $timestamp = time();
        $random = Str::random(8);
        
        return "{$basename}_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Get file URL
     */
    public function getFileUrl(string $path): string
    {
        if ($this->useS3) {
            // For S3, construct URL properly
            $region = config('filesystems.disks.s3.region');
            $bucket = config('filesystems.disks.s3.bucket');
            $endpoint = config('filesystems.disks.s3.endpoint');
            $urlStyle = config('filesystems.disks.s3.url_style', 'virtual-hosted'); // 기본값: virtual-hosted
            
            if ($endpoint) {
                // Custom endpoint (like MinIO or DigitalOcean Spaces)
                return rtrim($endpoint, '/') . '/' . $bucket . '/' . ltrim($path, '/');
            } else {
                // Standard AWS S3 - URL 스타일에 따라 구분
                if ($urlStyle === 'path-style') {
                    // Path-style URL: https://s3.region.amazonaws.com/bucket-name/path
                    return "https://s3.{$region}.amazonaws.com/{$bucket}/" . ltrim($path, '/');
                } else {
                    // Virtual-hosted-style URL: https://bucket-name.s3.region.amazonaws.com/path
                    return "https://{$bucket}.s3.{$region}.amazonaws.com/" . ltrim($path, '/');
                }
            }
        }
        
        return asset('storage/' . $path);
    }
    
    /**
     * Delete file
     */
    public function deleteFile(string $path, string $disk = null): bool
    {
        try {
            $diskToUse = $disk ?? $this->disk;
            return Storage::disk($diskToUse)->delete($path);
        } catch (Exception $e) {
            Log::error('File deletion failed: ' . $e->getMessage(), ['path' => $path]);
            return false;
        }
    }
    
    /**
     * Delete multiple files
     */
    public function deleteMultipleFiles(array $files): array
    {
        $deleted = [];
        $errors = [];
        
        foreach ($files as $file) {
            $path = is_array($file) ? $file['path'] : $file;
            $disk = is_array($file) ? ($file['disk'] ?? $this->disk) : $this->disk;
            
            if ($this->deleteFile($path, $disk)) {
                $deleted[] = $path;
            } else {
                $errors[] = $path;
            }
        }
        
        return [
            'deleted' => $deleted,
            'errors' => $errors
        ];
    }
    
    /**
     * Check if file exists
     */
    public function fileExists(string $path, string $disk = null): bool
    {
        $diskToUse = $disk ?? $this->disk;
        return Storage::disk($diskToUse)->exists($path);
    }
    
    /**
     * Get file size
     */
    public function getFileSize(string $path, string $disk = null): int
    {
        $diskToUse = $disk ?? $this->disk;
        return Storage::disk($diskToUse)->size($path);
    }
    
    /**
     * Get storage disk being used
     */
    public function getDisk(): string
    {
        return $this->disk;
    }
    
    /**
     * Check if using S3
     */
    public function isUsingS3(): bool
    {
        return $this->useS3;
    }
    
    /**
     * Test S3 connection
     */
    public function testS3Connection(): array
    {
        if (!$this->useS3) {
            return [
                'success' => false,
                'message' => 'S3 is not configured',
                'details' => 'Please set AWS credentials in .env file'
            ];
        }
        
        try {
            // Test S3 connection by listing bucket
            $files = Storage::disk('s3')->files('', 1); // Get max 1 file to test
            
            return [
                'success' => true,
                'message' => 'S3 connection successful',
                'bucket' => config('filesystems.disks.s3.bucket'),
                'region' => config('filesystems.disks.s3.region')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'S3 connection failed',
                'error' => $e->getMessage(),
                'details' => 'Please check your AWS credentials and bucket permissions'
            ];
        }
    }
    
    /**
     * Get file download response
     */
    public function downloadFile(string $path, string $disk = null, string $name = null, bool $forceDownload = true)
    {
        $diskToUse = $disk ?? $this->disk;
        
        if (!$this->fileExists($path, $diskToUse)) {
            abort(404, 'File not found');
        }
        
        $filename = $name ?? basename($path);
        
        if ($diskToUse === 's3') {
            // For S3, get the file content and create download response
            $fileContent = Storage::disk($diskToUse)->get($path);
            $mimeType = $this->getMimeTypeFromPath($path);
            
            $response = response($fileContent, 200, [
                'Content-Type' => $forceDownload ? 'application/octet-stream' : $mimeType,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => strlen($fileContent),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);
            
            return $response;
        }
        
        // For local storage, use response download
        $fullPath = Storage::disk($diskToUse)->path($path);
        
        if ($forceDownload) {
            // Force download with proper headers
            return response()->download($fullPath, $filename, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);
        }
        
        return response()->download($fullPath, $filename);
    }
    
    /**
     * 기본 파일 업로드 설정 (게시판 기준)
     */
    public static function getDefaultConfig(): array
    {
        return [
            'max_file_size' => 10240, // 10MB in KB
            'max_files' => 10,
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'],
            'folder_prefix' => 'attachments'
        ];
    }
    
    /**
     * Format file size for display
     */
    public static function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 범용 첨부파일 업로드 (테이블명 기반)
     * 
     * @param UploadedFile $file 업로드할 파일
     * @param string $tablePrefix 테이블 접두사 (예: 'board', 'doc', 'gallery')
     * @param int $entityId 연결할 엔티티 ID
     * @param array $options 업로드 옵션
     * @return array 업로드된 파일 정보
     */
    public function uploadAttachment(UploadedFile $file, string $tablePrefix, int $entityId, array $options = []): array
    {
        // 기본 설정과 사용자 옵션 병합
        $config = array_merge(self::getDefaultConfig(), $options);
        
        // 폴더 구조: attachments/{tablePrefix}/{entityId}
        $folder = "attachments/{$tablePrefix}/{$entityId}";
        
        // 파일 업로드
        $fileInfo = $this->uploadFile($file, $folder, [
            'allowed_types' => $config['allowed_types'],
            'max_size' => $config['max_file_size']
        ]);
        
        // 첨부파일 메타데이터 추가
        $fileInfo['table_prefix'] = $tablePrefix;
        $fileInfo['entity_id'] = $entityId;
        $fileInfo['original_name'] = $file->getClientOriginalName();
        $fileInfo['file_extension'] = strtolower($file->getClientOriginalExtension());
        $fileInfo['file_size'] = $file->getSize();
        $fileInfo['mime_type'] = $file->getMimeType();
        $fileInfo['category'] = $this->getCategoryFromMimeType($file->getMimeType());
        
        return $fileInfo;
    }

    /**
     * 범용 다중 첨부파일 업로드
     * 
     * @param array $files 업로드할 파일 배열
     * @param string $tablePrefix 테이블 접두사
     * @param int $entityId 연결할 엔티티 ID
     * @param array $options 업로드 옵션
     * @return array 업로드된 파일들 정보
     */
    public function uploadMultipleAttachments(array $files, string $tablePrefix, int $entityId, array $options = []): array
    {
        $config = array_merge(self::getDefaultConfig(), $options);
        $uploadedFiles = [];
        
        foreach ($files as $index => $file) {
            // 최대 파일 수 제한
            if (count($uploadedFiles) >= $config['max_files']) {
                break;
            }
            
            if (!$file->isValid()) {
                continue;
            }
            
            try {
                // 파일 업로드
                $fileInfo = $this->uploadAttachment($file, $tablePrefix, $entityId, $options);
                $fileInfo['sort_order'] = $index;
                
                $uploadedFiles[] = $fileInfo;
                
            } catch (\Exception $e) {
                Log::error('Attachment upload failed', [
                    'table_prefix' => $tablePrefix,
                    'entity_id' => $entityId,
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        
        return $uploadedFiles;
    }

    /**
     * 게시판 첨부파일 업로드 (보드별 폴더 분리)
     */
    public function uploadBoardAttachment(UploadedFile $file, string $boardSlug, int $postId, array $options = []): array
    {
        // 기본 설정과 사용자 옵션 병합
        $config = array_merge(self::getDefaultConfig(), $options);
        
        // 보드별 폴더 구조: attachments/board/{boardSlug}
        $folder = "attachments/board/{$boardSlug}";
        
        // 파일 업로드
        $fileInfo = $this->uploadFile($file, $folder, [
            'allowed_types' => $config['allowed_types'],
            'max_size' => $config['max_file_size']
        ]);
        
        // 첨부파일 메타데이터 추가
        $fileInfo['table_prefix'] = 'board';
        $fileInfo['entity_id'] = $postId;
        $fileInfo['board_slug'] = $boardSlug;
        $fileInfo['original_name'] = $file->getClientOriginalName();
        $fileInfo['file_extension'] = strtolower($file->getClientOriginalExtension());
        $fileInfo['file_size'] = $file->getSize();
        $fileInfo['mime_type'] = $file->getMimeType();
        
        // 게시판에 파일 카테고리가 설정되어 있으면 첫 번째 카테고리 사용, 없으면 MIME 타입 기반
        $fileCategories = $config['file_categories'] ?? [];
        if (!empty($fileCategories)) {
            $fileInfo['category'] = $fileCategories[0]; // 첫 번째 카테고리를 기본값으로 사용
        } else {
            $fileInfo['category'] = $this->getCategoryFromMimeType($file->getMimeType());
        }
        
        return $fileInfo;
    }

    /**
     * 게시판 다중 첨부파일 업로드 (보드별 폴더 분리)
     */
    public function uploadBoardAttachments(array $files, string $boardSlug, int $postId, array $options = []): array
    {
        $config = array_merge(self::getDefaultConfig(), $options);
        $uploadedFiles = [];
        
        foreach ($files as $index => $file) {
            // 최대 파일 수 제한
            if (count($uploadedFiles) >= $config['max_files']) {
                break;
            }
            
            if (!$file->isValid()) {
                continue;
            }
            
            try {
                // 보드별 파일 업로드
                $fileInfo = $this->uploadBoardAttachment($file, $boardSlug, $postId, $options);
                $fileInfo['sort_order'] = $index;
                
                $uploadedFiles[] = $fileInfo;
                
            } catch (\Exception $e) {
                Log::error('Board attachment upload failed', [
                    'board_slug' => $boardSlug,
                    'post_id' => $postId,
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        
        return $uploadedFiles;
    }

    /**
     * 첨부파일 삭제 (테이블 접두사 기반)
     * 
     * @param string $path 파일 경로
     * @param string $tablePrefix 테이블 접두사
     * @param int $entityId 엔티티 ID
     * @return bool 삭제 성공 여부
     */
    public function deleteAttachment(string $path, string $tablePrefix, int $entityId): bool
    {
        try {
            $deleted = $this->deleteFile($path);
            
            if ($deleted) {
                Log::info('Attachment deleted successfully', [
                    'table_prefix' => $tablePrefix,
                    'entity_id' => $entityId,
                    'path' => $path
                ]);
            }
            
            return $deleted;
        } catch (\Exception $e) {
            Log::error('Attachment deletion failed', [
                'table_prefix' => $tablePrefix,
                'entity_id' => $entityId,
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 다중 첨부파일 삭제
     * 
     * @param array $paths 파일 경로 배열
     * @param string $tablePrefix 테이블 접두사
     * @param int $entityId 엔티티 ID
     * @return array 삭제 결과 ['deleted' => [...], 'errors' => [...]]
     */
    public function deleteMultipleAttachments(array $paths, string $tablePrefix, int $entityId): array
    {
        $deleted = [];
        $errors = [];
        
        foreach ($paths as $path) {
            if ($this->deleteAttachment($path, $tablePrefix, $entityId)) {
                $deleted[] = $path;
            } else {
                $errors[] = $path;
            }
        }
        
        return [
            'deleted' => $deleted,
            'errors' => $errors
        ];
    }

    /**
     * MIME 타입에서 파일 카테고리를 자동으로 결정
     */
    private function getCategoryFromMimeType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } elseif (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv'
        ])) {
            return 'document';
        } elseif (in_array($mimeType, [
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/gzip'
        ])) {
            return 'archive';
        } else {
            return 'other';
        }
    }

    /**
     * 이미지 업로드 (프로필 사진 등)
     */
    public function uploadImage(UploadedFile $file, string $folder = 'images', array $options = []): array
    {
        // 이미지 파일인지 확인
        if (!str_starts_with($file->getMimeType(), 'image/')) {
            throw new \InvalidArgumentException('업로드된 파일이 이미지가 아닙니다.');
        }

        // 기본 옵션 설정
        $defaultOptions = [
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max_size' => 2048, // KB
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        return $this->uploadFile($file, $folder, $options);
    }

    /**
     * 프로필 사진 업로드
     */
    public function uploadProfilePhoto(UploadedFile $file): array
    {
        return $this->uploadImage($file, 'profile_photos', [
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
            'max_size' => 2048
        ]);
    }

    /**
     * Static helper methods for backwards compatibility
     */
    
    /**
     * Get singleton instance
     */
    protected static function getInstance(): self
    {
        if (static::$instance === null) {
            static::$instance = new self();
        }
        return static::$instance;
    }
    
    /**
     * Static method to get file URL
     */
    public static function url(string $path): string
    {
        return static::getInstance()->getFileUrl($path);
    }
    
    /**
     * Static method to upload file
     */
    public static function upload(UploadedFile $file, string $folder = 'uploads'): string
    {
        $result = static::getInstance()->uploadImage($file, $folder);
        return $result['path'];
    }
    
    /**
     * Static method to delete file
     */
    public static function delete(string $path): bool
    {
        return static::getInstance()->deleteFile($path);
    }
    
    /**
     * Check if S3 is properly configured
     */
    public static function isS3Configured(): bool
    {
        return static::getInstance()->isUsingS3();
    }
    
    /**
     * Migrate file from one disk to another
     */
    public static function migrate(string $fromDisk, string $toDisk, string $path): ?string
    {
        try {
            // Check if source file exists
            if (!Storage::disk($fromDisk)->exists($path)) {
                return null;
            }
            
            // Copy file content
            $content = Storage::disk($fromDisk)->get($path);
            
            // Store in target disk
            Storage::disk($toDisk)->put($path, $content);
            
            // Delete from source disk if migration is successful
            if (Storage::disk($toDisk)->exists($path)) {
                Storage::disk($fromDisk)->delete($path);
                return $path;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('File migration failed', [
                'from_disk' => $fromDisk,
                'to_disk' => $toDisk,
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get MIME type from file path
     */
    private function getMimeTypeFromPath(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            // Documents
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            
            // Images
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            
            // Archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            
            // Text
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xml' => 'application/xml',
            
            // Audio/Video
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
