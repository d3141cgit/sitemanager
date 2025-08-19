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
        'uploaded_by'
    ];

    protected $casts = [
        'size' => 'integer',
        'uploaded_by' => 'integer',
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
     * 파일 URL 반환
     */
    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
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
}
