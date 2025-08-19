<?php

namespace SiteManager\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use SiteManager\Services\PermissionService;
use SiteManager\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    protected $permissionService;
    protected $currentMenu;
    protected $userPermission;
    protected $permissionBits;

    public function __construct()
    {
        $this->permissionService = app(PermissionService::class);
    }

    /**
     * 메뉴 권한을 초기화합니다.
     */
    protected function initializeMenuPermission(Request $request, $menuId = null)
    {
        if (!$menuId) {
            // 요청에서 메뉴 정보 가져오기 (미들웨어에서 설정됨)
            $this->currentMenu = $request->get('current_menu');
            $this->userPermission = $request->get('menu_permission', 0);
            $this->permissionBits = $request->get('menu_permission_bits', []);
        } else {
            $this->currentMenu = Menu::find($menuId);
            $this->userPermission = $this->permissionService->checkMenuPermission($this->currentMenu);
            $this->permissionBits = $this->permissionService->permissionToBits($this->userPermission);
        }

        // 뷰에 권한 정보 공유
        view()->share([
            'currentMenu' => $this->currentMenu,
            'userPermission' => $this->userPermission,
            'permissionBits' => $this->permissionBits,
            'permissionNames' => $this->permissionService->getPermissionNames($this->userPermission)
        ]);
    }

    /**
     * 특정 권한이 있는지 확인합니다.
     */
    protected function hasPermission(int $requiredPermission): bool
    {
        return $this->permissionService->hasPermission($this->userPermission, $requiredPermission);
    }

    /**
     * 권한이 없으면 403 에러를 발생시킵니다.
     */
    protected function requirePermission(int $requiredPermission, string $message = 'Insufficient permissions'): void
    {
        if (!$this->hasPermission($requiredPermission)) {
            abort(403, $message);
        }
    }

    /**
     * Index 권한 확인 (목록 보기)
     */
    protected function canIndex(): bool
    {
        return $this->hasPermission(1);
    }

    /**
     * Read 권한 확인 (상세 보기)
     */
    protected function canRead(): bool
    {
        return $this->hasPermission(2);
    }

    /**
     * Write 권한 확인 (생성/수정)
     */
    protected function canWrite(): bool
    {
        return $this->hasPermission(32);
    }

    /**
     * FullControl 권한 확인 (삭제 등)
     */
    protected function canFullControl(): bool
    {
        return $this->hasPermission(128);
    }

    /**
     * 권한 정보를 JSON으로 반환합니다.
     */
    protected function getPermissionInfo(): array
    {
        return [
            'menu_id' => $this->currentMenu ? $this->currentMenu->id : null,
            'menu_title' => $this->currentMenu ? $this->currentMenu->title : null,
            'permission' => $this->userPermission,
            'permission_bits' => $this->permissionBits,
            'permission_names' => $this->permissionService->getPermissionNames($this->userPermission),
            'can' => [
                'index' => $this->canIndex(),
                'read' => $this->canRead(),
                'write' => $this->canWrite(),
                'full_control' => $this->canFullControl(),
            ]
        ];
    }
}
