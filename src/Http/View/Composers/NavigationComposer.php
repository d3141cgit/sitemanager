<?php

namespace SiteManager\Http\View\Composers;

use Illuminate\View\View;
use SiteManager\Services\PermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;

class NavigationComposer
{
    protected $permissionService;
    protected static $composerCache = [];

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        // 요청별 캐시 키 생성 (뷰 데이터가 아닌 요청 기반)
        $cacheKey = $this->generateRequestCacheKey();
        
        // 이미 계산된 경우 캐시된 데이터 사용
        if (isset(static::$composerCache[$cacheKey])) {
            $cachedData = static::$composerCache[$cacheKey];
            
            // 뷰별 특수 데이터가 있으면 병합
            $viewSpecificData = $this->getViewSpecificData($view, $cachedData);
            $view->with(array_merge($cachedData, $viewSpecificData));
            return;
        }
        
        $user = Auth::user();
        $accessibleMenus = $this->permissionService->getAccessibleMenus($user);
        
        // 네비게이션 트리 구성
        $navigationTree = $this->buildNavigationTree($accessibleMenus);
        
        // 현재 페이지 관련 메뉴 정보 (첫 번째 뷰의 데이터만 사용)
        $viewData = $view->getData();
        
        // 뷰에서 명시적으로 전달된 currentMenuId나 currentMenu가 있으면 우선 사용
        $currentMenuId = null;
        if (isset($viewData['currentMenuId'])) {
            $currentMenuId = $viewData['currentMenuId'];
        } elseif (isset($viewData['currentMenu'])) {
            $currentMenu = $viewData['currentMenu'];
            
            // Menu 객체인 경우 ID 추출, 숫자인 경우 그대로 사용
            if (is_object($currentMenu) && isset($currentMenu->id)) {
                $currentMenuId = $currentMenu->id;
            } elseif (is_numeric($currentMenu)) {
                $currentMenuId = $currentMenu;
            }
        }
        
        if ($currentMenuId) {
            // 메뉴 ID로 실제 메뉴 객체 찾기
            $foundMenu = $accessibleMenus->find($currentMenuId);
            $currentMenu = $foundMenu;
        } else {
            // 자동 감지 방식 사용 (라우트명, URL 패턴 매칭)
            $currentMenu = $this->findCurrentMenuByRoute($accessibleMenus);
        }
        
        $breadcrumb = $this->buildBreadcrumb($currentMenu, $accessibleMenus);
        
        $menuTabs = $this->buildMenuTabs($currentMenu, $accessibleMenus);
        
        // SEO 정보 구성 (기존 seoData가 있으면 우선 사용)
        $existingSeoData = $view->getData()['seoData'] ?? null;
        $seoData = $existingSeoData ?: $this->buildSeoData($currentMenu, $breadcrumb);
        
        $composerData = [
            'navigationMenus' => $navigationTree,
            'flatMenus' => $accessibleMenus,
            'currentMenu' => $currentMenu,
            'breadcrumb' => $breadcrumb,
            'menuTabs' => $menuTabs,
            'seoData' => $seoData
        ];
        
        // 캐시에 저장
        static::$composerCache[$cacheKey] = $composerData;
        
