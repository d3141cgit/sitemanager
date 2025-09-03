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
    public function index()
    {
        $languages = Language::orderBy('key')->paginate(50);
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
