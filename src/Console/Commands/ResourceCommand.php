<?php

namespace SiteManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SiteManager\Models\Asset;

class ResourceCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'resource {action} 
                            {--force : Force the operation without confirmation}
                            {--build-version= : Specify version for build command}';

    /**
     * The console command description.
     */
    protected $description = 'Manage application resources (CSS/JS assets)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'build':
                return $this->buildResources();
                
            case 'clear':
                return $this->clearResources();
                
            case 'reset':
                return $this->resetResources();
                
            case 'status':
                return $this->showStatus();
                
            case 'cleanup':
                return $this->cleanupResources();
                
            default:
                $this->error("Unknown action: {$action}");
                $this->info("Available actions: build, clear, reset, status, cleanup");
                return 1;
        }
    }

    /**
     * 리소스를 빌드하여 프로덕션 준비
     */
    protected function buildResources(): int
    {
        $this->info('Building resources for production...');

        $assets = Asset::all();
        
        if ($assets->isEmpty()) {
            $this->warn('No assets found to build. Make sure to load some pages first.');
            return 0;
        }

        $version = $this->option('build-version') ?: Str::random(12);
        $publicAssetsDir = public_path('assets');
        
        // 기존 빌드 파일 정리
        if (File::exists($publicAssetsDir)) {
            File::deleteDirectory($publicAssetsDir);
        }
        File::makeDirectory($publicAssetsDir, 0755, true);

        $successCount = 0;
        $failCount = 0;

        foreach ($assets as $asset) {
            if ($this->buildAsset($asset, $version, $publicAssetsDir)) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        // 설정 파일에 버전 저장
        $this->updateResourceVersion($version);

        $this->info("Build completed!");
        $this->info("✅ Successfully built: {$successCount} assets");
        
        if ($failCount > 0) {
            $this->warn("❌ Failed to build: {$failCount} assets");
        }
        
        $this->info("🏷️  Resource version: {$version}");

        return $failCount > 0 ? 1 : 0;
    }

    /**
     * 개별 에셋을 빌드
     */
    protected function buildAsset(Asset $asset, string $version, string $publicAssetsDir): bool
    {
        try {
            $sourcePath = storage_path('app/public/assets/' . $asset->copied);
            
            if (!File::exists($sourcePath)) {
                $this->warn("Source file not found: {$asset->copied}");
                return false;
            }

            $pathInfo = pathinfo($asset->original);
            $dirname = $pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] . '/' : '';
            $filename = $pathInfo['filename'];
            $ext = $pathInfo['extension'];
            
            $targetFileName = "{$dirname}{$filename}-{$version}.{$ext}";
            $targetPath = $publicAssetsDir . '/' . $targetFileName;
            $targetDir = dirname($targetPath);

            // 디렉토리 생성
            if (!File::exists($targetDir)) {
                File::makeDirectory($targetDir, 0755, true);
            }

            // 파일 복사
            if (!File::copy($sourcePath, $targetPath)) {
                throw new \Exception("Failed to copy file");
            }

            $this->line("✅ {$asset->original} -> {$targetFileName}");
            return true;

        } catch (\Exception $e) {
            $this->error("❌ Failed to build {$asset->original}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 리소스 캐시 및 빌드 파일 정리
     */
    protected function clearResources(): int
    {
        if (!$this->option('force') && !$this->confirm('This will clear all resource caches and built files. Continue?')) {
            return 0;
        }

        $this->info('Clearing resources...');

        // 데이터베이스 정리
        $deletedAssets = Asset::count();
        Asset::truncate();

        // storage/app/public/assets 정리
        $storageAssetsDir = storage_path('app/public/assets');
        if (File::exists($storageAssetsDir)) {
            File::deleteDirectory($storageAssetsDir);
            File::makeDirectory($storageAssetsDir, 0755, true);
        }

        // public/assets 정리
        $publicAssetsDir = public_path('assets');
        if (File::exists($publicAssetsDir)) {
            File::deleteDirectory($publicAssetsDir);
        }

        // 설정에서 버전 제거
        $this->updateResourceVersion(null);

        $this->info("✅ Resources cleared!");
        $this->info("🗑️  Deleted {$deletedAssets} asset records");

        return 0;
    }

    /**
     * 리소스 버전 리셋 (개발 모드로 전환)
     */
    protected function resetResources(): int
    {
        $this->updateResourceVersion(null);
        $this->info('✅ Resource version reset. Now using development mode.');
        return 0;
    }

    /**
     * 리소스 상태 표시
     */
    protected function showStatus(): int
    {
        $version = config('app.resource_version');
        $assetCount = Asset::count();
        $totalSize = Asset::sum('size');
        
        $this->info('📊 Resource Status');
        $this->table(['Property', 'Value'], [
            ['Environment', app()->environment()],
            ['Resource Version', $version ?: 'Not set (Development mode)'],
            ['Total Assets', $assetCount],
            ['Total Size', $this->formatBytes($totalSize)],
            ['Storage Path', storage_path('app/public/assets')],
            ['Public Path', public_path('assets')],
        ]);

        if ($assetCount > 0) {
            $this->info('📁 Recent Assets:');
            $recentAssets = Asset::latest()->limit(10)->get(['original', 'ext', 'size', 'updated_at']);
            
            $this->table(
                ['Original', 'Type', 'Size', 'Updated'],
                $recentAssets->map(fn($asset) => [
                    $asset->original,
                    strtoupper($asset->ext),
                    $this->formatBytes($asset->size),
                    $asset->updated_at->diffForHumans()
                ])->toArray()
            );
        }

        return 0;
    }

    /**
     * 오래된 리소스 정리
     */
    protected function cleanupResources(): int
    {
        $this->info('🧹 Cleaning up old resources...');
        
        $deletedCount = Asset::cleanupOldAssets(100);
        
        if ($deletedCount > 0) {
            $this->info("✅ Cleaned up {$deletedCount} old asset files");
        } else {
            $this->info('ℹ️  No old assets to clean up');
        }

        return 0;
    }

    /**
     * 설정 파일의 resource_version 업데이트
     */
    protected function updateResourceVersion(?string $version): void
    {
        try {
            $configPath = config_path('app.php');
            
            if (!File::exists($configPath)) {
                $this->error("Config file not found: {$configPath}");
                return;
            }

            $content = File::get($configPath);
            
            // resource_version 설정이 이미 있는지 확인
            if (preg_match("/'resource_version'\s*=>/", $content)) {
                // 기존 설정 업데이트
                $pattern = "/'resource_version'\s*=>\s*[^,]+,?/";
                $replacement = $version 
                    ? "'resource_version' => '{$version}',"
                    : "'resource_version' => null,";
                $content = preg_replace($pattern, $replacement, $content);
            } else {
                // 새 설정 추가 (env 설정 뒤에)
                $pattern = "/('env'\s*=>\s*env\('APP_ENV',\s*'[^']+'\),?)/";
                $replacement = $version 
                    ? "$1\n\n    'resource_version' => '{$version}',"
                    : "$1\n\n    'resource_version' => null,";
                $content = preg_replace($pattern, $replacement, $content);
            }

            File::put($configPath, $content);
            
            // 설정 캐시 클리어
            $this->call('config:clear');
            
        } catch (\Exception $e) {
            $this->error("Failed to update resource version: " . $e->getMessage());
        }
    }

    /**
     * 바이트를 사람이 읽기 쉬운 형태로 변환
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
