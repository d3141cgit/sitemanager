<?php

namespace SiteManager\Services;

use SiteManager\Models\Menu;
use SiteManager\Models\MenuPermission;
use SiteManager\Models\Member;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    /**
     * 메뉴에 대한 사용자의 권한을 확인합니다.
     * 
     * @param int|Menu $menu
     * @param Member|null $user
     * @return int 권한 비트마스크 (0-255)
     */
    public function checkMenuPermission($menu, $user = null): int
    {
        if (!$user) {
            $user = Auth::user();
        }

        if (!$user) {
            // 게스트 사용자: 메뉴의 기본 권한만 반환
            return $this->getMenuBasicPermission($menu);
        }

        // 관리자는 모든 권한
        if ($this->isAdmin($user)) {
            return config('permissions.values.all', 255);
        }

        // 캐시 키 생성
        $cacheKey = "menu_permission_{$user->id}_" . ($menu instanceof Menu ? $menu->id : $menu);
        
        return Cache::remember($cacheKey, 300, function () use ($menu, $user) {
            return $this->calculateUserPermission($menu, $user);
        });
    }

    /**
     * 사용자의 메뉴별 권한을 계산합니다.
     */
    private function calculateUserPermission($menu, $user): int
    {
        if (!$menu instanceof Menu) {
            $menu = Menu::find($menu);
        }

        if (!$menu) {
            return 0;
        }

        $permissions = [];

        // 1. 메뉴 기본 권한
        $permissions[] = $menu->permission ?? 0;

        // 2. 레벨별 권한 확인
        $levelPermission = MenuPermission::where('menu_id', $menu->id)
            ->where('type', 'level')
            ->where('subject_id', '<=', $user->level)
            ->max('permission');
        
        if ($levelPermission) {
            $permissions[] = $levelPermission;
        }

        // 3. 관리자 권한 확인
        $adminPermission = MenuPermission::where('menu_id', $menu->id)
            ->where('type', 'admin')
            ->where('subject_id', $user->id)
            ->value('permission');
        
        if ($adminPermission) {
            $permissions[] = $adminPermission;
        }

        // 4. 그룹 권한 확인
        $groupPermission = MenuPermission::where('menu_id', $menu->id)
            ->where('type', 'group')
            ->whereIn('subject_id', function($query) use ($user) {
                $query->select('group_id')
                    ->from('group_members')
                    ->where('member_id', $user->id);
            })
            ->max('permission');
        
        if ($groupPermission) {
            $permissions[] = $groupPermission;
        }

        // 가장 높은 권한 반환
        return max($permissions);
    }

    /**
     * 메뉴의 기본 권한을 반환합니다.
     */
    private function getMenuBasicPermission($menu): int
    {
        if (!$menu instanceof Menu) {
            $menu = Menu::find($menu);
        }

        return $menu ? ($menu->permission ?? 0) : 0;
    }

    /**
     * 관리자인지 확인합니다.
     */
    private function isAdmin($user): bool
    {
        return $user && $user->isAdmin();
    }

    /**
     * 권한을 비트 배열로 변환합니다 (sf4l 방식).
     */
    public function permissionToBits(int $permission): array
    {
        return array_reverse(str_split(sprintf('%08b', $permission)));
    }

    /**
     * 특정 권한이 있는지 확인합니다.
     */
    public function hasPermission(int $userPermission, int $requiredPermission): bool
    {
        return ($userPermission & $requiredPermission) === $requiredPermission;
    }

    /**
     * 사용자가 접근 가능한 메뉴들을 반환합니다.
     */
    public function getAccessibleMenus($user = null): \Illuminate\Support\Collection
    {
        $user = $user ?: Auth::user();
        
        $cacheKey = $user ? "accessible_menus_{$user->id}" : 'accessible_menus_guest';
        
        return Cache::remember($cacheKey, 600, function () use ($user) {
            $menus = Menu::orderBy('section')->orderBy('_lft')->get();
            return $menus->filter(function ($menu) use ($user) {
                // 숨겨진 메뉴는 제외
                if ($menu->hidden) {
                    return false;
                }
                $permission = $this->checkMenuPermission($menu, $user);
                // Index 권한(1)이 있는 메뉴만 네비게이션에 표시
                return $this->hasPermission($permission, 1);
            });
        });
    }

    /**
     * 권한 캐시를 지웁니다.
     */
    public function clearPermissionCache($userId = null): void
    {
        if ($userId) {
            Cache::forget("accessible_menus_{$userId}");
            // 해당 사용자의 모든 메뉴 권한 캐시 삭제
            $menus = Menu::pluck('id');
            foreach ($menus as $menuId) {
                Cache::forget("menu_permission_{$userId}_{$menuId}");
            }
        } else {
            // 모든 권한 캐시 삭제
            Cache::flush(); // 주의: 모든 캐시가 삭제됩니다
        }
    }

    /**
     * 권한 이름을 반환합니다.
     */
    public function getPermissionNames(int $permission): array
    {
        $permissions = config('permissions.menu');
        $names = [];
        
        foreach ($permissions as $bit => $name) {
            if ($this->hasPermission($permission, $bit)) {
                $names[] = $name;
            }
        }
        
        return $names;
    }
}