        // 뷰별 특수 데이터 병합
        $viewSpecificData = $this->getViewSpecificData($view, $composerData);
        $view->with(array_merge($composerData, $viewSpecificData));
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
     * 라우트 정보를 기반으로 현재 메뉴를 찾습니다.
     */
    private function findCurrentMenuByRoute($menus)
    {
        $currentRouteName = Route::currentRouteName();
        $currentUrl = Request::url();
        $currentPath = Request::path();
        
        // 1. 커스텀 경로 매칭 (/ 로 시작하는 target)
        $customPathMatch = $menus->filter(function($menu) use ($currentPath) {
            if ($menu && $menu->type === 'route' && $menu->target && str_starts_with($menu->target, '/')) {
                $menuPath = ltrim($menu->target, '/');
                $currentPathClean = ltrim($currentPath, '/');
                return $menuPath === $currentPathClean;
            }
            return false;
        })->first();
        
        if ($customPathMatch) {
            return $customPathMatch;
        }
        
        // 2. 정확한 라우트명 매칭
        $exactMatch = $menus->filter(function($menu) use ($currentRouteName) {
            return $menu && $menu->type === 'route' && $menu->target === $currentRouteName;
        })->first();
        
        if ($exactMatch) {
            return $exactMatch;
        }
        
        // 3. menuId 파라미터를 사용한 라우트 매칭
        if ($currentRouteName) {
            $menuIdFromRoute = request()->route('menuId');
            if ($menuIdFromRoute) {
                $menuIdMatch = $menus->filter(function($menu) use ($currentRouteName, $menuIdFromRoute) {
                    if ($menu && $menu->type === 'route' && $menu->id == $menuIdFromRoute) {
                        // 라우트명이 target과 일치하거나, target이 커스텀 경로인 경우
                        return $menu->target === $currentRouteName || str_starts_with($menu->target, '/');
                    }
                    return false;
                })->first();
                
                if ($menuIdMatch) {
                    return $menuIdMatch;
                }
            }
        }
        
        // 4. URL 패턴 매칭
        $urlMatch = $menus->filter(function($menu) use ($currentUrl, $currentPath) {
            if ($menu && $menu->type === 'url' && $menu->target) {
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
        
        // 5. 패턴 매칭 (예: /about/* 패턴으로 /about/edm-korean-global-campus 매칭)
        $patternMatch = $menus->filter(function($menu) use ($currentPath) {
            if ($menu && $menu->type === 'url' && $menu->target) {
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
            return $menu ? strlen($menu->target ?? '') : 0;
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
                    'is_current' => false,
                    'alternatives' => []
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
        
        // Home 추가 (alternatives 없음)
        $breadcrumb[] = [
            'title' => 'Home',
            'url' => '/',
            'is_current' => false,
            'alternatives' => []
        ];
        
        // 역순으로 브레드크럼 구성
        $menuChain = array_reverse($menuChain);
        foreach ($menuChain as $index => $menu) {
            if (!$menu) continue; // null 체크
            
            $isLast = $index === count($menuChain) - 1;
            
            // 같은 레벨의 형제 메뉴들을 대안으로 찾기 (같은 section 내에서만)
            $siblings = collect();
            if ($menu->parent_id) {
                // 같은 부모를 가진 형제 메뉴들 (같은 section 내)
                $siblings = $menus->filter(function($sibling) use ($menu) {
                    return $sibling && 
                           $sibling->parent_id == $menu->parent_id && 
                           $sibling->id != $menu->id &&
                           $sibling->section == $menu->section && // 같은 section 체크 추가
                           !empty($sibling->permission) && 
                           ($sibling->permission & 1) === 1; // 보기 권한 체크
                });
            } else {
                // 최상위 메뉴들 (같은 section 내)
                $siblings = $menus->filter(function($sibling) use ($menu) {
                    return $sibling && 
                           !$sibling->parent_id && 
                           $sibling->id != $menu->id &&
                           $sibling->section == $menu->section && // 같은 section 체크 추가
                           !empty($sibling->permission) && 
                           ($sibling->permission & 1) === 1; // 보기 권한 체크
                });
            }
            
            // 디버깅: 형제 메뉴 개수 확인
            $siblingCount = $siblings->count();
            
            $alternatives = [];
            foreach ($siblings as $sibling) {
                $alternatives[] = [
                    'title' => $sibling->title ?? 'Menu',
                    'url' => $this->getMenuUrl($sibling),
                    'menu_id' => $sibling->id ?? null
                ];
            }
            
            $breadcrumb[] = [
                'title' => $menu->title ?? 'Menu',
                'url' => $isLast ? null : $this->getMenuUrl($menu),
                'is_current' => $isLast,
                'menu_id' => $menu->id ?? null,
                'alternatives' => $alternatives
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
                return $menu && $menu->parent_id === $parentMenu->id;
            });
        } else {
            // 루트 메뉴인 경우: 같은 섹션의 루트 메뉴들
            $siblings = $menus->filter(function($menu) use ($currentMenu) {
                return $menu && $menu->section === $currentMenu->section && $menu->parent_id === null;
            });
        }
        
        $tabs = [];
        foreach ($siblings as $menu) {
            if (!$menu) continue; // null 체크
            
            // 사용자 권한 확인
            $userPerm = $this->permissionService->checkMenuPermission($menu);
            if (($userPerm & 1) !== 1) continue; // 읽기 권한 없음
            
            $tabs[] = [
                'title' => $menu->title ?? 'Menu',
                'url' => $this->getMenuUrl($menu),
                'is_current' => $menu->id === $currentMenu->id,
                'menu_id' => $menu->id ?? null,
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
        if (!$menu) {
            return '/';
        }
        
        switch ($menu->type) {
            case 'route':
                try {
                    $target = $menu->target;
                    
                    // 커스텀 경로인지 확인 (/ 로 시작하면 커스텀 경로)
                    if (str_starts_with($target, '/')) {
                        // 커스텀 경로는 그대로 반환
                        return $target;
                    }
                    
                    // 라우트명인 경우 기존 로직 사용
                    $routeName = $target;
                    $routeParameters = [];
                    
                    // 메뉴의 route_parameters 속성이 있으면 사용
                    if (!empty($menu->route_parameters)) {
                        $routeParameters = is_array($menu->route_parameters) 
                            ? $menu->route_parameters 
                            : json_decode($menu->route_parameters, true) ?? [];
                    }
                    
                    // 커스텀 ID 지원 라우트인지 확인 ({menuId?} 패턴)
                    try {
                        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName($routeName);
                        if ($route && str_contains($route->uri(), '{menuId?}')) {
                            // 현재 메뉴 ID를 menuId 파라미터로 추가
                            $routeParameters['menuId'] = $menu->id;
                        }
                    } catch (\Exception $e) {
                        // 라우트가 없어도 계속 진행
                    }
                    
                    // board.index의 경우 연결된 게시판의 slug 파라미터가 필요
                    if ($routeName === 'board.index' && empty($routeParameters['slug'])) {
                        $board = \SiteManager\Models\Board::where('menu_id', $menu->id)->first();
                        if ($board && $board->slug) {
                            $routeParameters['slug'] = $board->slug;
                        } else {
                            // 연결된 게시판이 없으면 메뉴의 slug 속성 사용
                            if (!empty($menu->slug)) {
                                $routeParameters['slug'] = $menu->slug;
                            } else {
                                return '#';
                            }
                        }
                    }
                    
                    return route($routeName, $routeParameters);
                } catch (\Exception $e) {
                    Log::warning("Failed to generate route URL for menu {$menu->id}: " . $e->getMessage());
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
            $siteName = config_get('SITE_NAME');
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
            $siteName = config_get('SITE_NAME');
            $seoData['title'] = $siteName;
            $seoData['description'] = config_get('SITE_DESCRIPTION');
            $seoData['keywords'] = config_get('SITE_KEYWORDS');
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
        $siteName = config_get('SITE_NAME');
        $description = "Learn about {$menu->title}";
        
        if ($breadcrumb && count($breadcrumb) > 2) {
            // 상위 카테고리가 있는 경우
            $parentCategory = $breadcrumb[count($breadcrumb) - 2]['title'];
            $description = "Discover {$menu->title} in our {$parentCategory} section";
        }
        
        $description .= " at {$siteName}.";
        
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

    /**
     * 캐시 무효화 메서드
     */
    public static function clearCache(): void
    {
        static::$composerCache = [];
    }

    /**
     * 요청 기반 캐시 키 생성 (뷰 데이터 무관)
     */
    private function generateRequestCacheKey(): string
    {
        $user = Auth::user();
        
        // 메뉴 테이블의 최신 업데이트 시간 추가
        $menuLastUpdate = \SiteManager\Models\Menu::max('updated_at');
        
        // 요청 레벨의 핵심 정보만 사용
        $keyParts = [
            'user_id' => $user ? $user->id : 'guest',
            'route' => Route::currentRouteName() ?? 'unknown',
            'url_path' => parse_url(Request::url(), PHP_URL_PATH), // 도메인 제외, 경로만
            'menu_update' => $menuLastUpdate ? strtotime($menuLastUpdate) : 0, // 메뉴 변경 감지
        ];
        
        return 'nav_composer_req_' . md5(serialize($keyParts));
    }

    /**
     * 뷰별 특수 데이터 처리
     */
    private function getViewSpecificData(View $view, array $cachedData): array
    {
        $viewData = $view->getData();
        $viewSpecificData = [];
        
        // 뷰에서 전달된 추가 브레드크럼 처리
        if (isset($viewData['additionalBreadcrumb'])) {
            $additionalBreadcrumb = $viewData['additionalBreadcrumb'];
            $breadcrumb = $cachedData['breadcrumb'] ?? [];
            
            // 기존 브레드크럼의 마지막 요소를 현재가 아닌 것으로 변경
            if (!empty($breadcrumb)) {
                $lastIndex = count($breadcrumb) - 1;
                $breadcrumb[$lastIndex]['is_current'] = false;
                if (isset($cachedData['currentMenu'])) {
                    $breadcrumb[$lastIndex]['url'] = $this->getMenuUrl($cachedData['currentMenu']);
                }
            }
            
            // 추가 브레드크럼 요소 추가
            $breadcrumb[] = [
                'title' => $additionalBreadcrumb['title'] ?? 'Page',
                'url' => $additionalBreadcrumb['url'] ?? null,
                'is_current' => true
            ];
            
            $viewSpecificData['breadcrumb'] = $breadcrumb;
        }
        
        // 뷰에서 명시적으로 currentMenuId가 전달된 경우
        if (isset($viewData['currentMenuId']) && $viewData['currentMenuId'] !== ($cachedData['currentMenu']->id ?? null)) {
            // 다른 메뉴가 지정된 경우 캐시를 사용하지 않고 재계산 필요
            // 이 경우는 별도 처리가 필요하지만, 일반적으로 드물어야 함
        }
        
        return $viewSpecificData;
    }

    /**
     * 캐시 키 생성 (호환성 유지용, 곧 제거 예정)
     */
    private function generateCacheKey(View $view): string
    {
        return $this->generateRequestCacheKey();
    }
}
