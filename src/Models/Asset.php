<?php

namespace SiteManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 */
class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'original',
        'copied',
        'mtime',
        'ext',
        'hash',
        'size',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'mtime' => 'integer',
        'size' => 'integer',
    ];

    /**
     * 사용되지 않는 오래된 에셋 제거
     */
    public static function cleanupOldAssets(int $maxDeletions = 50): int
    {
        $deletedCount = 0;
        $threeDaysAgo = now()->subDays(3);
        
        // 3일 이상 된 미사용 에셋 조회
        $oldAssets = self::where('updated_at', '<', $threeDaysAgo)
            ->limit($maxDeletions)
            ->get();

        foreach ($oldAssets as $asset) {
            if ($asset->deleteFile()) {
                $asset->delete();
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * 에셋 파일 삭제
     */
    public function deleteFile(): bool
    {
        try {
            $filePath = storage_path('app/public/assets/' . $this->copied);
            if (file_exists($filePath)) {
                return unlink($filePath);
            }
            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to delete asset file: {$this->copied}. Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 파일 해시 생성
     */
    public static function generateHash(string $originalPath, int $mtime): string
    {
        return md5($originalPath . $mtime . config('app.key'));
    }
}
