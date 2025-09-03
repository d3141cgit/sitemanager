<?php

use SiteManager\Models\Language;
use Illuminate\Support\Facades\Log;

if (!function_exists('t')) {
    /**
     * 다국어 번역 함수
     *
     * @param string $key 번역 키
     * @param string|null $locale 언어 코드 (null이면 현재 로케일 사용)
     * @return string
     */
    function t(string $key, ?string $locale = null): string
    {
        // 로케일이 지정되지 않으면 현재 앱 로케일 사용
        $locale = $locale ?: app()->getLocale();
        
        // 현재 컨텍스트 정보 가져오기
        $contextInfo = 'unknown';
        
        try {
            // 1. 현재 라우트 이름 시도
            if (app()->bound('request')) {
                $currentRoute = request()->route();
                if ($currentRoute && $currentRoute->getName()) {
                    $contextInfo = $currentRoute->getName();
                }
            }
            
            // 2. 라우트 이름이 없으면 뷰 파일 경로에서 추출
            if ($contextInfo === 'unknown') {
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
                foreach ($backtrace as $trace) {
                    if (isset($trace['file']) && strpos($trace['file'], '/views/') !== false) {
                        $viewPath = $trace['file'];
                        // views/ 이후 경로만 추출하고 .blade.php 제거
                        if (preg_match('/\/views\/(.+)\.blade\.php/', $viewPath, $matches)) {
                            $contextInfo = str_replace('/', '.', $matches[1]);
                            break;
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            $contextInfo = 'error';
        }
        
        // 번역 가져오기
        $translation = Language::translate($key, $locale, $contextInfo);
        
        // 디버그 모드에서만 컨텍스트 정보를 번역 텍스트 옆에 표시
        // if (config('app.debug') && config('app.env') === 'local') {
        //     return $translation . ' - ' . $contextInfo;
        // }
        
        return $translation;
    }
}

if (!function_exists('set_locale')) {
    /**
     * 언어 설정
     *
     * @param string $locale
     * @return void
     */
    function set_locale(string $locale): void
    {
        app()->setLocale($locale);
        session(['locale' => $locale]);
    }
}

if (!function_exists('get_available_languages')) {
    /**
     * 사용 가능한 언어 목록 가져오기
     *
     * @return array
     */
    function get_available_languages(): array
    {
        return Language::getAvailableLanguages();
    }
}
