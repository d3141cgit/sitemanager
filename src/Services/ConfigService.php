<?php

namespace SiteManager\Services;

use SiteManager\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
// use Illuminate\Support\Facades\Log;

/**
 * ConfigService - 애플리케이션 설정 관리 서비스
 * 
 * 이 서비스는 애플리케이션의 설정값들을 데이터베이스에 저장하고 관리합니다.
 * .env 파일과 동기화되는 시스템 설정과 일반 애플리케이션 설정을 모두 지원합니다.
 * 
 * ## 설정 타입:
 * - cfg.text: 시스템 환경변수 (텍스트, .env 파일과 동기화)
 * - cfg.bool: 시스템 환경변수 (불린, .env 파일과 동기화)  
 * - text: 일반 애플리케이션 텍스트 설정
 * - bool: 일반 애플리케이션 불린 설정
 * - hidden: 시스템 내부용 설정 (관리자 페이지에서 숨김)
 * 
 * ## 사용법:
 * 
 * ### 관리자 페이지용 설정 조회 (hidden 제외):
 * ```php
 * $configs = ConfigService::get(); // 모든 관리 가능한 설정들
 * $specific = ConfigService::get('APP_NAME'); // 특정 설정값
 * ```
 * 
 * ### 일반 설정값 저장/조회:
 * ```php
 * // 기본적으로 hidden 타입으로 저장 (시스템 내부용)
 * ConfigService::set('CACHE_VERSION', time());
 * ConfigService::set('LAST_BACKUP', now());
 * 
 * // 타입을 명시적으로 지정
 * ConfigService::set('USER_SETTING', 'value', 'text');
 * ConfigService::set('FEATURE_FLAG', true, 'bool');
 * 
 * // 설정값 조회 (모든 타입 포함)
 * $value = ConfigService::getValue('CACHE_VERSION');
 * $value = ConfigService::getValue('UNKNOWN_KEY', 'default_value');
 * ```
 * 
 * ### 시스템 설정 관리:
 * ```php
 * // 시스템 필수 설정인지 확인
 * $isSystem = ConfigService::isSystemConfig('APP_NAME'); // true
 * 
 * // 기존 설정 업데이트 (타입 포함)
 * ConfigService::update('SITE_NAME', '새 사이트명', 'text');
 * ConfigService::update('DEBUG_MODE', true); // 타입 유지
 * ```
 * 
 * ### 개발/디버깅용:
 * ```php
 * // 모든 hidden 설정 조회
 * $hiddenConfigs = ConfigService::getAllHidden();
 * ```
 * 
 * ### 헬퍼 함수 (권장):
 * ```php
 * // 설정값 조회 - config_get() 헬퍼 함수
 * $siteName = config_get('SITE_NAME');
 * $defaultValue = config_get('UNKNOWN_KEY', 'default');
 * 
 * // Blade 템플릿에서 사용
 * {{ config_get('SITE_NAME') }}
 * {{ config_get('SITE_DESCRIPTION') }}
 * 
 * // 설정값 저장 - config_set() 헬퍼 함수
 * config_set('CACHE_VERSION', time()); // hidden 타입으로 저장
 * config_set('USER_SETTING', 'value', 'text'); // 타입 지정
 * config_set('FEATURE_FLAG', true, 'bool');
 * ```
 * 
 * ## 시스템 보호:
 * - $cfg_system에 정의된 설정들은 삭제 및 키/타입 변경이 불가능
 * - cfg.* 타입의 설정들은 자동으로 .env 파일과 동기화
 * - hidden 타입은 관리자 페이지에서 보이지 않음
 */
class ConfigService
{
    public static $cfg_type = ['text', 'bool'];
    
    public static $cfg_system = [
        'IsDevel'           => ['cfg.bool', false],

        'APP_NAME'          => ['cfg.text', 'sitemanager'],
        'APP_URL'           => ['cfg.text', ''],
        'APP_TIMEZONE'      => ['cfg.text', 'America/Chicago'],
        'APP_LOCALE'        => ['cfg.text', 'en'],

        'HOST_NAME'         => ['cfg.text', 'localhost'],
        'MAIL_FROM_NAME'    => ['cfg.text', ''],
        'MAIL_FROM_ADDRESS' => ['cfg.text', ''],

        'AUTO_DARK_MODE'    => ['bool', false],
        'USE_DARK_MODE'     => ['bool', false],
        'SITE_NAME'         => ['text', 'Site Manager'],
        'SITE_DESCRIPTION'  => ['text', 'Site Manager - 웹사이트 관리 시스템'],
        'SITE_KEYWORDS'     => ['text', '사이트 관리, 웹사이트, 관리자, CMS'],
        'SITE_AUTHOR'       => ['text', 'Site Manager'],
    ];

