<?php

namespace SiteManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Language extends Model
{
    protected $fillable = [
        'key',
        'ko',
        'tw',
    ];

    /**
     * timestamps 사용 안함
     */
    public $timestamps = false;

    /**
     * 언어 키로 번역된 텍스트 가져오기
     */
    public static function translate(string $key, string $locale = null): string
    {
        // locale이 null이면 현재 앱 로케일 사용
        $locale = $locale ?: app()->getLocale();
        
        // 캐시 키 생성
        $cacheKey = "lang.{$locale}.{$key}";
        
        // 캐시에서 먼저 확인
        $translation = Cache::remember($cacheKey, 3600, function () use ($key, $locale) {
            $language = static::where('key', $key)->first();
            
            if (!$language) {
                // 언어 키가 없으면 새로 생성 (기본값은 키 자체)
                $language = static::create([
                    'key' => $key,
                ]);
            }
            
            // 요청된 언어의 번역이 있으면 반환
            if ($language->{$locale}) {
                return $language->{$locale};
            }
            
            // fallback: 앱의 fallback locale 확인
            $fallbackLocale = config('app.fallback_locale', 'en');
            if ($locale !== $fallbackLocale && $language->{$fallbackLocale}) {
                return $language->{$fallbackLocale};
            }
            
            // 마지막으로 키 자체 반환 (영어 기본값)
            return $language->key;
        });
        
        return $translation;
    }

    /**
     * 캐시 클리어
     */
    public static function clearCache(): void
    {
        Cache::flush(); // 또는 특정 패턴으로 캐시 삭제
    }

    /**
     * 사용 가능한 언어 목록
     */
    public static function getAvailableLanguages(): array
    {
        return [
            'en' => 'English',
            'ko' => '한국어',
            'tw' => '中文(繁體, 대만)',
        ];
    }

    /**
     * 모델 저장 후 캐시 클리어
     */
    protected static function booted()
    {
        static::saved(function () {
            static::clearCache();
        });

        static::deleted(function () {
            static::clearCache();
        });
    }
}
