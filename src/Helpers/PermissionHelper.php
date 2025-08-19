<?php

if (!function_exists('getSharedViewData')) {
    /**
     * 뷰에서 공유된 데이터를 가져오는 헬퍼 함수
     */
    function getSharedViewData(string $key, $default = null)
    {
        $factory = app('view');
        $shared = $factory->getShared();
        return $shared[$key] ?? $default;
    }
}

if (!function_exists('hasCurrentMenuPermission')) {
    /**
     * 현재 메뉴에 대한 권한이 있는지 확인합니다.
     */
    function hasCurrentMenuPermission(int $requiredPermission): bool
    {
        $userPermission = getSharedViewData('userPermission', 0);
        return ($userPermission & $requiredPermission) === $requiredPermission;
    }
}

if (!function_exists('getPermissionNames')) {
    /**
     * 현재 권한의 이름들을 반환합니다.
     */
    function getPermissionNames(): array
    {
        return getSharedViewData('permissionNames', []);
    }
}

if (!function_exists('getCurrentMenu')) {
    /**
     * 현재 메뉴를 반환합니다.
     */
    function getCurrentMenu()
    {
        return getSharedViewData('currentMenu', null);
    }
}

if (!function_exists('can')) {
    /**
     * 범용 권한 체크 함수
     * 
     * @param string $permission 권한 이름
     * @param mixed $model 권한을 체크할 모델 (Board, Menu 등)
     * @return bool
     */
    function can(string $permission, $model): bool
    {
        // 권한 이름을 비트값으로 매핑
        $permissionMap = [
            // 기본 권한
            'index' => 1,
            'read' => 2,
            'write' => 32,
            'fullControl' => 128,
            'manage' => 128, // fullControl과 동일
            
            // 댓글 권한
            'readComments' => 4,
            'writeComments' => 8,
            'uploadCommentFiles' => 16,
            'manageComments' => 128,
            
            // 파일 업로드 권한
            'uploadFiles' => 64,
            
            // 별칭들
            'view' => 1,        // index와 동일
            'create' => 8,      // writeComments와 동일 (댓글 생성)
            'update' => 8,      // writeComments와 동일 (댓글 수정)
            'delete' => 128,    // manageComments와 동일 (댓글 삭제)
            'reply' => 8,       // writeComments와 동일 (답글)
        ];
        
        // 권한 이름이 매핑에 없으면 false 반환
        if (!isset($permissionMap[$permission])) {
            return false;
        }
        
        $requiredPermission = $permissionMap[$permission];
        
        // 모든 권한은 메뉴를 통해서만 작동
        if ($model instanceof \App\Models\Board) {
            // 게시판이 메뉴에 연결되지 않은 경우 관리자만 접근 가능
            if (!$model->menu_id) {
                $user = auth()->user();
                return $user && $user->level >= config('member.admin_level', 200);
            }
            
            // 메뉴에 연결된 경우 메뉴 권한을 통해 체크
            $menu = $model->menu;
            if (!$menu) {
                return false;
            }
            
            return hasMenuPermission($menu, $requiredPermission);
        } elseif ($model instanceof \App\Models\Menu) {
            return hasMenuPermission($model, $requiredPermission);
        }
        
        // 지원하지 않는 모델 타입
        return false;
    }
}

if (!function_exists('hasMenuPermission')) {
    /**
     * 메뉴 권한이 있는지 확인합니다.
     */
    function hasMenuPermission(\App\Models\Menu $menu, int $requiredPermission): bool
    {
        $user = auth()->user();
        $permissionService = app(\App\Services\PermissionService::class);
        
        // 메뉴에 대한 사용자의 최종 권한 계산
        $userPermission = $permissionService->checkMenuPermission($menu, $user);
        
        // 비트마스크로 권한 체크
        return $permissionService->hasPermission($userPermission, $requiredPermission);
    }
}
