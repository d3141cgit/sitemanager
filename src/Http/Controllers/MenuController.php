<?php

namespace SiteManager\Http\Controllers;

use SiteManager\Models\Menu;
use SiteManager\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\Log;

class MenuController extends Controller
{
    protected $menuService;
    
    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }
    
    /**
     * 관리자 권한 체크 헬퍼 메서드
     */
    private function checkAdminPermission()
    {
        $user = Auth::user();
        if (!$user instanceof \SiteManager\Models\Member || !$user->isAdmin()) {
            abort(403, '권한이 없습니다.');
        }
    }
    
    /**
     * 메뉴 목록 (관리자용)
     */
    public function index()
    {
        $this->checkAdminPermission();
        
        $menus = $this->menuService->getAllMenusOrdered();
        $totalCount = $menus->count();
        
        // 메뉴 데이터의 이미지 URL을 FileUploadService를 통해 처리
        $menusWithUrls = $menus->map(function ($menu) {
            $menuArray = $menu->toArray();
            
            // images 필드가 있으면 올바른 URL로 변환
            if ($menu->images) {
                $menuArray['images'] = $menu->getImagesWithUrls();
            }
            
            return $menuArray;
        });
        
        // 존재하지 않는 route를 사용하는 메뉴들 찾기
        $invalidRouteMenus = $this->menuService->findMenusWithInvalidRoutes();

        // AJAX 요청인 경우 JSON만 반환
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'menus' => $menusWithUrls,
                'invalidRouteMenus' => $invalidRouteMenus
            ]);
        }
        
        return view('sitemanager::sitemanager.menus.index', compact('menusWithUrls', 'invalidRouteMenus', 'totalCount'));
    }
    
    /**
     * 메뉴 생성 폼
     */
    public function create()
    {
        $this->checkAdminPermission();
        
        $availableRoutes = $this->menuService->getAvailableRoutes();
        $menuPermissions = ['basic' => [], 'level' => [], 'group' => [], 'admins' => []];
        
        return view('sitemanager::sitemanager.menus.form', compact('availableRoutes', 'menuPermissions'));
    }
    
    /**
     * 메뉴 저장
     */
    public function store(Request $request)
    {
        $this->checkAdminPermission();
        
        $request->validate([
            'title' => 'required|string|max:100',
            'description' => 'nullable|string',
            'search_content' => 'nullable|string',
            'type' => 'required|in:route,url,text',
            'target' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:menus,id',
            'hidden' => 'nullable',
            'images.*.category' => 'nullable|string',
            'images.*.file' => 'nullable|image|max:10240', // 10MB
        ]);
        
        // Route 타입인 경우 route 존재 여부 확인 (경고만 표시, 저장은 계속 진행)
        $routeWarning = null;
        if ($request->type === 'route' && $request->target) {
            if (!$this->menuService->routeExists($request->target)) {
                $routeWarning = 'Warning: The route "' . $request->target . '" does not exist in the application. This menu may not function properly.';
            }
        }
        
        try {
            // 권한 관련 데이터 분리
            $menuData = $request->only(['title', 'description', 'search_content', 'type', 'target', 'parent_id', 'hidden']);
            
            // hidden 필드를 boolean으로 변환 (checkbox 처리)
            $menuData['hidden'] = $request->has('hidden') && $request->input('hidden') == '1';
            
            $permissionData = $request->only(['permission', 'level_permissions', 'group_permissions', 'admin_permissions']);
            $imageData = $request->input('images', []);
            
            // 이미지 파일 데이터 추가
            $imageData = $this->processImageFiles($request, $imageData, 'STORE');
            
            // 메뉴 데이터와 권한 데이터, 이미지 데이터 합치기
            $allData = array_merge($menuData, $permissionData, ['images' => $imageData]);
            
            $this->menuService->createMenu($allData);
            
            $successMessage = '메뉴가 생성되었습니다.';
            if ($routeWarning) {
                $successMessage .= ' ' . $routeWarning;
            }
            
            return redirect()->route('sitemanager.menus.index')
                           ->with($routeWarning ? 'warning' : 'success', $successMessage);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }
    
    /**
     * 메뉴 상세 보기
     */
    public function show(Menu $menu)
    {
        $this->checkAdminPermission();
        
        return view('sitemanager::sitemanager.menus.show', compact('menu'));
    }
    
    /**
     * 메뉴 수정 폼
     */
    public function edit(Menu $menu)
    {
        $this->checkAdminPermission();
        
        $availableRoutes = $this->menuService->getAvailableRoutes();
        $menuPermissions = $this->menuService->getMenuPermissions($menu->id);
        
        return view('sitemanager::sitemanager.menus.form', compact('menu', 'availableRoutes', 'menuPermissions'));
    }
    
    /**
     * 메뉴 업데이트
     */
    public function update(Request $request, Menu $menu)
    {
        $this->checkAdminPermission();
        
        $request->validate([
            'title' => 'required|string|max:100',
            'description' => 'nullable|string',
            'search_content' => 'nullable|string',
            'type' => 'required|in:route,url,text',
            'target' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:menus,id',
            'hidden' => 'nullable',
            'images.*.category' => 'nullable|string',
            'images.*.file' => 'nullable|image|max:10240', // 10MB
            'images.*.existing_url' => 'nullable|string',
        ]);
        
        // 자기 자신을 부모로 설정하는 것 방지
        if ($request->parent_id == $menu->id) {
            return back()->with('error', '자기 자신을 부모 메뉴로 설정할 수 없습니다.')->withInput();
        }
        
        // Route 타입인 경우 route 존재 여부 확인 (경고만 표시, 저장은 계속 진행)
        $routeWarning = null;
        if ($request->type === 'route' && $request->target) {
            if (!$this->menuService->routeExists($request->target)) {
                $routeWarning = 'Warning: The route "' . $request->target . '" does not exist in the application. This menu may not function properly.';
            }
        }
        
        try {
            // 권한 관련 데이터 분리
            $menuData = $request->only(['title', 'description', 'search_content', 'type', 'target', 'parent_id', 'hidden']);
            
            // hidden 필드를 boolean으로 변환 (checkbox 처리)
            $menuData['hidden'] = $request->has('hidden') && $request->input('hidden') == '1';
            
            $permissionData = $request->only(['permission', 'level_permissions', 'group_permissions', 'admin_permissions']);
            $imageData = $request->input('images', null);
            
            // 이미지 파일 데이터 추가
            if (!empty($imageData)) {
                $imageData = $this->processImageFiles($request, $imageData, 'UPDATE');
            }
            
            // 메뉴 데이터와 권한 데이터, 이미지 데이터 합치기
            $allData = array_merge($menuData, $permissionData, ['images' => $imageData]);
            
            $this->menuService->updateMenu($menu->id, $allData);
            
            $successMessage = '메뉴가 수정되었습니다.';
            if ($routeWarning) {
                $successMessage .= ' ' . $routeWarning;
            }
            
            // 수정 시에는 수정 폼에 머무르기
            return redirect()->route('sitemanager.menus.edit', $menu->id)
                           ->with($routeWarning ? 'warning' : 'success', $successMessage);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }
    
    /**
     * 메뉴 삭제
     */
    public function destroy(Menu $menu)
    {
        $this->checkAdminPermission();
        
        // 자식 메뉴가 있는지 확인
        if ($menu->children()->count() > 0) {
            return back()->with('error', '하위 메뉴가 있는 메뉴는 삭제할 수 없습니다.');
        }
        
        try {
            $this->menuService->deleteMenu($menu->id);
            
            return redirect()->route('sitemanager.menus.index')
                           ->with('success', '메뉴가 삭제되었습니다.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
    
    /*
     * ======================================
     * 일반 메뉴 관리 메서드들 (현재 사용 중)
     * ======================================
     */
    
    /**
     * 사용 가능한 라우트 목록 API (AJAX용)
     */
    public function getRoutes()
    {
        $this->checkAdminPermission();
        
        try {
            $routes = $this->menuService->getAvailableRoutes();
            return response()->json(['success' => true, 'routes' => $routes]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 섹션별 부모 메뉴 목록 API (AJAX용)
     */
    public function getSectionParents($section, Request $request)
    {
        $this->checkAdminPermission();
        
        try {
            $excludeId = $request->query('exclude');
            
            $query = Menu::where('section', $section)->orderBy('_lft');
            
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            
            $menus = $query->get()->map(function($menu) {
                return [
                    'id' => $menu->id,
                    'title' => $menu->title,
                    'depth' => $menu->depth
                ];
            });
            
            return response()->json($menus);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 전체 트리 구조 재구축 (AJAX용)
     */
    public function rebuildTree()
    {
        $this->checkAdminPermission();
        
        try {
            Menu::fixAllTrees();
            
            return response()->json([
                'success' => true,
                'message' => 'Tree structure rebuilt successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rebuild tree structure: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 단일 노드 이동 처리 (jsTree move_node 이벤트)
     */
    public function moveNode(Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof \SiteManager\Models\Member || !$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);
        }

        $payload = $request->validate([
            'id' => 'required|integer|exists:menus,id',
            'parent_id' => 'nullable|integer|exists:menus,id',
            'position' => 'nullable|integer|min:0',
            'old_parent' => 'nullable|integer',
            'is_root_level' => 'nullable|boolean',
            'original_section' => 'nullable|integer',
            'target_section' => 'nullable|integer'
        ]);

        try {
            $result = $this->menuService->moveNode(
                (int)$payload['id'], 
                $payload['parent_id'] ?? null, 
                $payload['position'] ?? null,
                [
                    'is_root_level' => $payload['is_root_level'] ?? false,
                    'original_section' => $payload['original_section'] ?? null,
                    'target_section' => $payload['target_section'] ?? null
                ]
            );
            
            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => '메뉴가 성공적으로 이동되었습니다.',
                    'data' => [
                        'id' => $payload['id'],
                        'parent_id' => $payload['parent_id'] ?? null,
                        'position' => $payload['position'] ?? null
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '메뉴 이동에 실패했습니다.'
                ], 500);
            }
        } catch (\Exception $e) {            
            return response()->json([
                'success' => false, 
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 게시판 연결 확인 (AJAX용)
     */
    public function checkBoardConnection(Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof \SiteManager\Models\Member || !$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        try {
            $menuId = $request->input('menu_id');
            
            if ($menuId) {
                // 기존 메뉴 수정 시: 해당 메뉴에 연결된 게시판 확인
                $board = \SiteManager\Models\Board::where('menu_id', $menuId)->first();
            } else {
                // 새 메뉴 생성 시: 연결된 게시판이 없음
                $board = null;
            }
            
            if ($board) {
                return response()->json([
                    'hasBoard' => true,
                    'boardName' => $board->name,
                    'boardSlug' => $board->slug
                ]);
            } else {
                return response()->json([
                    'hasBoard' => false,
                    'message' => 'No board is connected to this menu. Please create a board and connect it to this menu.'
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 이미지 파일 처리 헬퍼 메서드
     */
    private function processImageFiles($request, $imageData, $context = '')
    {
        // 이미지 파일 데이터 추가
        if ($request->hasFile('images')) {
            $files = $request->file('images');
            
            foreach ($files as $index => $fileSet) {
                if (isset($fileSet['file']) && $fileSet['file']->isValid()) {
                    $imageData[$index]['file'] = $fileSet['file'];
                }
            }
        } else {
            // Laravel 중첩 배열 파일 처리를 위한 대안적 접근
            $allFiles = $request->allFiles();
            
            if (isset($allFiles['images'])) {
                foreach ($allFiles['images'] as $index => $fileData) {
                    if (isset($fileData['file']) && $fileData['file']->isValid()) {
                        if (!isset($imageData[$index])) {
                            $imageData[$index] = [];
                        }
                        $imageData[$index]['file'] = $fileData['file'];
                    }
                }
            }
        }
        
        return $imageData;
    }

    /**
     * 검색 컨텐츠 업데이트
     */
    public function updateSearchContent()
    {
        $this->checkAdminPermission();

        try {
            $menus = Menu::whereNotNull('target')
                        ->where('type', 'route')
                        ->get();

            $updated = 0;
            $errors = [];
            $debugInfo = [];

            foreach ($menus as $menu) {
                try {
                    // 라우트에서 뷰 파일 경로 추출
                    $viewPath = $this->getViewPathFromRoute($menu->target);
                    
                    $debugInfo[] = "Menu: {$menu->title} -> Route: {$menu->target} -> View: " . ($viewPath ?: 'NOT FOUND');
                    
                    if ($viewPath) {
                        // 뷰 파일에서 텍스트 추출
                        $content = Menu::extractTextFromView($viewPath);
                        
                        if ($content && mb_check_encoding($content, 'UTF-8')) {
                            $menu->updateSearchContent($content);
                            $updated++;
                            $debugInfo[] = "  -> Content extracted: " . mb_strlen($content, 'UTF-8') . " chars";
                        } else {
                            $debugInfo[] = "  -> Content extraction failed or invalid UTF-8";
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = "Menu '{$menu->title}': " . $e->getMessage();
                }
            }

            $message = "Search content updated successfully. Updated: {$updated} menus";
            
            if (!empty($errors)) {
                $message .= " Errors: " . count($errors);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'updated' => $updated,
                'errors' => $errors,
                'debug' => $debugInfo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Search content update failed: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 라우트명에서 뷰 파일 경로 추출
     */
    private function getViewPathFromRoute(string $routeName): ?string
    {
        try {
            // 직접 뷰 경로 매핑 시도 (라우트 정보 조회 없이)
            return $this->mapRouteNameToView($routeName);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 라우트명을 뷰 파일 경로로 직접 매핑
     */
    private function mapRouteNameToView(string $routeName): ?string
    {
        // 라우트명에서 뷰 경로 추출 시도
        if (str_contains($routeName, '.')) {
            $parts = explode('.', $routeName);
            
            // 기본 뷰 경로들 확인
            $possiblePaths = [
                // 라우트명 그대로 (about.edm-korean-global-campus)
                $routeName,
                // 슬래시로 변환 (about/edm-korean-global-campus)
                str_replace('.', '/', $routeName),
                // pages 접두사 추가
                'pages.' . $routeName,
                'pages/' . str_replace('.', '/', $routeName),
                // 대시를 언더스코어로 변환
                str_replace('-', '_', $routeName),
                str_replace('-', '_', str_replace('.', '/', $routeName)),
                // 대시 제거
                str_replace('-', '', $routeName),
                str_replace('-', '', str_replace('.', '/', $routeName)),
            ];
            
            foreach ($possiblePaths as $path) {
                if (view()->exists($path)) {
                    return $path;
                }
            }
        }

        // 단일 라우트명인 경우
        $singlePaths = [
            $routeName,
            'pages.' . $routeName,
            'pages/' . $routeName,
            str_replace('-', '_', $routeName),
            str_replace('-', '', $routeName),
        ];
        
        foreach ($singlePaths as $path) {
            if (view()->exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
