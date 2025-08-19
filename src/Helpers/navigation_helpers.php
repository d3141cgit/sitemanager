<?php

use Illuminate\Support\Facades\Log;

if (!function_exists('is_active_route')) {
    /**
     * 현재 요청이 지정된 라우트와 일치하는지 확인
     */
    function is_active_route($route, $parameters = [])
    {
        if (is_array($route)) {
            foreach ($route as $r) {
                if (request()->routeIs($r)) {
                    return true;
                }
            }
            return false;
        }
        
        return request()->routeIs($route, $parameters);
    }
}

if (!function_exists('is_active_url')) {
    /**
     * 현재 URL이 지정된 URL과 일치하는지 확인
     */
    function is_active_url($url, $exact = false)
    {
        $currentUrl = request()->url();
        
        if ($exact) {
            return $currentUrl === $url;
        }
        
        return str_starts_with($currentUrl, $url);
    }
}

if (!function_exists('menu_active_class')) {
    /**
     * 메뉴가 활성 상태일 때 CSS 클래스 반환
     */
    function menu_active_class($menu, $class = 'active')
    {
        if (!is_array($menu)) {
            return '';
        }
        
        if (empty($menu['target'])) {
            return '';
        }
        
        $isActive = false;
        
        if ($menu['type'] === 'route') {
            $isActive = is_active_route($menu['target']);
        } elseif ($menu['type'] === 'url') {
            $isActive = is_active_url($menu['target']);
        }
        
        return $isActive ? $class : '';
    }
}

if (!function_exists('get_menu_url')) {
    /**
     * 메뉴 타입에 따라 URL 반환
     */
    function get_menu_url($menu)
    {
        if (!is_array($menu) || empty($menu['target'])) {
            return '#';
        }
        
        switch ($menu['type']) {
            case 'route':
                try {
                    $routeName = $menu['target'];
                    
                    // 라우트에 필요한 파라미터 처리
                    $routeParams = [];
                    
                    // board.index의 경우 연결된 게시판의 slug 파라미터가 필요
                    if ($routeName === 'board.index') {
                        $menuId = $menu['id'] ?? null;
                        if ($menuId) {
                            $board = \App\Models\Board::where('menu_id', $menuId)->first();
                            if ($board && $board->slug) {
                                $routeParams['slug'] = $board->slug;
                            } else {
                                // 연결된 게시판이 없으면 기본값 사용
                                Log::warning("No board found for menu ID: {$menuId}");
                                return '#';
                            }
                        } else {
                            Log::warning("Menu ID not provided for board.index route");
                            return '#';
                        }
                    }
                    
                    // 기타 파라미터가 필요한 라우트들을 여기에 추가 가능
                    
                    return route($routeName, $routeParams);
                } catch (Exception $e) {
                    // Log::warning("Failed to generate route URL for menu", [
                    //     'menu_target' => $menu['target'] ?? 'unknown',
                    //     'menu_id' => $menu['id'] ?? 'unknown',
                    //     'error' => $e->getMessage()
                    // ]);
                    return '#';
                }
                
            case 'url':
                return $menu['target'];
                
            default:
                return '#';
        }
    }
}

if (!function_exists('format_menu_title')) {
    /**
     * 메뉴 제목 포맷팅 (필요시 아이콘 추가)
     */
    function format_menu_title($menu)
    {
        if (!is_array($menu)) {
            return '';
        }
        
        $title = $menu['title'] ?? '';
        
        // 외부 링크인 경우 아이콘 추가
        if (($menu['type'] ?? '') === 'url' && !empty($menu['target'])) {
            if (filter_var($menu['target'], FILTER_VALIDATE_URL) && 
                !str_contains($menu['target'], request()->getHost())) {
                $title .= ' <i class="bi bi-box-arrow-up-right ms-1"></i>';
            }
        }
        
        return $title;
    }
}

if (!function_exists('should_show_menu')) {
    /**
     * 메뉴를 표시할지 여부 결정
     */
    function should_show_menu($menu)
    {
        if (!is_array($menu)) {
            return false;
        }
        
        // 숨김 처리된 메뉴
        if ($menu['hidden'] ?? false) {
            return false;
        }
        
        // 권한 확인 (이미 NavigationComposer에서 필터링되었지만 추가 보안)
        if (isset($menu['permission_required']) && $menu['permission_required'] > 0) {
            $permissionService = app(\App\Services\PermissionService::class);
            
            if (!$permissionService->hasPermission(
                $menu['id'] ?? 0, 
                $menu['permission_required'],
                auth()->id()
            )) {
                return false;
            }
        }
        
        return true;
    }
}

if (!function_exists('get_menu_attributes')) {
    /**
     * 메뉴 링크에 대한 HTML 속성 반환
     */
    function get_menu_attributes($menu)
    {
        $attributes = [];
        
        if (!is_array($menu)) {
            return '';
        }
        
        // 외부 링크인 경우 target="_blank" 추가
        if (($menu['type'] ?? '') === 'url' && !empty($menu['target'])) {
            if (filter_var($menu['target'], FILTER_VALIDATE_URL) && 
                !str_contains($menu['target'], request()->getHost())) {
                $attributes['target'] = '_blank';
                $attributes['rel'] = 'noopener noreferrer';
            }
        }
        
        // 권한 레벨에 따른 데이터 속성
        if (isset($menu['permission_level'])) {
            $attributes['data-permission-level'] = $menu['permission_level'];
        }
        
        // 메뉴 ID
        if (isset($menu['id'])) {
            $attributes['data-menu-id'] = $menu['id'];
        }
        
        // 배열을 HTML 속성 문자열로 변환
        $attrString = '';
        foreach ($attributes as $key => $value) {
            $attrString .= sprintf(' %s="%s"', $key, htmlspecialchars($value));
        }
        
        return $attrString;
    }
}
