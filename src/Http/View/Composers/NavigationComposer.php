<?php

namespace SiteManager\Http\View\Composers;

use Illuminate\View\View;
use SiteManager\Services\PermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
            'seoData' => $seoData,
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
                if ($menu->hidden)
                    continue;

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

            // section_label을 메모리에서 찾기 (쿼리 방지)
            $sectionLabel = $sectionKey;
            $rootMenu = $menuCollection->first(function ($menu) {
                return $menu->depth === 0;
            });
            if ($rootMenu) {
                $sectionLabel = $rootMenu->title;
            }

            $sections[$sectionKey] = [
                'section' => $sectionKey,
                'section_label' => $sectionLabel,
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
        $customPathMatch = $menus->filter(function ($menu) use ($currentPath) {
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
        $exactMatch = $menus->filter(function ($menu) use ($currentRouteName) {
            return $menu && $menu->type === 'route' && $menu->target === $currentRouteName;
        })->first();

        if ($exactMatch) {
            return $exactMatch;
        }

        // 3. menuId 파라미터를 사용한 라우트 매칭
        if ($currentRouteName) {
            $menuIdFromRoute = request()->route('menuId');
            if ($menuIdFromRoute) {
                $menuIdMatch = $menus->filter(function ($menu) use ($currentRouteName, $menuIdFromRoute) {
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
        $urlMatch = $menus->filter(function ($menu) use ($currentUrl, $currentPath) {
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
        $patternMatch = $menus->filter(function ($menu) use ($currentPath) {
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
        })->sortByDesc(function ($menu) {
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
            if (!$menu)
                continue; // null 체크

            $isLast = $index === count($menuChain) - 1;

            // 형제 메뉴들 찾기 (같은 parent_id를 가진 메뉴들)
            $siblings = $menus->filter(function ($sibling) use ($menu) {
                if (!$sibling)
                    return false;

                // 사용자 권한 확인
                $userPerm = $this->permissionService->checkMenuPermission($sibling);
                if (($userPerm & 1) !== 1)
                    return false; // 읽기 권한 없음

                // 같은 부모를 가진 메뉴들
                return $sibling->parent_id === $menu->parent_id
                    && $sibling->section === $menu->section
                    && $sibling->id !== $menu->id; // 현재 메뉴 제외
            })->sortBy('_lft');

            // alternatives 배열 구성
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

        // 섹션별 탭 동작 설정 확인
        $tabBehavior = config('sitemanager.menu.tab_behavior.' . $currentMenu->section, 'siblings');

        // 탭에 포함할 메뉴들 찾기
        if ($tabBehavior === 'same_depth_in_section') {
            // 같은 섹션 내 같은 depth의 모든 메뉴들
            $siblings = $menus->filter(function ($menu) use ($currentMenu) {
                return $menu &&
                    $menu->section === $currentMenu->section &&
                    $menu->depth === $currentMenu->depth;
            });
        } else {
            // 기본 동작: 형제 메뉴들만
            $parentMenu = $currentMenu->parent_id ? $menus->find($currentMenu->parent_id) : null;

            if ($parentMenu) {
                // 부모가 있는 경우: 같은 부모를 가진 메뉴들
                $siblings = $menus->filter(function ($menu) use ($parentMenu) {
                    return $menu && $menu->parent_id === $parentMenu->id;
                });
            } else {
                // 루트 메뉴인 경우: 같은 섹션의 루트 메뉴들
                $siblings = $menus->filter(function ($menu) use ($currentMenu) {
                    return $menu && $menu->section === $currentMenu->section && $menu->parent_id === null;
                });
            }
        }

        $tabs = [];
        foreach ($siblings as $menu) {
            if (!$menu)
                continue; // null 체크

            // 사용자 권한 확인
            $userPerm = $this->permissionService->checkMenuPermission($menu);
            if (($userPerm & 1) !== 1)
                continue; // 읽기 권한 없음

            $tabs[] = [
                'title' => $menu->title ?? 'Menu',
                'url' => $this->getMenuUrl($menu),
                'is_current' => $menu->id === $currentMenu->id,
                'menu_id' => $menu->id ?? null,
                'icon' => $menu->icon ?? null
            ];
        }

        // _lft 순서대로 정렬
        usort($tabs, function ($a, $b) use ($menus) {
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
     * 메뉴의 첫 번째 linkable한 자식 메뉴의 URL을 반환합니다.
     */
    private function getFirstLinkableChildUrl($menu, $menus)
    {
        if (!$menu) {
            return null;
        }

        // 자식 메뉴들 찾기
        $children = $menus->filter(function ($child) use ($menu) {
            if (!$child)
                return false;

            // 사용자 권한 확인
            $userPerm = $this->permissionService->checkMenuPermission($child);
            if (($userPerm & 1) !== 1)
                return false;

            return $child->parent_id === $menu->id;
        })->sortBy('_lft');

        foreach ($children as $child) {
            $childUrl = $this->getMenuUrl($child);
            if ($childUrl && $childUrl !== '#') {
                return $childUrl;
            }

            // 자식의 자식도 확인 (재귀)
            $grandchildUrl = $this->getFirstLinkableChildUrl($child, $menus);
            if ($grandchildUrl && $grandchildUrl !== '#') {
                return $grandchildUrl;
            }
        }

        return null;
    }

    /**
     * 현재 메뉴 정보를 기반으로 SEO 데이터를 구성합니다.
     */
    private function buildSeoData($currentMenu, $breadcrumb)
    {
        // 메뉴에 저장된 SEO 메타 설정 (json 컬럼)
        $menuSeoMeta = null;
        if ($currentMenu && isset($currentMenu->seo_meta)) {
            $menuSeoMeta = is_array($currentMenu->seo_meta) ? $currentMenu->seo_meta : null;
        }

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
            'noindex' => false,
            'custom_json_ld' => null,
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

            // 제목 구성 (SITE_NAME이 없으면 제목 끝의 구분자 제거)
            if (!empty($categoryTitles)) {
                $titleParts = [$pageTitle, implode(' - ', $categoryTitles)];
                if (!empty($siteName)) {
                    $titleParts[] = $siteName;
                }
                $seoData['title'] = implode(' | ', $titleParts);
            } else {
                if (!empty($siteName)) {
                    $seoData['title'] = $pageTitle . ' | ' . $siteName;
                } else {
                    $seoData['title'] = $pageTitle;
                }
            }

            // 설명 설정
            $seoData['description'] = $currentMenu->description ?: $this->generateAutoDescription($currentMenu, $breadcrumb);

            // 키워드 설정 (최우선: 설정 키워드, 없으면: 제목과 카테고리 기반)
            $siteKeywords = config_get('SITE_KEYWORDS');
            if (!empty($siteKeywords)) {
                $seoData['keywords'] = $siteKeywords;
            } else {
                $keywords = [$pageTitle];
                $keywords = array_merge($keywords, $categoryTitles);
                if (!empty($siteName)) {
                    $keywords[] = $siteName;
                }
                $seoData['keywords'] = implode(', ', array_unique($keywords));
            }

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

            // Canonical URL (메뉴 SEO 메타에 명시된 값이 있으면 우선 사용)
            $explicitCanonical = $menuSeoMeta['canonical'] ?? null;
            $seoData['canonical_url'] = $explicitCanonical ?: $this->getMenuUrl($currentMenu);

            // JSON-LD 브레드크럼 구조화 데이터 (메뉴 설정으로 on/off 가능)
            $useBreadcrumbJsonLd = true;
            if (isset($menuSeoMeta['schema']) && is_array($menuSeoMeta['schema'])) {
                $useBreadcrumbJsonLd = $menuSeoMeta['schema']['use_breadcrumb'] ?? true;
            }
            $seoData['breadcrumb_json_ld'] = $useBreadcrumbJsonLd
                ? $this->generateBreadcrumbJsonLd($breadcrumb)
                : null;

            // 메뉴에 저장된 SEO 메타로 필드 오버라이드
            if (is_array($menuSeoMeta)) {
                if (isset($menuSeoMeta['title']) && $menuSeoMeta['title'] !== '') {
                    $seoData['title'] = $menuSeoMeta['title'];
                }

                if (array_key_exists('description', $menuSeoMeta) && $menuSeoMeta['description'] !== null && $menuSeoMeta['description'] !== '') {
                    $seoData['description'] = $menuSeoMeta['description'];
                    // OG 설명도 동기화
                    $seoData['og_description'] = $menuSeoMeta['description'];
                }

                if (!empty($menuSeoMeta['keywords'])) {
                    $seoData['keywords'] = $menuSeoMeta['keywords'];
                }

                if (array_key_exists('noindex', $menuSeoMeta)) {
                    $seoData['noindex'] = (bool) $menuSeoMeta['noindex'];
                }

                if (!empty($menuSeoMeta['custom_json_ld'])) {
                    $seoData['custom_json_ld'] = $menuSeoMeta['custom_json_ld'];
                }
            }
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
            $description = "{$menu->title} - {$parentCategory}";
        }

        $description .= " - {$siteName}.";

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
     * 메뉴 최신 업데이트 시간을 캐시에서 가져옵니다.
     * 메뉴가 변경될 때만 캐시가 무효화됩니다.
     */
    private function getMenuLastUpdateTime(): ?string
    {
        return Cache::remember('menu_last_update_time', 300, function () {
            // 5분 캐시, 메뉴 변경 시 clearMenuCache()에서 무효화
            return \SiteManager\Models\Menu::max('updated_at');
        });
    }

    /**
     * 캐시 무효화 메서드
     */
    public static function clearCache(): void
    {
        static::$composerCache = [];
        // 메뉴 최신 업데이트 시간 캐시도 함께 무효화
        Cache::forget('menu_last_update_time');
    }

    /**
     * 요청 기반 캐시 키 생성 (뷰 데이터 무관)
     */
    private function generateRequestCacheKey(): string
    {
        $user = Auth::user();

        // 메뉴 테이블의 최신 업데이트 시간 추가 (캐시 사용)
        $menuLastUpdate = $this->getMenuLastUpdateTime();

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

            // 추가 브레드크럼을 정규화 (단일 → 배열의 배열로 통일)
            if (!isset($additionalBreadcrumb[0]) || !is_array($additionalBreadcrumb[0]) || isset($additionalBreadcrumb['title'])) {
                $additionalBreadcrumb = [$additionalBreadcrumb];
            }

            // 기존 breadcrumb(Home 제외)과 additionalBreadcrumb의 중복 감지
            // 메뉴 시스템이 이미 URL로 현재 메뉴를 잡아서 breadcrumb을 만든 경우,
            // 컨트롤러의 additionalBreadcrumb과 겹칠 수 있음
            $existingWithoutHome = array_slice($breadcrumb, 1); // Home 제외
            $overlapStart = null;

            // 기존 breadcrumb(Home 제외)의 depth와 additionalBreadcrumb의 depth가 같으면
            // 메뉴 시스템이 이미 같은 경로를 잡은 것으로 판단
            if (count($existingWithoutHome) > 0 && count($existingWithoutHome) === count($additionalBreadcrumb)) {
                // 첫 번째 항목의 title이 일치하는지 확인 (대분류 기준)
                $existingFirstTitle = $existingWithoutHome[0]['title'] ?? '';
                $additionalFirstTitle = $additionalBreadcrumb[0]['title'] ?? '';
                if ($existingFirstTitle === $additionalFirstTitle) {
                    $overlapStart = 1; // Home 다음부터 중복
                }
            }

            // depth가 다르더라도 title 순차 비교로 부분 중복 감지
            if ($overlapStart === null) {
                $existingTitles = array_map(function ($item) {
                    return $item['title'] ?? '';
                }, $breadcrumb);
                $firstAdditionalTitle = $additionalBreadcrumb[0]['title'] ?? '';

                for ($i = 1; $i < count($existingTitles); $i++) {
                    if ($existingTitles[$i] === $firstAdditionalTitle) {
                        $isFullOverlap = true;
                        for ($j = 0; $j < count($additionalBreadcrumb); $j++) {
                            $existingIdx = $i + $j;
                            if ($existingIdx >= count($existingTitles) ||
                                $existingTitles[$existingIdx] !== ($additionalBreadcrumb[$j]['title'] ?? '')) {
                                $isFullOverlap = false;
                                break;
                            }
                        }
                        if ($isFullOverlap) {
                            $overlapStart = $i;
                            break;
                        }
                    }
                }
            }

            if ($overlapStart !== null) {
                // 중복 발견: 기존 breadcrumb의 중복 항목에서 alternatives 등 메타 정보를 보존
                $overlappedItems = array_values(array_slice($breadcrumb, $overlapStart));
                $breadcrumb = array_slice($breadcrumb, 0, $overlapStart);

                // 기존 브레드크럼의 마지막 요소를 현재가 아닌 것으로 변경
                if (!empty($breadcrumb)) {
                    $lastIndex = count($breadcrumb) - 1;
                    $breadcrumb[$lastIndex]['is_current'] = false;
                }

                // additionalBreadcrumb에 기존 항목의 alternatives, menu_id 등을 병합
                foreach ($additionalBreadcrumb as $index => $crumb) {
                    $isLast = $index === count($additionalBreadcrumb) - 1;
                    $merged = [
                        'title' => $crumb['title'] ?? 'Page',
                        'url' => $crumb['url'] ?? null,
                        'is_current' => $isLast,
                    ];

                    // 대응하는 기존 항목이 있으면 alternatives, menu_id 보존
                    if (isset($overlappedItems[$index])) {
                        $orig = $overlappedItems[$index];
                        if (!empty($orig['alternatives'])) {
                            $merged['alternatives'] = $orig['alternatives'];
                        }
                        if (isset($orig['menu_id'])) {
                            $merged['menu_id'] = $orig['menu_id'];
                        }
                        // additionalBreadcrumb에 url이 없으면 기존 url 유지
                        if ($merged['url'] === null && !$isLast && isset($orig['url'])) {
                            $merged['url'] = $orig['url'];
                        }
                    }

                    $breadcrumb[] = $merged;
                }
            } else {
                // 중복 없음: 기존 로직대로 append
                // 기존 브레드크럼의 마지막 요소를 현재가 아닌 것으로 변경
                if (!empty($breadcrumb)) {
                    $lastIndex = count($breadcrumb) - 1;
                    $breadcrumb[$lastIndex]['is_current'] = false;
                    if (isset($cachedData['currentMenu'])) {
                        $breadcrumb[$lastIndex]['url'] = $this->getMenuUrl($cachedData['currentMenu']);
                    }
                }

                // additionalBreadcrumb 항목 추가
                foreach ($additionalBreadcrumb as $index => $crumb) {
                    $isLast = $index === count($additionalBreadcrumb) - 1;
                    $breadcrumb[] = [
                        'title' => $crumb['title'] ?? 'Page',
                        'url' => $crumb['url'] ?? null,
                        'is_current' => $isLast
                    ];
                }
            }

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
