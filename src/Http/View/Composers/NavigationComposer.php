<?php

namespace SiteManager\Http\View\Composers;

use Illuminate\View\View;
use SiteManager\Services\PermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;

class NavigationComposer
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $user = Auth::user();
        $accessibleMenus = $this->permissionService->getAccessibleMenus($user);
        
        // 네비게이션 트리 구성
        $navigationTree = $this->buildNavigationTree($accessibleMenus);
        
        // 현재 페이지 관련 메뉴 정보
        $currentMenu = $this->findCurrentMenu($accessibleMenus);
        $breadcrumb = $this->buildBreadcrumb($currentMenu, $accessibleMenus);
        $menuTabs = $this->buildMenuTabs($currentMenu, $accessibleMenus);
        
        // SEO 정보 구성
        $seoData = $this->buildSeoData($currentMenu, $breadcrumb);
        
        $view->with([
            'navigationMenus' => $navigationTree,
            'flatMenus' => $accessibleMenus,
            'currentMenu' => $currentMenu,
            'breadcrumb' => $breadcrumb,
            'menuTabs' => $menuTabs,
            'seoData' => $seoData
        ]);
    }

    /**
     * 메뉴를 트리 구조로 구성합니다.
     */
    private function buildNavigationTree($menus)
    {
        $sections = [];

        // Group menus by section first
        $grouped = $menus->groupBy('section');

        foreach ($grouped as $sectionKey => $menuCollection) {
            $map = [];
            $roots = [];

            // Build map of id => menuData (preserve order from collection)
            foreach ($menuCollection as $menu) {
                // skip hidden just in case
                if ($menu->hidden) continue;

                $menuData = $menu->toArray();
                $menuData['user_permission'] = $this->permissionService->checkMenuPermission($menu);
                $menuData['permission_names'] = $this->permissionService->getPermissionNames($menuData['user_permission']);
                $menuData['children'] = [];

                $map[$menu->id] = $menuData;
            }

            // Link children to their parents
            foreach ($map as $id => &$node) {
                $parentId = $node['parent_id'] ?? null;
                if ($parentId && isset($map[$parentId])) {
                    $map[$parentId]['children'][] = &$node;
                } else {
                    $roots[] = &$node;
                }
            }

            $sections[$sectionKey] = [
                'section' => $sectionKey,
                'section_label' => $menuCollection->first()->section_label ?? $sectionKey,
                'menus' => $roots,
            ];
        }

        return array_values($sections);
    }

    /**
     * 현재 라우트에 해당하는 메뉴를 찾습니다.
     */
    private function findCurrentMenu($menus)
    {
        $currentRouteName = Route::currentRouteName();
        $currentUrl = Request::url();
        $currentPath = Request::path();
        
        // 1. 정확한 라우트명 매칭
        $exactMatch = $menus->filter(function($menu) use ($currentRouteName) {
            return $menu->type === 'route' && $menu->target === $currentRouteName;
        })->first();
        
        if ($exactMatch) {
            return $exactMatch;
        }
        
        // 2. URL 패턴 매칭
        $urlMatch = $menus->filter(function($menu) use ($currentUrl, $currentPath) {
            if ($menu->type === 'url' && $menu->target) {
                $menuUrl = $menu->target;
                // 정확한 URL 매칭
                if ($menuUrl === $currentUrl || $menuUrl === $currentPath) {
                    return true;
                }
                // 부분 매칭 (하위 경로)
                if (str_starts_with($currentPath, rtrim($menuUrl, '/') . '/')) {
                    return true;
                }
            }
            return false;
        })->first();
        
        if ($urlMatch) {
            return $urlMatch;
        }
        
        // 3. 패턴 매칭 (예: /about/* 패턴으로 /about/edm-korean-global-campus 매칭)
        $patternMatch = $menus->filter(function($menu) use ($currentPath) {
            if ($menu->type === 'url' && $menu->target) {
                $menuPath = ltrim($menu->target, '/');
                $currentPathClean = ltrim($currentPath, '/');
                
                // 상위 경로 매칭
                $menuParts = explode('/', $menuPath);
                $currentParts = explode('/', $currentPathClean);
                
                if (count($menuParts) <= count($currentParts)) {
                    $match = true;
                    for ($i = 0; $i < count($menuParts); $i++) {
                        if ($menuParts[$i] !== $currentParts[$i]) {
                            $match = false;
                            break;
                        }
                    }
                    if ($match) {
                        return true;
                    }
                }
            }
            return false;
        })->sortByDesc(function($menu) {
            // 더 구체적인 경로를 우선순위로
            return strlen($menu->target ?? '');
        })->first();
        
        return $patternMatch;
    }

    /**
     * 브레드크럼을 구성합니다.
     */
    private function buildBreadcrumb($currentMenu, $menus)
    {
        if (!$currentMenu) {
            return [
                [
                    'title' => 'Home',
                    'url' => '/',
                    'is_current' => false
                ]
            ];
        }
        
        $breadcrumb = [];
        $menu = $currentMenu;
        
        // 현재 메뉴부터 루트까지 역순으로 수집
        $menuChain = [];
        while ($menu) {
            $menuChain[] = $menu;
            $menu = $menu->parent_id ? $menus->find($menu->parent_id) : null;
        }
        
        // Home 추가
        $breadcrumb[] = [
            'title' => 'Home',
            'url' => '/',
            'is_current' => false
        ];
        
        // 역순으로 브레드크럼 구성
        $menuChain = array_reverse($menuChain);
        foreach ($menuChain as $index => $menu) {
            $isLast = $index === count($menuChain) - 1;
            
            $breadcrumb[] = [
                'title' => $menu->title,
                'url' => $isLast ? null : $this->getMenuUrl($menu),
                'is_current' => $isLast,
                'menu_id' => $menu->id
            ];
        }
        
        return $breadcrumb;
    }

    /**
     * 메뉴 탭을 구성합니다 (현재 메뉴의 형제 메뉴들).
     */
    private function buildMenuTabs($currentMenu, $menus)
    {
        if (!$currentMenu) {
            return [];
        }
        
        // 현재 메뉴의 부모 찾기
        $parentMenu = $currentMenu->parent_id ? $menus->find($currentMenu->parent_id) : null;
        
        // 형제 메뉴들 찾기
        if ($parentMenu) {
            // 부모가 있는 경우: 같은 부모를 가진 메뉴들
            $siblings = $menus->filter(function($menu) use ($parentMenu) {
                return $menu->parent_id === $parentMenu->id;
            });
        } else {
            // 루트 메뉴인 경우: 같은 섹션의 루트 메뉴들
            $siblings = $menus->filter(function($menu) use ($currentMenu) {
                return $menu->section === $currentMenu->section && $menu->parent_id === null;
            });
        }
        
        $tabs = [];
        foreach ($siblings as $menu) {
            // 사용자 권한 확인
            $userPerm = $this->permissionService->checkMenuPermission($menu);
            if (($userPerm & 1) !== 1) continue; // 읽기 권한 없음
            
            $tabs[] = [
                'title' => $menu->title,
                'url' => $this->getMenuUrl($menu),
                'is_current' => $menu->id === $currentMenu->id,
                'menu_id' => $menu->id,
                'icon' => $menu->icon ?? null
            ];
        }
        
        // _lft 순서대로 정렬
        usort($tabs, function($a, $b) use ($menus) {
            $menuA = $menus->find($a['menu_id']);
            $menuB = $menus->find($b['menu_id']);
            return ($menuA->_lft ?? 0) <=> ($menuB->_lft ?? 0);
        });
        
        return $tabs;
    }

    /**
     * 메뉴의 URL을 생성합니다.
     */
    private function getMenuUrl($menu)
    {
        switch ($menu->type) {
            case 'route':
                try {
                    return route($menu->target);
                } catch (\Exception $e) {
                    return '#';
                }
            case 'url':
                return $menu->target ?? '#';
            default:
                return '#';
        }
    }

    /**
     * 현재 메뉴 정보를 기반으로 SEO 데이터를 구성합니다.
     */
    private function buildSeoData($currentMenu, $breadcrumb)
    {
        $seoData = [
            'title' => null,
            'description' => null,
            'keywords' => null,
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
            'og_url' => null,
            'canonical_url' => null,
            'breadcrumb_json_ld' => null,
        ];

        if ($currentMenu) {
            // 기본 제목 설정
            $siteName = config_get('SITE_NAME', 'EDM Korean Global Campus');
            $pageTitle = $currentMenu->title;
            
            // 브레드크럼에서 상위 카테고리 정보 추출
            $categoryTitles = [];
            if ($breadcrumb && count($breadcrumb) > 1) {
                // Home 제외하고 현재 페이지 제외한 중간 카테고리들
                for ($i = 1; $i < count($breadcrumb) - 1; $i++) {
                    $categoryTitles[] = $breadcrumb[$i]['title'];
                }
            }
            
            // 제목 구성
            if (!empty($categoryTitles)) {
                $seoData['title'] = $pageTitle . ' | ' . implode(' - ', $categoryTitles) . ' | ' . $siteName;
            } else {
                $seoData['title'] = $pageTitle . ' | ' . $siteName;
            }
            
            // 설명 설정
            $seoData['description'] = $currentMenu->description ?: $this->generateAutoDescription($currentMenu, $breadcrumb);
            
            // 키워드 설정 (제목과 카테고리 기반)
            $keywords = [$pageTitle];
            $keywords = array_merge($keywords, $categoryTitles);
            $keywords[] = $siteName;
            $keywords[] = 'Korean Language';
            $keywords[] = 'Korean Course';
            $seoData['keywords'] = implode(', ', array_unique($keywords));
            
            // Open Graph 설정
            $seoData['og_title'] = $pageTitle;
            $seoData['og_description'] = $seoData['description'];
            $seoData['og_url'] = request()->url();
            
            // 이미지 설정 (메뉴에 이미지가 있으면 사용)
            $seoData['images'] = []; // 이미지 배열 전체를 저장할 필드 추가
            
            if (!empty($currentMenu->images)) {
                // images가 이미 배열인지 JSON 문자열인지 확인
                $images = is_array($currentMenu->images) 
                    ? $currentMenu->images 
                    : json_decode($currentMenu->images, true);
                
                if (is_array($images) && !empty($images)) {
                    // 전체 이미지 배열을 seoData에 저장 (URL을 asset으로 변환)
                    $seoData['images'] = [];
                    foreach ($images as $category => $imageData) {
                        if (isset($imageData['url'])) {
                            $seoData['images'][$category] = [
                                'url' => \SiteManager\Services\FileUploadService::url($imageData['url']),
                                'original_url' => $imageData['url'],
                                'uploaded_at' => $imageData['uploaded_at'] ?? null
                            ];
                        }
                    }
                    
                    // SEO 카테고리 이미지 우선 사용
                    if (isset($images['seo']['url'])) {
                        $seoData['og_image'] = \SiteManager\Services\FileUploadService::url($images['seo']['url']);
                    }
                    // SEO 이미지가 없으면 thumbnail 사용
                    elseif (isset($images['thumbnail']['url'])) {
                        $seoData['og_image'] = \SiteManager\Services\FileUploadService::url($images['thumbnail']['url']);
                    }
                    // 다른 카테고리 이미지가 있으면 첫 번째 사용
                    else {
                        $firstCategory = array_values($images)[0];
                        if (isset($firstCategory['url'])) {
                            $seoData['og_image'] = \SiteManager\Services\FileUploadService::url($firstCategory['url']);
                        }
                    }
                }
            }
            
            // 기본 이미지 없으면 사이트 기본 이미지 사용
            if (!$seoData['og_image']) {
                $seoData['og_image'] = asset('images/logo.svg');
            }
            
            // Canonical URL
            $seoData['canonical_url'] = $this->getMenuUrl($currentMenu);
            
            // JSON-LD 브레드크럼 구조화 데이터
            $seoData['breadcrumb_json_ld'] = $this->generateBreadcrumbJsonLd($breadcrumb);
        } else {
            // 현재 메뉴가 없는 경우 기본값
            $siteName = config_get('SITE_NAME', 'EDM Korean Global Campus');
            $seoData['title'] = $siteName;
            $seoData['description'] = config_get('SITE_DESCRIPTION', 'Learn Korean language with EDM Korean Global Campus');
            $seoData['keywords'] = config_get('SITE_KEYWORDS', 'Korean language, Korean course, Learn Korean');
            $seoData['og_title'] = $siteName;
            $seoData['og_description'] = $seoData['description'];
            $seoData['og_url'] = request()->url();
            $seoData['og_image'] = asset('images/logo.svg');
            $seoData['canonical_url'] = request()->url();
        }

        return $seoData;
    }

    /**
     * 자동 설명 생성
     */
    private function generateAutoDescription($menu, $breadcrumb)
    {
        $siteName = config_get('SITE_NAME', 'EDM Korean Global Campus');
        $description = "Learn about {$menu->title}";
        
        if ($breadcrumb && count($breadcrumb) > 2) {
            // 상위 카테고리가 있는 경우
            $parentCategory = $breadcrumb[count($breadcrumb) - 2]['title'];
            $description = "Discover {$menu->title} in our {$parentCategory} section";
        }
        
        $description .= " at {$siteName}. Join us for quality Korean language education and cultural experience.";
        
        return $description;
    }

    /**
     * JSON-LD 브레드크럼 구조화 데이터 생성
     */
    private function generateBreadcrumbJsonLd($breadcrumb)
    {
        if (!$breadcrumb || count($breadcrumb) < 2) {
            return null;
        }

        $itemListElement = [];
        foreach ($breadcrumb as $index => $crumb) {
            $item = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['title']
            ];
            
            if (!$crumb['is_current'] && $crumb['url']) {
                $item['item'] = url($crumb['url']);
            }
            
            $itemListElement[] = $item;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $itemListElement
        ];
    }
}
