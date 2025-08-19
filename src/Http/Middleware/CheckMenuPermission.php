<?php

namespace SiteManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\PermissionService;
use App\Models\Menu;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckMenuPermission
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $menuId = null, $requiredPermission = 1): Response
    {
        // 메뉴 ID가 제공되지 않으면 현재 라우트에서 추출
        if (!$menuId) {
            $menuId = $this->extractMenuIdFromRoute($request);
        }

        if (!$menuId) {
            // 메뉴 ID를 찾을 수 없으면 통과
            return $next($request);
        }

        $menu = Menu::find($menuId);
        if (!$menu) {
            abort(404, 'Menu not found');
        }

        $user = Auth::user();
        $userPermission = $this->permissionService->checkMenuPermission($menu, $user);

        // 필요한 권한이 있는지 확인
        if (!$this->permissionService->hasPermission($userPermission, (int)$requiredPermission)) {
            abort(403, 'Insufficient permissions for this menu');
        }

        // 요청에 권한 정보 추가
        $request->merge([
            'menu_permission' => $userPermission,
            'menu_permission_bits' => $this->permissionService->permissionToBits($userPermission),
            'current_menu' => $menu
        ]);

        return $next($request);
    }

    /**
     * 현재 라우트에서 메뉴 ID를 추출합니다.
     */
    private function extractMenuIdFromRoute(Request $request): ?string
    {
        // 라우트 파라미터에서 메뉴 ID 찾기
        $routeParams = $request->route()->parameters();
        
        if (isset($routeParams['menuId'])) {
            return $routeParams['menuId'];
        }

        // 라우트 이름으로 메뉴 찾기
        $routeName = $request->route()->getName();
        if ($routeName) {
            $menu = Menu::where('target', $routeName)->first();
            return $menu ? $menu->id : null;
        }

        return null;
    }
}
