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
    protected $description = 'Manage application resources and published files
    
    Available actions:
    • build   - Build and optimize resources for production (adds version hash, compresses)
    • clear   - Clear all resource caches and published files (complete reset)
    • reset   - Reset to development mode (disable production optimizations)
    • status  - Show current resource status and detailed statistics
    • cleanup - Clean up old and unused asset files to free disk space';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'build':
                // 개발 모드의 리소스를 프로덕션용으로 빌드 (버전 해시 추가, 압축 등)
                return $this->buildResources();
                
            case 'clear':
                // 모든 리소스 캐시와 빌드된 파일을 삭제 (완전 초기화)
                return $this->clearResources();
                
            case 'reset':
                // 프로덕션 모드를 해제하고 개발 모드로 전환
                return $this->resetResources();
                
            case 'status':
                // 현재 리소스 상태와 통계 정보를 표시
                return $this->showStatus();
                
            case 'cleanup':
                // 오래되거나 사용하지 않는 리소스 파일을 정리
                return $this->cleanupResources();
                
            default:
                $this->error("Unknown action: {$action}");
                $this->info("Available actions:");
                $this->info("  build   - Build and optimize resources for production deployment");
                $this->info("  clear   - Clear all resource caches and published files (full reset)");
                $this->info("  reset   - Reset to development mode (disable production optimizations)");
                $this->info("  status  - Show current resource status and statistics");
                $this->info("  cleanup - Clean up old and unused asset files");
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
            
            // sitemanager:: 패키지 리소스인 경우 깨끗한 경로 사용
            if (str_starts_with($asset->original, 'sitemanager::')) {
                $cleanPath = str_replace('sitemanager::', '', $asset->original);
                $pathInfo = pathinfo($cleanPath);
            }
            
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

        // 퍼블리시된 뷰 파일 정리 (개발 시 유용)
        $publishedViewsDir = resource_path('views/vendor/sitemanager');
        $viewsDeleted = false;
        if (File::exists($publishedViewsDir)) {
            File::deleteDirectory($publishedViewsDir);
            $viewsDeleted = true;
            $this->info('🗑️  Cleared published view files');
        }

        // 설정에서 버전 제거
        $this->updateResourceVersion(null);

        $this->info("✅ Resources cleared!");
        $this->info("🗑️  Deleted {$deletedAssets} asset records");
        if ($viewsDeleted) {
            $this->info("📁 Removed published view overrides - now using package views");
        }

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

            // config 가 env() 로 값을 받게 돼 있으면 설정 파일 대신 .env 를 갱신한다.
            // 배포 서버에서 config/app.php 를 고치면 git 이 더러워져 다음 pull 이 막힌다.
            if (preg_match("/'resource_version'\s*=>\s*env\(\s*'([A-Z_]+)'/", $content, $m)) {
                $this->updateEnvResourceVersion($m[1], $version);
                $this->call('config:clear');

                return;
            }

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
     * .env 의 자산 버전 키를 갱신한다. 없으면 끝에 추가한다.
     */
    protected function updateEnvResourceVersion(string $key, ?string $version): void
    {
        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            $this->warn("  .env not found — set {$key}={$version} manually.");
            return;
        }

        $env = File::get($envPath);
        $line = $key . '=' . ($version ?? '');

        if (preg_match("/^{$key}=.*$/m", $env)) {
            $env = preg_replace("/^{$key}=.*$/m", $line, $env);
        } else {
            $env = rtrim($env, "\n") . "\n" . $line . "\n";
        }

        File::put($envPath, $env);
        $this->line("  Updated {$key} in .env");
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
