<?php

use SiteManager\Models\Language;

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
        
        return Language::translate($key, $locale);
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