    public static function get($key = '')
    {
        if ($key) {
            if (is_numeric($key)) {
                return Setting::find($key);
            } else {
                return Setting::where('key', '=', $key)->value('value');
            }
        } else {
            // 'hidden' 타입은 관리자 페이지에서 숨김 (시스템 내부용 설정)
            return Setting::where('type', '!=', 'hidden')->orderBy('type')->orderBy('key')->get();
        }
    }

    public static function process(Request $request)
    {
        // system config 초기화
        foreach (self::$cfg_system as $key => $value) {
            if ((is_array($request->key) and !in_array($key, $request->key)) or empty($request->key)) {
                $data = [
                    'type' => $value[0],
                    'key' => $key,
                    'value' => $value[1],
                ];

                Setting::firstOrCreate(['key' => $key], $data);
            } else {
                Setting::where('key', $key)->where('type', '!=', $value[0])->update(['type' => $value[0]]);
            }
        }

        // 새로운 설정 추가
        if (!empty($request->new_key)) {
            $request->validate([
                'new_key' => 'regex:/^[a-z0-9_-]+$/i'
            ]);

            $data = [
                'type' => $request->new_type,
                'key' => $request->new_key,
                'value' => $request->new_val,
            ];
            Setting::create($data);
        }

        // 기존 설정 업데이트
        if (!empty($request->key)) {
            foreach ($request->key as $cfg_id => $value) {
                $setting = Setting::find($cfg_id);
                if (!$setting) continue;
                
                // 시스템 필수 설정들은 삭제 불가
                $isSystemConfig = array_key_exists($setting->key, self::$cfg_system);
                
                if (empty($request->key[$cfg_id]) && !$isSystemConfig) {
                    // 시스템 설정이 아닌 경우만 삭제 허용
                    $setting->delete();
                } else {
                    $key = isset($request->key[$cfg_id]) ? preg_replace('/[^a-z0-9_-]/i', '', $request->key[$cfg_id]) : '';
                    $val = isset($request->val[$cfg_id]) ? $request->val[$cfg_id] : '';

                    if (!empty($key)) {
                        $cfg_type = $request->type[$cfg_id];
                        
                        // 시스템 설정의 경우 키와 타입 변경 불가
                        if ($isSystemConfig) {
                            $key = $setting->key; // 원래 키 유지
                            $cfg_type = self::$cfg_system[$setting->key][0]; // 원래 타입 유지
                        }
                        
                        $data = [
                            'type' => $cfg_type,
                            'key' => $key,
                        ];

                        if (empty($val)) {
                            if ($cfg_type == 'bool' or $cfg_type == 'cfg.bool') {
                                $data['value'] = false;
                            } else {
                                $data['value'] = null;
                            }
                        } else {
                            $data['value'] = $val;
                        }

                        Setting::where('id', $cfg_id)->update($data);
                    }
                }
            }
        }

        // .env 파일 업데이트
        self::updateEnvFile();
    }

    public static function update($key, $val, $type = '')
    {
        if ($type) {
            Setting::where('key', $key)->update(['value' => $val, 'type' => $type]);
        } else {
            Setting::where('key', $key)->update(['value' => $val]);
        }
    }

    /**
     * 시스템 필수 설정인지 확인
     */
    public static function isSystemConfig($key)
    {
        return array_key_exists($key, self::$cfg_system);
    }

    /**
     * 설정값 저장 (타입 지정 가능)
     */
    public static function set($key, $value, $type = 'hidden')
    {
        return Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }

