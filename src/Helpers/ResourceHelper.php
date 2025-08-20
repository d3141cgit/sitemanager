<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SiteManager\Models\Asset;

if (!function_exists('resource')) {
    /**
     * 리소스 파일을 처리하고 HTML 태그를 반환합니다.
     * 
     * @param string $assetPath
     * @param array $options
     * @return string
     */
    function resource(string $assetPath, array $options = []): string
    {
        // 캐시 키 생성
        // $cacheKey = "resource.{$assetPath}";
        
        // 프로덕션 환경에서는 빌드된 리소스 사용
        if (app()->environment('production') && config('app.resource_version')) {
            return getCachedResource($assetPath, $options);
        }

        // 개발 환경에서는 실시간 처리
        return processResource($assetPath, $options);
    }
}

if (!function_exists('processResource')) {
    /**
     * 리소스를 실시간으로 처리합니다.
     */
    function processResource(string $assetPath, array $options = []): string
    {
        // sitemanager:: 패키지 리소스 처리
        if (str_starts_with($assetPath, 'sitemanager::')) {
            $packagePath = str_replace('sitemanager::', '', $assetPath);
            $resourcePath = __DIR__ . "/../../resources/{$packagePath}";
            $cleanAssetPath = $packagePath; // Hash 생성에는 깨끗한 경로 사용
        } else {
            $resourcePath = resource_path($assetPath);
            $cleanAssetPath = $assetPath;
        }
        
        if (!file_exists($resourcePath)) {
            Log::warning("Resource file not found: {$assetPath}");
            return '';
        }

        $fileInfo = pathinfo($cleanAssetPath);
        $ext = strtolower($fileInfo['extension'] ?? '');
        $mtime = filemtime($resourcePath);
        $size = filesize($resourcePath);
        $hash = Asset::generateHash($cleanAssetPath, $mtime);

        // 데이터베이스에서 기존 에셋 조회
        $asset = Asset::where('original', $assetPath)->first();

        // 파일이 변경되지 않았고 복사본이 존재하는 경우
        if ($asset && $asset->mtime == $mtime && $asset->hash == $hash) {
            $copiedPath = storage_path('app/public/assets/' . $asset->copied);
            if (file_exists($copiedPath)) {
                return generateHtmlTag($asset->copied, $ext, $options);
            }
        }

        // 새로운 파일 또는 변경된 파일 처리
        $copiedFileName = generateCopiedFileName($cleanAssetPath, $hash, $ext);
        
        // 이전 파일 정리
        if ($asset && $asset->copied !== $copiedFileName) {
            $asset->deleteFile();
        }

        // 새 파일 복사
        if (copyAssetFile($resourcePath, $copiedFileName, $ext)) {
            // 데이터베이스 업데이트
            Asset::updateOrCreate(
                ['original' => $assetPath],
                [
                    'copied' => $copiedFileName,
                    'hash' => $hash,
                    'ext' => $ext,
                    'mtime' => $mtime,
                    'size' => $size,
                ]
            );

            // 가끔씩 가비지 수집 실행 (1% 확률)
            if (random_int(1, 100) === 1) {
                Asset::cleanupOldAssets();
            }

            return generateHtmlTag($copiedFileName, $ext, $options);
        }

        Log::error("Failed to copy asset file: {$assetPath}");
        return '';
    }
}

if (!function_exists('getCachedResource')) {
    /**
     * 프로덕션에서 빌드된 리소스를 가져옵니다.
     */
    function getCachedResource(string $assetPath, array $options = []): string
    {
        $version = config('app.resource_version');
        $fileInfo = pathinfo($assetPath);
        $ext = strtolower($fileInfo['extension'] ?? '');
        $filename = $fileInfo['filename'];
        $dirname = $fileInfo['dirname'] !== '.' ? $fileInfo['dirname'] . '/' : '';
        
        // sitemanager:: 패키지 리소스인 경우 패키지 경로로 처리
        if (str_starts_with($assetPath, 'sitemanager::')) {
            $cleanPath = str_replace('sitemanager::', '', $assetPath);
            $fileInfo = pathinfo($cleanPath);
            $dirname = $fileInfo['dirname'] !== '.' ? $fileInfo['dirname'] . '/' : '';
            $filename = $fileInfo['filename'];
        }
        
        $versionedPath = "{$dirname}{$filename}-{$version}.{$ext}";
        $publicPath = public_path("assets/{$versionedPath}");
        
        if (file_exists($publicPath)) {
            return generateHtmlTag($versionedPath, $ext, $options, true);
        }

        // 빌드된 파일이 없으면 개발 모드로 폴백
        return processResource($assetPath, $options);
    }
}

