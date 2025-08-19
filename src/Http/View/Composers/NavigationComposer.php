<?php

namespace SiteManager\Http\View\Composers;

use Illuminate\View\View;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;

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
        
        $view->with([
            'navigationMenus' => $navigationTree,
            'flatMenus' => $accessibleMenus
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
}
