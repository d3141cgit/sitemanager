<?php

namespace SiteManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class BoardAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'board_slug',
        'filename',
        'original_name',
        'file_path',
        'file_extension',
        'file_size',
        'mime_type',
        'category',
        'description',
        'sort_order',
        'download_count',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'download_count' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * 파일 크기를 인간이 읽기 쉬운 형태로 반환
     */
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * 파일 다운로드 URL
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('board.attachment.download', [
            'slug' => $this->board_slug,
            'attachmentId' => $this->id
        ]);
    }

    /**
     * 이미지 미리보기 URL (이미지 파일인 경우)
     */
    public function getPreviewUrlAttribute(): ?string
    {
        if (!$this->is_image) {
            return null;
        }
        
        return asset('storage/' . $this->file_path);
    }

    /**
     * 파일이 이미지인지 확인
     */
    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'image/') || $this->category === 'image';
    }

    /**
     * 파일 타입에 따른 아이콘 클래스 반환 (Bootstrap Icons)
     */
    public function getFileIcon(): string
    {
        // 카테고리별 아이콘
        switch ($this->category) {
            case 'image':
                return 'bi-image';
            case 'video':
                return 'bi-file-earmark-play';
            case 'audio':
                return 'bi-file-earmark-music';
            case 'document':
                return $this->getDocumentIcon();
            case 'archive':
                return 'bi-file-earmark-zip';
            default:
                return 'bi-file-earmark';
        }
    }

    /**
     * 문서 타입별 세부 아이콘 반환 (Bootstrap Icons)
     */
    private function getDocumentIcon(): string
    {
        $extension = strtolower($this->file_extension);
        
        return match($extension) {
            'pdf' => 'bi-file-earmark-pdf',
            'doc', 'docx' => 'bi-file-earmark-word',
            'xls', 'xlsx' => 'bi-file-earmark-excel',
            'ppt', 'pptx' => 'bi-file-earmark-ppt',
            'txt' => 'bi-file-earmark-text',
            'csv' => 'bi-file-earmark-spreadsheet',
            default => 'bi-file-earmark'
        };
    }

    /**
     * MIME 타입에서 파일 카테고리를 자동으로 결정
     */
    public static function getCategoryFromMimeType(string $mimeType): string
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
     * 파일명에서 확장자 추출
     */
    public static function getExtensionFromFilename(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * 파일 크기를 사람이 읽기 쉬운 형태로 포맷
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        
        if ($bytes === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    /**
     * 파일을 안전하게 삭제
     */
    public function deleteFile(): bool
    {
        try {
            // S3 또는 로컬 스토리지에서 파일 삭제
            if (Storage::disk('public')->exists($this->file_path)) {
                Storage::disk('public')->delete($this->file_path);
            }
            
            // S3에서도 삭제 시도 (S3가 설정되어 있을 경우)
            if (config('filesystems.disks.s3.key')) {
                Storage::disk('s3')->delete($this->file_path);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'attachment_id' => $this->id,
                'file_path' => $this->file_path,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 관련 게시글 관계
     */
    public function post()
    {
        return $this->belongsTo(BoardPost::class, 'post_id');
    }

    /**
     * 다운로드 횟수 증가
     */
    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
    }

    /**
     * 정렬 순서별로 첨부파일 조회
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * 특정 게시글의 첨부파일 조회
     */
    public function scopeForPost($query, int $postId)
    {
        return $query->where('post_id', $postId);
    }

    /**
     * 특정 카테고리의 첨부파일 조회
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * 게시글별 첨부파일 조회 (보드 슬러그 포함)
     */
    public function scopeByPost($query, int $postId, string $boardSlug)
    {
        return $query->where('post_id', $postId)->where('board_slug', $boardSlug);
    }

    /**
     * 파일 아이콘 클래스 (attribute accessor)
     */
    public function getFileIconAttribute(): string
    {
        // 카테고리 기반으로 아이콘 결정
        return match($this->category) {
            'image' => 'bi-image',
            'video' => 'bi-file-earmark-play',
            'audio' => 'bi-file-earmark-music',
            'archive' => 'bi-file-earmark-zip',
            'document' => $this->getDocumentIcon(),
            default => 'bi-file-earmark'
        };
    }

    /**
     * 파일 삭제 시 실제 파일도 삭제하는 이벤트 리스너
     */
    protected static function booted()
    {
        static::deleting(function ($attachment) {
            // FileUploadService를 사용하여 파일 삭제
            $fileUploadService = app(\SiteManager\Services\FileUploadService::class);
            
            try {
                $fileUploadService->deleteFile($attachment->file_path);
            } catch (\Exception $e) {
                Log::error('Failed to delete attachment file', [
                    'attachment_id' => $attachment->id,
                    'file_path' => $attachment->file_path,
                    'error' => $e->getMessage()
                ]);
            }
        });
    }
}
