<?php

namespace SiteManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorImage extends Model
{
    protected $fillable = [
        'original_name',
        'filename',
        'path',
        'size',
        'mime_type',
        'uploaded_by',
        'reference_type',
        'reference_slug',
        'reference_id',
        'is_used'
    ];

    protected $casts = [
        'size' => 'integer',
        'uploaded_by' => 'integer',
        'reference_id' => 'integer',
        'is_used' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * 업로드한 사용자와의 관계
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'uploaded_by');
    }

    /**
     * 파일 URL 반환 (S3 또는 로컬)
     */
    public function getUrlAttribute(): string
    {
        // 이미 전체 URL인 경우 (레거시 데이터)
        if (strpos($this->path, 'https://') === 0) {
            return $this->path;
        }
        
        // 상대경로인 경우 FileUploadService를 통해 URL 생성
        $fileUploadService = app(\SiteManager\Services\FileUploadService::class);
        return $fileUploadService->getFileUrl($this->path);
    }

    /**
     * 파일 크기를 읽기 쉬운 형태로 반환
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * 이미지 파일인지 확인
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * 특정 참조에 연결된 이미지들 조회
     */
    public static function getByReference(string $type, string $slug, int $id): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('reference_type', $type)
            ->where('reference_slug', $slug)
            ->where('reference_id', $id)
            ->where('is_used', true)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * 사용되지 않는 이미지들 조회 (가비지 컬렉션용)
     */
    public static function getUnusedImages(int $minutesOld = 60): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_used', false)
            ->where('created_at', '<', now()->subMinutes($minutesOld))
            ->get();
    }

    /**
     * 임시 참조 ID 생성 (post 생성 전)
     */
    public static function generateTempReferenceId(): int
    {
        return -1 * time(); // 음수로 임시 ID 구분
    }

    /**
     * 임시 참조를 실제 참조로 업데이트
     */
    public static function updateTempReference(int $tempId, int $realId): int
    {
        return static::where('reference_id', $tempId)
            ->update(['reference_id' => $realId, 'is_used' => true]);
    }

    /**
     * 콘텐츠에서 사용된 이미지들을 사용됨으로 표시
     */
    public static function markAsUsedByContent(?string $content, string $type, string $slug, int $id): int
    {
        // 다양한 에디터 이미지 URL 패턴들
        $patterns = [
            // 로컬 storage 경로
            '/\/storage\/editor\/images\/([^"\s<>]+)/',
            // S3 에디터 이미지 경로
            '/https:\/\/[^\/]+\.s3\.[^\/]+\.amazonaws\.com\/editor\/images\/([^"\s<>]+)/',
            // S3 legacy 이미지 경로 (레거시 변환용)
            '/https:\/\/[^\/]+\.s3\.[^\/]+\.amazonaws\.com\/editor\/images\/legacy\/([^"\s<>]+)/',
        ];
        
        $allFilenames = [];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                if (!empty($matches[1])) {
                    $filenames = array_map('basename', $matches[1]);
                    $allFilenames = array_merge($allFilenames, $filenames);
                }
            }
        }
        
        if (empty($allFilenames)) {
            return 0;
        }
        
        // 중복 제거
        $allFilenames = array_unique($allFilenames);
        
        return static::whereIn('filename', $allFilenames)
            ->update([
                'reference_type' => $type,
                'reference_slug' => $slug,
                'reference_id' => $id,
                'is_used' => true
            ]);
    }
}
