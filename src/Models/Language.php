<?php

namespace SiteManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Language extends Model
{
    protected $fillable = [
        'key',
        'ko',
        'tw',
        'location',
    ];

    /**
     * timestamps 사용 안함
     */
    public $timestamps = false;

    /**
     * 언어 키로 번역된 텍스트 가져오기
     */
    public static function translate(string $key, string $locale = null, string $context = null): string
    {
        // locale이 null이면 현재 앱 로케일 사용
        $locale = $locale ?: app()->getLocale();
        
        // 캐시 키 생성
        $cacheKey = "lang.{$locale}.{$key}";
        
        // 캐시에서 먼저 확인
        $translation = Cache::remember($cacheKey, 3600, function () use ($key, $locale, $context) {
            $language = static::where('key', $key)->first();
            
            if (!$language) {
                // 언어 키가 없으면 새로 생성 (기본값은 키 자체)
                $language = static::create([
                    'key' => $key,
                ]);
                
                // 디버그 모드에서 새로 생성된 키 로깅
                if (config('app.debug') && $context) {
                    Log::info("New translation key created: '{$key}' from context: {$context}");
                }
            }

            // LANGUAGE_TRACE가 활성화되어 있으면 location 정보 업데이트
            if ($context && $context !== 'unknown' && config('sitemanager.language.trace_enabled', false)) {
                $language->addLocation($context);
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
     * 위치 정보 추가 (중복 방지)
     */
    public function addLocation(string $context): void
    {
        if (!$context || $context === 'unknown') {
            return;
        }

        $locations = $this->getLocationArray();
        
        if (!in_array($context, $locations)) {
            $locations[] = $context;
            $this->location = implode(',', $locations);
            $this->save();
        }
    }

    /**
     * 위치 정보를 배열로 반환
     */
    public function getLocationArray(): array
    {
        if (!$this->location) {
            return [];
        }
        
        return array_filter(explode(',', $this->location));
    }

    /**
     * 모든 location을 null로 초기화
     */
    public static function clearAllLocations(): void
    {
        static::query()->update(['location' => null]);
        static::clearCache();
    }

    /**
     * location이 없는 키들만 조회
     */
    public static function withoutLocation()
    {
        return static::where(function($query) {
            $query->whereNull('location')
                  ->orWhere('location', '');
        });
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
