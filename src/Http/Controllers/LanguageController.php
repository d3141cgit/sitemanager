<?php

namespace SiteManager\Http\Controllers;

use Illuminate\Http\Request;
use SiteManager\Models\Language;
use SiteManager\Services\ConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class LanguageController extends Controller
{
    /**
     * 언어 목록 페이지
     */
    public function index(Request $request)
    {
        $query = Language::query();
        
        // 검색어 필터링
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('key', 'LIKE', "%{$search}%")
                  ->orWhere('ko', 'LIKE', "%{$search}%")
                  ->orWhere('tw', 'LIKE', "%{$search}%")
                  ->orWhere('location', 'LIKE', "%{$search}%");
            });
        }

        // 위치 필터링
        if ($location = $request->get('location')) {
            $query->where('location', 'LIKE', "%{$location}%");
        }
        
        // 번역 상태 필터링
        if ($status = $request->get('status')) {
            switch ($status) {
                case 'translated':
                    // 모든 언어가 번역된 키들
                    $query->whereNotNull('ko')->whereNotNull('tw');
                    break;
                case 'partial':
                    // 일부만 번역된 키들
                    $query->where(function ($q) {
                        $q->where(function ($sq) {
                            $sq->whereNotNull('ko')->whereNull('tw');
                        })->orWhere(function ($sq) {
                            $sq->whereNull('ko')->whereNotNull('tw');
                        });
                    });
                    break;
                case 'untranslated':
                    // 번역되지 않은 키들
                    $query->whereNull('ko')->whereNull('tw');
                    break;
                case 'no_location':
                    // location이 없는 키들
                    $query->where(function ($q) {
                        $q->whereNull('location')
                          ->orWhere('location', '');
                    });
                    break;
            }
        }
        
        $perPage = $request->get('per_page', config('sitemanager.ui.pagination_per_page', 20));
        $perPage = min(max((int)$perPage, 1), 100); // 1-100 범위로 제한

        $languages = $query->orderBy('key')->paginate($perPage);
        $availableLanguages = Language::getAvailableLanguages();
        
        // Location 자동완성을 위한 데이터
        $existingLocations = Language::whereNotNull('location')
            ->where('location', '!=', '')
            ->get()
            ->flatMap(function ($language) {
                return $language->getLocationArray();
            })
            ->unique()
            ->values()
            ->toArray();
        
        return view('sitemanager::sitemanager.languages.index', compact('languages', 'availableLanguages', 'existingLocations'));
    }

    /**
     * 언어 항목 수정
     */
    public function update(Request $request, Language $language)
    {
        try {
            $request->validate([
                'ko' => 'nullable|string|max:500',
                'tw' => 'nullable|string|max:500',
                'location' => 'nullable|string|max:1000',
            ]);

            // 번역 필드와 location 필드 업데이트
            $updateData = $request->only(['ko', 'tw']);
            
            // location 필드가 있으면 추가
            if ($request->has('location')) {
                $locationValue = $request->input('location');
                // 빈 문자열이면 null로 저장
                $updateData['location'] = empty(trim($locationValue)) ? null : trim($locationValue);
            }
            
            $language->update($updateData);

            // AJAX 요청인 경우 JSON 응답 반환
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => t('Translation saved successfully')
                ]);
            }

            return redirect()->back()->with('success', t('Language updated successfully'));
        } catch (\Exception $e) {
            // 에러 로깅
            Log::error('Language update error: ' . $e->getMessage(), [
                'language_id' => $language->id,
                'request_data' => $request->all(),
                'exception' => $e
            ]);

            // AJAX 요청인 경우 JSON 에러 응답 반환
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => t('Error saving translation') . ': ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', t('Error saving translation') . ': ' . $e->getMessage());
        }
    }

    /**
     * 언어 항목 삭제
     */
    public function destroy(Language $language)
    {
        $language->delete();
        
        return redirect()->back()->with('success', t('Language deleted successfully'));
    }

    /**
     * AJAX로 언어 변경
     */
    public function setLocale(Request $request): JsonResponse
    {
        $locale = $request->input('locale');
        
        if (!array_key_exists($locale, Language::getAvailableLanguages())) {
            return response()->json(['error' => 'Invalid locale'], 400);
        }
        
        set_locale($locale);
        
        return response()->json(['success' => true]);
    }

    /**
     * 미사용 언어 키 정리
     */
    public function cleanup()
    {
        // 실제 사용되지 않는 언어 키들을 찾아서 삭제하는 로직
        // (옵션적 기능)
        
        return redirect()->back()->with('success', t('Language cleanup completed'));
    }

    /**
     * 언어 추적 활성화/비활성화
     */
    public function toggleTrace(Request $request): JsonResponse
    {
        $enabled = $request->boolean('enabled');
        
        try {
            // .env 파일에 SITEMANAGER_LANGUAGE_TRACE 값 업데이트
            $envPath = base_path('.env');
            if (!file_exists($envPath)) {
                return response()->json([
                    'success' => false,
                    'message' => t('.env file not found')
                ]);
            }
            
            $envContent = file_get_contents($envPath);
            $envKey = 'SITEMANAGER_LANGUAGE_TRACE';
            $envValue = $enabled ? 'true' : 'false';
            
            // ConfigService의 setEnv 로직을 사용
            $pattern = "/^" . preg_quote($envKey, '/') . "\s*=.*$/m";
            $replacement = $envKey . "=" . $envValue;
            
            if (preg_match($pattern, $envContent)) {
                // 기존 키가 있으면 교체
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                // 키가 없으면 파일 끝에 추가
                $envContent = rtrim($envContent) . "\n" . $replacement . "\n";
            }
            
            file_put_contents($envPath, $envContent);
            
            // 런타임 config도 업데이트
            config(['sitemanager.language.trace_enabled' => $enabled]);
            
            return response()->json([
                'success' => true,
                'message' => $enabled ? t('Language trace enabled') : t('Language trace disabled')
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error toggling trace mode: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => t('Error updating trace mode') . ': ' . $e->getMessage()
            ]);
        }
    }

    /**
     * location 정보 초기화
     */
    public function clearLocations(): JsonResponse
    {
        Language::clearAllLocations();
        
        return response()->json([
            'success' => true,
            'message' => t('All location information cleared')
        ]);
    }

    /**
     * trace 상태 확인
     */
    public function getTraceStatus(): JsonResponse
    {
        return response()->json([
            'enabled' => config('sitemanager.language.trace_enabled', false),
            'total_keys' => Language::count(),
            'keys_with_location' => Language::whereNotNull('location')->where('location', '!=', '')->count(),
            'keys_without_location' => Language::withoutLocation()->count()
        ]);
    }

    /**
     * 현재 페이지의 위치 정보 삭제
     */
    public function clearCurrentPageLocations(Request $request): JsonResponse
    {
        try {
            $currentLocation = $request->input('location');
            
            if (empty($currentLocation)) {
                return response()->json([
                    'success' => false,
                    'message' => t('Current page location not found')
                ]);
            }

            // 현재 위치와 관련된 언어 키들을 찾아서 해당 위치만 제거
            $languages = Language::whereNotNull('location')
                ->where('location', '!=', '')
                ->get();

            $updatedCount = 0;
            foreach ($languages as $language) {
                $locations = $language->getLocationArray();
                
                // 현재 위치가 포함되어 있는지 확인
                if (in_array($currentLocation, $locations)) {
                    // 현재 위치를 제거
                    $newLocations = array_filter($locations, function($loc) use ($currentLocation) {
                        return $loc !== $currentLocation;
                    });
                    
                    // 업데이트
                    $language->update([
                        'location' => empty($newLocations) ? null : implode(',', $newLocations)
                    ]);
                    
                    $updatedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => t('Current page location cleared from') . ' ' . $updatedCount . ' ' . t('keys'),
                'updated_count' => $updatedCount,
                'location' => $currentLocation
            ]);

        } catch (\Exception $e) {
            Log::error('Error clearing current page locations: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => t('Error clearing current page locations') . ': ' . $e->getMessage()
            ]);
        }
    }
}