if (!function_exists('generateCopiedFileName')) {
    /**
     * 복사될 파일명을 생성합니다.
     */
    function generateCopiedFileName(string $originalPath, string $hash, string $ext): string
    {
        // sitemanager:: 패키지 접두사 제거
        $cleanPath = str_starts_with($originalPath, 'sitemanager::') 
            ? str_replace('sitemanager::', '', $originalPath)
            : $originalPath;
            
        $pathInfo = pathinfo($cleanPath);
        $baseDir = $pathInfo['dirname'] !== '.' ? str_replace('/', '-', $pathInfo['dirname']) . '-' : '';
        $baseName = $pathInfo['filename'];
        
        return "{$baseDir}{$baseName}-{$hash}.{$ext}";
    }
}

if (!function_exists('copyAssetFile')) {
    /**
     * 에셋 파일을 복사합니다.
     */
    function copyAssetFile(string $sourcePath, string $copiedFileName, string $ext): bool
    {
        $targetDir = storage_path('app/public/assets');
        $targetPath = $targetDir . '/' . $copiedFileName;

        // 디렉토리 생성
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // 파일 복사
        if (!copy($sourcePath, $targetPath)) {
            return false;
        }

        // CSS 파일의 경우 이미지 경로 처리
        if ($ext === 'css') {
            processCssFile($targetPath);
        }

        return true;
    }
}

if (!function_exists('processCssFile')) {
    /**
     * CSS 파일의 이미지 경로를 처리합니다.
     */
    function processCssFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        
        // 상대 경로를 절대 경로로 변경 (필요한 경우)
        $imageUrl = config('app.asset_url', asset(''));
        $content = preg_replace('#(\.\.\/img\/|\.\.\/\.\.\/img\/)#', $imageUrl . '/img/', $content);
        
        file_put_contents($filePath, $content);
    }
}

if (!function_exists('generateHtmlTag')) {
    /**
     * HTML 태그를 생성합니다.
     */
    function generateHtmlTag(string $fileName, string $ext, array $options = [], bool $isProduction = false): string
    {
        $basePath = $isProduction ? asset("assets/{$fileName}") : asset("storage/assets/{$fileName}");
        
        switch ($ext) {
            case 'css':
                $attributes = array_merge(['rel' => 'stylesheet', 'href' => $basePath], $options);
                $attrString = implode(' ', array_map(
                    fn($key, $value) => $key . '="' . htmlspecialchars($value) . '"',
                    array_keys($attributes),
                    array_values($attributes)
                ));
                return "<link {$attrString} />";
                
            case 'js':
                $attributes = array_merge(['src' => $basePath], $options);
                $attrString = implode(' ', array_map(
                    fn($key, $value) => $key . '="' . htmlspecialchars($value) . '"',
                    array_keys($attributes),
                    array_values($attributes)
                ));
                return "<script {$attrString}></script>";
                
            default:
                return $basePath;
        }
    }
}

if (!function_exists('setResources')) {
    /**
     * 여러 외부 리소스를 한번에 로드합니다.
     */
    function setResources(array $assets = []): string
    {
        static $loadedResources = [];
        $resources = '';

        $cdnResources = [
            'sweetalert' => [
                    'css' => ['https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css'],
                    'js' => ['https://cdn.jsdelivr.net/npm/sweetalert2@11']
                ],
            'jquery' => [
                    'js' => [
                        'https://code.jquery.com/jquery-3.7.1.min.js',
                        'https://code.jquery.com/ui/1.14.0/jquery-ui.min.js'
                    ],
                    'css' => ['//code.jquery.com/ui/1.14.0/themes/ui-darkness/jquery-ui.css']
                ],
            'bootstrap' => [
                'js' => ['https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js'],
                'css' => [
                    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css',
                    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css'
                ]
            ],
            'fontawesome' => [
                'css' => ['https://use.fontawesome.com/releases/v5.15.4/css/all.css']
            ],
        ];

        foreach ($assets as $asset) {
            if (in_array($asset, $loadedResources) || !isset($cdnResources[$asset])) {
                continue;
            }

            $config = $cdnResources[$asset];
            
            foreach ($config['css'] ?? [] as $url) {
                $resources .= "<link rel=\"stylesheet\" href=\"{$url}\" />\n";
            }
            
            foreach ($config['js'] ?? [] as $url) {
                $resources .= "<script src=\"{$url}\"></script>\n";
            }

            $loadedResources[] = $asset;
        }

        return $resources;
    }
}

if (!function_exists('config_get')) {
    /**
     * ConfigService의 getValue() 메서드 단축 헬퍼
     */
    function config_get(string $key, $default = null): mixed
    {
        return \SiteManager\Services\ConfigService::getValue($key, $default);
    }
}

if (!function_exists('config_set')) {
    /**
     * ConfigService의 set() 메서드 단축 헬퍼
     */
    function config_set(string $key, $value, string $type = 'hidden'): \SiteManager\Models\Setting
    {
        return \SiteManager\Services\ConfigService::set($key, $value, $type);
    }
}
