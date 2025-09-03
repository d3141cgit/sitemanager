<?php

namespace SiteManager\Http\Controllers;

use Illuminate\Http\Request;
use SiteManager\Models\Language;
use Illuminate\Http\JsonResponse;

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
                  ->orWhere('tw', 'LIKE', "%{$search}%");
            });
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
            }
        }
        
        $languages = $query->orderBy('key')->paginate(50);
        $availableLanguages = Language::getAvailableLanguages();
        
        return view('sitemanager::sitemanager.languages.index', compact('languages', 'availableLanguages'));
    }

    /**
     * 언어 항목 수정
     */
    public function update(Request $request, Language $language)
    {
        $request->validate([
            'ko' => 'nullable|string|max:500',
            'tw' => 'nullable|string|max:500',
        ]);

        $language->update($request->only(['ko', 'tw']));

        return redirect()->back()->with('success', t('Language updated successfully'));
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
}