    /**
     * 설정값 조회 (모든 타입 포함)
     */
    public static function getValue($key, $default = null)
    {
        $setting = Setting::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * 모든 Hidden 설정 조회 (개발자/디버깅용)
     */
    public static function getAllHidden()
    {
        return Setting::where('type', 'hidden')->orderBy('key')->get();
    }

    /**
     * 설정을 기본값으로 초기화
     */
    public static function resetToDefaults()
    {
        // 시스템 설정들을 기본값으로 업데이트
        foreach (self::$cfg_system as $key => $config) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'type' => $config[0],
                    'value' => $config[1]
                ]
            );
        }

        // 사용자가 추가한 설정들 (시스템 설정이 아닌 것들) 삭제
        $systemKeys = array_keys(self::$cfg_system);
        Setting::whereNotIn('key', $systemKeys)
               ->where('type', '!=', 'hidden') // hidden 타입은 보존
               ->delete();

        // .env 파일 업데이트
        self::updateEnvFile();
    }

    private static function updateEnvFile()
    {
        try {
            $envFile = base_path('.env');
            
            if (!file_exists($envFile)) {
                throw new \Exception('.env file not found');
            }
            
            $str = file_get_contents($envFile);
            $envChanged = false;

            $configs = Setting::where('type', 'like', 'cfg.%')->get();
            foreach ($configs as $config) {
                if ($config->key == 'IsDevel') {
                    if ($config->value == false) {
                        self::setEnv($str, "APP_ENV", "production");
                        self::setEnv($str, "APP_DEBUG", "false");
                        $envChanged = true;
                    } else {
                        self::setEnv($str, "APP_ENV", "local");
                        self::setEnv($str, "APP_DEBUG", "true");
                        $envChanged = true;
                    }
                } else {
                    if (!empty($config->value)) {
                        $envValue = "'" . $config->value . "'";
                        self::setEnv($str, $config->key, $envValue);
                        $envChanged = true;
                    } else {
                        $envValue = env($config->key);
                        if ($envValue !== null) {
                            Setting::where('key', $config->key)->update(['value' => $envValue]);
                        }
                    }
                }
            }

            file_put_contents($envFile, $str);
            
            // .env 파일이 변경되었으면 config 캐시 클리어
            if ($envChanged) {
                self::clearConfigCache();
            }
        } catch (\Exception $e) {
            // .env 파일 업데이트 실패 시 로그만 남기고 계속 진행
            // Log::error('Failed to update .env file: ' . $e->getMessage());
        }
    }

    private static function setEnv(&$str, $envKey, $envVal)
    {
        // 더 정확한 정규식 패턴 사용 - 키 시작부터 줄 끝까지 매칭
        $pattern = "/^" . preg_quote($envKey, '/') . "\s*=.*$/m";
        $replacement = $envKey . "=" . $envVal;
        
        if (preg_match($pattern, $str)) {
            // 기존 키가 있으면 교체
            $str = preg_replace($pattern, $replacement, $str);
        } else {
            // 키가 없으면 파일 끝에 추가
            $str = rtrim($str) . "\n" . $replacement . "\n";
        }
        
        return $str;
    }

    /**
     * .env 파일의 중복 키 정리
     */
    public static function cleanDuplicateEnvKeys()
    {
        try {
            $envFile = base_path('.env');
            
            if (!file_exists($envFile)) {
                throw new \Exception('.env file not found');
            }
            
            $lines = file($envFile, FILE_IGNORE_NEW_LINES);
            $cleanedLines = [];
            $seenKeys = [];
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // 빈 줄이나 주석은 그대로 유지
                if (empty($line) || strpos($line, '#') === 0) {
                    $cleanedLines[] = $line;
                    continue;
                }
                
                // KEY=VALUE 패턴 확인
                if (preg_match('/^([A-Z_][A-Z0-9_]*)\s*=/', $line, $matches)) {
                    $key = $matches[1];
                    
                    // 처음 나오는 키만 유지, 중복된 키는 마지막 값으로 덮어쓰기
                    $seenKeys[$key] = $line;
                } else {
                    $cleanedLines[] = $line;
                }
            }
            
            // 고유한 키들을 추가
            foreach ($seenKeys as $line) {
                $cleanedLines[] = $line;
            }
            
            // 파일에 쓰기
            file_put_contents($envFile, implode("\n", $cleanedLines) . "\n");
            
            return true;
        } catch (\Exception $e) {
            // Log::error('Failed to clean duplicate .env keys: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Config 캐시 클리어
     */
    private static function clearConfigCache()
    {
        try {
            // Laravel의 config 캐시 클리어
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            
            // APP_LOCALE이 변경된 경우 현재 세션에도 즉시 적용
            $newLocale = env('APP_LOCALE');
            if ($newLocale && app()->getLocale() !== $newLocale) {
                app()->setLocale($newLocale);
                // 언어 캐시도 클리어
                \SiteManager\Models\Language::clearCache();
            }
            
        } catch (\Exception $e) {
            // 캐시 클리어 실패 시 로그만 남기고 계속 진행
            // Log::error('Failed to clear config cache: ' . $e->getMessage());
        }
    }
}
