<?php

namespace SiteManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SiteManager\Models\EditorImage;
use SiteManager\Services\FileUploadService;

class CleanupUnusedEditorImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    protected string $referenceType;
    protected string $referenceSlug;
    protected ?int $referenceId;
    protected array $usedFilenames;

    /**
     * Create a new job instance.
     *
     * @param string $referenceType - 참조 타입 (예: 'board')
     * @param string $referenceSlug - 참조 슬러그 (예: board slug)
     * @param int|null $referenceId - 참조 ID (post id, 신규 글인 경우 null)
     * @param array $usedFilenames - 콘텐츠에서 실제 사용 중인 이미지 파일명 목록
     */
    public function __construct(
        string $referenceType,
        string $referenceSlug,
        ?int $referenceId,
        array $usedFilenames = []
    ) {
        $this->referenceType = $referenceType;
        $this->referenceSlug = $referenceSlug;
        $this->referenceId = $referenceId;
        $this->usedFilenames = $usedFilenames;
    }

    /**
     * Execute the job.
     */
    public function handle(FileUploadService $fileUploadService): void
    {
        try {
            // 해당 참조에 연결된 미사용 이미지 조회
            $query = EditorImage::where('reference_type', $this->referenceType)
                ->where('reference_slug', $this->referenceSlug);

            if ($this->referenceId) {
                // 수정 모드: 해당 post에 연결된 이미지 중 미사용 이미지
                $query->where('reference_id', $this->referenceId);
            } else {
                // 신규 모드: reference_id가 null인 이미지 (아직 연결 안 된 이미지)
                $query->whereNull('reference_id');
            }

            // 사용 중인 파일은 제외
            if (!empty($this->usedFilenames)) {
                $query->whereNotIn('filename', $this->usedFilenames);
            }

            // 미사용 상태인 이미지만 (is_used = false)
            $query->where('is_used', false);

            $unusedImages = $query->get();

            if ($unusedImages->isEmpty()) {
                Log::info('CleanupUnusedEditorImagesJob: No unused images found', [
                    'reference_type' => $this->referenceType,
                    'reference_slug' => $this->referenceSlug,
                    'reference_id' => $this->referenceId,
                ]);
                return;
            }

            $deletedCount = 0;
            $errorCount = 0;

            foreach ($unusedImages as $image) {
                try {
                    // 파일 삭제 (S3 또는 로컬)
                    $this->deleteImageFile($image, $fileUploadService);

                    // DB에서 삭제
                    $image->delete();
                    $deletedCount++;

                } catch (\Exception $e) {
                    Log::warning('CleanupUnusedEditorImagesJob: Failed to delete image', [
                        'filename' => $image->filename,
                        'path' => $image->path,
                        'error' => $e->getMessage(),
                    ]);
                    $errorCount++;
                }
            }

            Log::info('CleanupUnusedEditorImagesJob: Cleanup completed', [
                'reference_type' => $this->referenceType,
                'reference_slug' => $this->referenceSlug,
                'reference_id' => $this->referenceId,
                'deleted' => $deletedCount,
                'errors' => $errorCount,
            ]);

        } catch (\Exception $e) {
            Log::error('CleanupUnusedEditorImagesJob: Job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * 이미지 파일 삭제 (S3 또는 로컬)
     */
    protected function deleteImageFile(EditorImage $image, FileUploadService $fileUploadService): void
    {
        if (strpos($image->path, 'https://') === 0) {
            // S3 URL인 경우
            $fileUploadService->deleteFile($image->path);
        } else {
            // 로컬 경로인 경우
            if (Storage::disk('public')->exists($image->path)) {
                Storage::disk('public')->delete($image->path);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CleanupUnusedEditorImagesJob: Job failed permanently', [
            'reference_type' => $this->referenceType,
            'reference_slug' => $this->referenceSlug,
            'reference_id' => $this->referenceId,
            'error' => $exception->getMessage(),
        ]);
    }
}
