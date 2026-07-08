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
        
        // source map 주석 제거 (/*# sourceMappingURL=... */ 형태)
        $content = preg_replace('/\/\*#\s*sourceMappingURL=[^\*]+\*\//', '', $content);
        
        // source map 주석 제거 (//# sourceMappingURL=... 형태)
        $content = preg_replace('/\/\/#\s*sourceMappingURL=.*$/m', '', $content);
        
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

        // CDN 자산: 버전 고정 + SRI(integrity) 해시로 공급망 변조·CDN 장애 노출을 줄인다.
        //  형식: 'url' 만 있으면 SRI 없음(문자열), ['url'=>..,'sri'=>..] 이면 integrity+crossorigin 부여.
        //  SRI 를 갱신할 때는 해당 URL 을 받아 `openssl dgst -sha384 -binary | openssl base64 -A` 로 재계산.
        $cdnResources = [
            'sweetalert' => [
                    'css' => [['url' => 'https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.min.css', 'sri' => 'sha384-dCW5imOdApH6OwpFau8cZNKjqVbJYnCA5q+8YsMYP3XwXKsV6Jfz1u6MZLnXaBsS']],
                    'js' => [['url' => 'https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.all.min.js', 'sri' => 'sha384-nLoOnA/BDh8A/jxqtckg4DumuCGOBYUnNJLZdQz/zfYNp3wcjGSoWTAzgko06G/2']]
                ],
            'jquery' => [
                    'js' => [
                        ['url' => 'https://code.jquery.com/jquery-3.7.1.min.js', 'sri' => 'sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs'],
                        ['url' => 'https://code.jquery.com/ui/1.14.0/jquery-ui.min.js', 'sri' => 'sha384-8EM386r8XMMzwGPUxfGNr6c1wIOYnPQBJ6VFxzKCZeklpQarHoZGB40kdNDA3gYr']
                    ],
                    'css' => [['url' => 'https://code.jquery.com/ui/1.14.0/themes/ui-darkness/jquery-ui.css', 'sri' => 'sha384-/0boWEfbR17vbRSzc07RRhUA9hK8orV94N/Fv2CoCaXcUlOrqLq2UX/21TgM+OrM']]
                ],
            'bootstrap' => [
                'js' => [['url' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js', 'sri' => 'sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q']],
                'css' => [
                    ['url' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css', 'sri' => 'sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr'],
                    ['url' => 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css', 'sri' => 'sha384-CK2SzKma4jA5H/MXDUU7i1TqZlCFaD4T01vtyDFvPlD97JQyS+IsSh1nI2EFbpyk']
                ]
            ],
            'fontawesome' => [
                // use.fontawesome.com 은 CORS/버전 특성상 SRI 미부여 (kit 갱신 대비).
                'css' => ['https://use.fontawesome.com/releases/v5.15.4/css/all.css']
            ],
            'animate' => [
                'css' => [['url' => 'https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css', 'sri' => 'sha384-Gu3KVV2H9d+yA4QDpVB7VcOyhJlAVrcXd0thEjr4KznfaFPLe0xQJyonVxONa4ZC']]
            ],
            'select2' => [
                'js' => [['url' => 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', 'sri' => 'sha384-d3UHjPdzJkZuk5H3qKYMLRyWLAQBJbby2yr2Q58hXXtAGF8RSNO9jpLDlKKPv5v3']],
                'css' => [
                    ['url' => 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', 'sri' => 'sha384-OXVF05DQEe311p6ohU11NwlnX08FzMCsyoXzGOaL+83dKAb3qS17yZJxESl8YrJQ'],
                    ['url' => 'https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css', 'sri' => 'sha384-IrMr0LFnIMa9H6HhC5VVqVuWNEIwspnRLKQc0SUyPj4Cy4s02DiWDZEoJOo5WNK6']
                ]
            ],
            'flatpickr' => [
                'js' => [['url' => 'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js', 'sri' => 'sha384-5JqMv4L/Xa0hfvtF06qboNdhvuYXUku9ZrhZh3bSk8VXF0A/RuSLHpLsSV9Zqhl6']],
                'css' => [['url' => 'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css', 'sri' => 'sha384-RkASv+6KfBMW9eknReJIJ6b3UnjKOKC5bOUaNgIY778NFbQ8MtWq9Lr/khUgqtTt']]
            ],
            'swiper' => [
                // swiper@12 는 major 태그(미고정)라 SRI 미부여 — 고정 시 함께 SRI 부여 권장.
                'js' => ['https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.js'],
                'css' => ['https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.css']
            ],
        ];

        // CDN 항목 정규화: 문자열이면 [url, sri=null], 배열이면 [url, sri].
        $cdnItem = function ($item): array {
            if (is_array($item)) {
                return [$item['url'] ?? '', $item['sri'] ?? null];
            }

            return [(string) $item, null];
        };

        foreach ($assets as $asset) {
            if (in_array($asset, $loadedResources) || !isset($cdnResources[$asset])) {
                continue;
            }

            $config = $cdnResources[$asset];

            foreach ($config['css'] ?? [] as $item) {
                [$url, $sri] = $cdnItem($item);
                $attrs = $sri ? " integrity=\"{$sri}\" crossorigin=\"anonymous\"" : '';
                $resources .= "<link rel=\"stylesheet\" href=\"{$url}\"{$attrs} />\n";
            }

            foreach ($config['js'] ?? [] as $item) {
                [$url, $sri] = $cdnItem($item);
                $attrs = $sri ? " integrity=\"{$sri}\" crossorigin=\"anonymous\"" : '';
                $resources .= "<script src=\"{$url}\"{$attrs}></script>\n";
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
