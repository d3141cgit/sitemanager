<?php

namespace SiteManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use SiteManager\Models\EditorImage;
use SiteManager\Services\FileUploadService;

class CleanupEditorImages extends Command
{
    protected $fileUploadService;
    
    public function __construct(FileUploadService $fileUploadService)
    {
        parent::__construct();
        $this->fileUploadService = $fileUploadService;
    }
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'editor:cleanup-images {--hours=1 : Hours old to consider for cleanup} {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up unused editor images that are older than specified hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = $this->option('hours');
        $dryRun = $this->option('dry-run');
        
        $this->info("🧹 Editor Images Cleanup");
        $this->info("========================");
        $this->info("Looking for unused images older than {$hours} hour(s)...");
        
        // 사용되지 않는 이미지들 조회
        $unusedImages = EditorImage::getUnusedImages($hours * 60);
        
        if ($unusedImages->isEmpty()) {
            $this->info("✅ No unused images found.");
            return;
        }
        
        $this->info("Found {$unusedImages->count()} unused images:");
        
        $deletedCount = 0;
        $errorCount = 0;
        
        foreach ($unusedImages as $image) {
            $this->line("  - {$image->filename} (uploaded: {$image->created_at})");
            
            if (!$dryRun) {
                try {
                    // 파일 삭제 (S3 또는 로컬)
                    if (strpos($image->path, 'https://') === 0) {
                        // S3 URL인 경우
                        $this->fileUploadService->deleteFile($image->path);
                    } else {
                        // 로컬 경로인 경우
                        if (Storage::disk('public')->exists($image->path)) {
                            Storage::disk('public')->delete($image->path);
                        }
                    }
                    
                    // 데이터베이스에서 삭제
                    $image->delete();
                    $deletedCount++;
                    
                } catch (\Exception $e) {
                    $this->error("    Failed to delete {$image->filename}: " . $e->getMessage());
                    Log::error("Editor image cleanup failed: {$image->filename}", ['error' => $e->getMessage()]);
                    $errorCount++;
                }
            }
        }
        
        if ($dryRun) {
            $this->warn("🔍 Dry run mode - no files were actually deleted.");
            $this->info("Run without --dry-run to actually delete these files.");
        } else {
            $this->info("✅ Cleanup completed:");
            $this->info("  - Deleted: {$deletedCount} images");
            if ($errorCount > 0) {
                $this->warn("  - Errors: {$errorCount} images");
            }
        }
    }
}
