<?php

namespace SiteManager\Services;

use SiteManager\Models\Menu;
use SiteManager\Models\MenuPermission;
use SiteManager\Repositories\MenuRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
// use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MenuService
{
    protected MenuRepositoryInterface $menuRepository;
    
    public function __construct(MenuRepositoryInterface $menuRepository)
    {
        $this->menuRepository = $menuRepository;
    }
    
    /**
     * 전체 메뉴 조회 (_lft 순서)
     */
    public function getAllMenus(): Collection
    {
        return $this->menuRepository->all();
    }
    
    /**
     * 메뉴 트리 구조 조회 (계층 구조)
     */
    public function getMenuTree(): Collection
    {
        return $this->menuRepository->getMenuTree();
    }
    
    /**
     * 모든 메뉴를 _lft 순서로 조회 (플랫 리스트)
     */
    public function getAllMenusOrdered(): Collection
    {
        return $this->menuRepository->all();
    }
    
    /**
     * 타입별 메뉴 조회
     */
    public function getMenusByType(string $type): Collection
    {
        return $this->menuRepository->getByType($type);
    }
    
    /**
     * 네비게이션 메뉴 조회
     */
    public function getNavigationMenus(): Collection
    {
        return $this->getMenusByType('route');
    }
    
    /**
     * 메뉴 상세 조회
     */
    public function getMenu(int $id): ?Menu
    {
        return $this->menuRepository->find($id);
    }
    
    /**
     * 메뉴 생성
     */
    public function createMenu(array $data): Menu
    {
        // 필드명 호환성 처리
        if (isset($data['name']) && !isset($data['title'])) {
            $data['title'] = $data['name'];
            unset($data['name']);
        }
        
        if (isset($data['url']) && !isset($data['target'])) {
            $data['target'] = $data['url'];
            unset($data['url']);
        }
        
        // 권한 관련 데이터 분리
        $permissionData = [
            'permission' => $data['permission'] ?? [],
            'level_permissions' => $data['level_permissions'] ?? [],
            'group_permissions' => $data['group_permissions'] ?? [],
            'admin_permissions' => $data['admin_permissions'] ?? [],
        ];
        
        // 이미지 관련 데이터 분리
        $imageData = $data['images'] ?? [];
        
        // 메뉴 기본 데이터 (권한 및 이미지 관련 필드 제외)
        $menuData = array_diff_key($data, array_merge($permissionData, ['images' => null]));
        
        // 기본 권한 설정은 saveMenuPermissions에서 처리하므로 여기서는 제거
        // saveMenuPermissions에서 permission 값을 올바르게 설정함
        
        // 이미지 처리
        if (!empty($imageData)) {
            $menuData['images'] = Menu::processImageUploads($imageData);
        }
        
        // 섹션 자동 결정
        $menuData['section'] = $this->determineSectionFromParent($menuData['parent_id'] ?? null);
        
        // 루트 메뉴인 경우 (parent_id가 없는 경우) 섹션 중복 검증
        if (empty($menuData['parent_id'])) {
            $this->validateSectionUniqueness($menuData['section']);
        }
        
        $menu = $this->menuRepository->create($menuData);
        
        // 권한 저장 (permission 값이 0이어도 항상 호출)
        $this->saveMenuPermissions($menu->id, $permissionData, true);
            
        // 해당 섹션의 nested set 구조 재구성
        $this->rebuildSection($menu->section);

        // 메뉴 변경 시 권한 캐시 리셋
        app('SiteManager\\Services\\PermissionService')->clearPermissionCache();
        
        // 안전한 방식으로 최신 데이터 반환
        try {
            return $menu->fresh();
        } catch (\Exception $e) {
            // fresh() 실패시 ID로 다시 조회
            $freshMenu = Menu::find($menu->id);
            if ($freshMenu) {
                return $freshMenu;
            }
            // 그래도 실패하면 원본 메뉴 반환
            return $menu;
        }
    }
    
    /**
     * 부모 메뉴로부터 섹션 결정
     */
    private function determineSectionFromParent($parentId): int
    {
        if ($parentId) {
            $parent = $this->menuRepository->find($parentId);
            if ($parent) {
                return $parent->section;
            }
        }
        
        // 부모가 없으면 새로운 섹션 번호 생성
        $maxSection = Menu::max('section');
        return $maxSection ? $maxSection + 1 : 1;
    }
    
    /**
     * 섹션 내 루트 메뉴 중복 검증
     */
    private function validateSectionUniqueness(int $section, ?int $excludeId = null): void
    {
        $query = Menu::where('section', $section)
                    ->whereNull('parent_id');
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        $existingRootMenu = $query->first();
        
        if ($existingRootMenu) {
            throw new \InvalidArgumentException(
                "Section {$section} already has a root menu: '{$existingRootMenu->title}'. Each section can only have one root menu."
            );
        }
    }
    
    /**
     * 특정 섹션의 nested set 구조 재구성
     */
    private function rebuildSection(int $section): void
    {        
        $this->rebuildSectionDirectly($section);
    }
    
    /**
     * 섹션의 nested set 값을 직접 계산하여 업데이트
     */
    private function rebuildSectionDirectly(int $section): void
    {
        // 루트 메뉴들 가져오기
        $rootMenus = Menu::where('section', $section)
            ->whereNull('parent_id')
            ->orderBy('id')
            ->get();
            
        $currentLeft = 1;
        
        foreach ($rootMenus as $root) {
            $currentLeft = $this->rebuildNodeDirectly($root, $currentLeft, 0);
        }
    }
    
    /**
     * 노드와 하위 노드들의 nested set 값을 직접 계산
     */
    private function rebuildNodeDirectly(Menu $node, int $left, int $depth): int
    {
        $right = $left + 1;
        
        // 자식 메뉴들 가져오기 (_lft 순서대로 - 임시로 설정된 순서 반영)
        $children = Menu::where('section', $node->section)
            ->where('parent_id', $node->id)
            ->orderBy('_lft')
            ->get();
            
        foreach ($children as $child) {
            $right = $this->rebuildNodeDirectly($child, $right, $depth + 1);
        }
        
        // 직접 DB 업데이트 (Eloquent 모델 이벤트 우회)
        DB::table('menus')->where('id', $node->id)->update([
            '_lft' => $left,
            '_rgt' => $right,
            'depth' => $depth
        ]);
        
        return $right + 1;
    }
    
    /**
     * 메뉴 수정
     */
    public function updateMenu(int $id, array $data): bool
    {
        $menu = $this->menuRepository->find($id);
        if (!$menu) {
            return false;
        }
        // 권한 관련 데이터 분리
        $permissionData = [
            'permission' => $data['permission'] ?? [],
            'level_permissions' => $data['level_permissions'] ?? [],
            'group_permissions' => $data['group_permissions'] ?? [],
            'admin_permissions' => $data['admin_permissions'] ?? [],
        ];
        
        // 이미지 관련 데이터 분리
        $imageData = $data['images'] ?? null;
        
        // 메뉴 기본 데이터 (권한 및 이미지 관련 필드 제외)
        $menuData = array_diff_key($data, array_merge($permissionData, ['images' => null]));

        // 이미지 처리
        if (!empty($imageData)) {
            $menuData['images'] = Menu::processImageUploads($imageData, $menu->images);
        } else {
            $menuData['images'] = null; // 이미지가 모두 제거된 경우
        }

        $originalSection = $menu->section;
        $originalParentId = $menu->parent_id;
        $newParentId = $menuData['parent_id'] ?? null;

        // 섹션 자동 결정 (parent가 변경된 경우)
        if ($originalParentId != $newParentId) {
            $menuData['section'] = $this->determineSectionFromParent($newParentId);
            
            // 루트 메뉴로 변경되는 경우 (새로운 parent_id가 null) 섹션 중복 검증
            if (empty($newParentId)) {
                $this->validateSectionUniqueness($menuData['section'], $id);
            }
        }

        // 일반 필드 업데이트
        $result = $this->menuRepository->update($id, $menuData);

        if ($result) {
            // 권한 저장 (permission 값이 0이어도 항상 호출)
            $this->saveMenuPermissions($id, $permissionData, false);
            
            // 원래 섹션과 새 섹션 모두 rebuild
            $this->rebuildSection($originalSection);
            
            if (isset($menuData['section']) && $menuData['section'] != $originalSection) {
                $this->rebuildSection($menuData['section']);
            }
        }

        if ($result) {
            // 메뉴 변경 시 권한 캐시 리셋
            app('SiteManager\\Services\\PermissionService')->clearPermissionCache();
        }
        return $result;
    }
    
    /**
     * 메뉴 삭제
     */
    public function deleteMenu(int $id): bool
    {
        $menu = $this->menuRepository->find($id);
        if (!$menu) {
            return false;
        }
        
        $section = $menu->section;
        $result = $this->menuRepository->delete($id);
        
        if ($result) {
            // 해당 섹션의 nested set 구조 재구성
            $this->rebuildSection($section);
        }
        
        if ($result) {
            // 메뉴 삭제 시 권한 캐시 리셋
            app('SiteManager\\Services\\PermissionService')->clearPermissionCache();
        }
        return $result;
    }
    
    /**
     * 라우트 목록 조회 (메뉴에 적합한 라우트만)
     */
    public function getAvailableRoutes(): array
    {
        $routes = Route::getRoutes();
        $routeData = [];
        
        foreach ($routes as $route) {
            $name = $route->getName();
            $methods = $route->methods();
            
            // 기본 필터링: 이름이 있고 GET 메서드를 지원하는 라우트만
            if (!$name || !in_array('GET', $methods)) {
                continue;
            }
            
            // 시스템/디버그 라우트 제외
            if (str_starts_with($name, '_ignition') || 
                str_starts_with($name, 'debugbar') ||
                str_starts_with($name, 'telescope') ||
                str_starts_with($name, 'horizon')) {
                continue;
            }
            
            // Admin 라우트 제외 (관리자 전용)
            if (str_starts_with($name, 'admin.')) {
                continue;
            }
            
            // SiteManager 패키지 라우트 제외 (내부 시스템 라우트)
            if (str_starts_with($name, 'sitemanager.')) {
                continue;
            }
            
            // API 라우트 제외
            if (str_starts_with($name, 'api.')) {
                continue;
            }
            
            // 스토리지 라우트 제외
            if (str_starts_with($name, 'storage.')) {
                continue;
            }
            
            // 인증 관련 라우트 제외
            if (in_array($name, ['login', 'logout', 'register', 'password.request', 'password.email', 'password.reset', 'password.update', 'verification.notice', 'verification.verify', 'verification.send'])) {
                continue;
            }
            
            // CRUD 액션 중 메뉴에 부적합한 것들 제외
            if (str_contains($name, '.store') ||    // POST 액션
                str_contains($name, '.update') ||   // PUT/PATCH 액션
                str_contains($name, '.destroy') ||  // DELETE 액션
                str_contains($name, '.create') ||   // 생성 폼
                str_contains($name, '.edit') ||     // 편집 폼
                str_contains($name, '.confirm') ||  // 확인 페이지
                str_contains($name, '.approve') ||  // 승인 액션
                str_contains($name, '.reject') ||   // 거부 액션
                str_contains($name, '.download') || // 다운로드 액션
                str_contains($name, '.upload') ||   // 업로드 액션
                str_contains($name, '.delete') ||   // 삭제 관련
                str_contains($name, '.restore') ||  // 복원 관련
                str_contains($name, '.toggle') ||   // 토글 관련
                str_contains($name, '.search') ||   // 검색 API (일반적으로 AJAX)
                str_contains($name, '.preview') ||  // 미리보기
                str_contains($name, '.export') ||   // 내보내기
                str_contains($name, '.import') ||   // 가져오기
                str_contains($name, '.comments') || // 댓글 관련 (보통 하위 리소스)
                str_contains($name, '.files') ||    // 파일 관련
                str_contains($name, '.attachments') || // 첨부파일 관련
                str_contains($name, '.get-') ||     // AJAX API 엔드포인트 (get-images, get-data 등)
                str_contains($name, '.fetch-') ||   // AJAX fetch 엔드포인트
                str_contains($name, '.load-') ||    // AJAX load 엔드포인트
                str_contains($name, '.ajax') ||     // 명시적 AJAX 라우트
                str_contains($name, '.api') ||      // API 엔드포인트
                str_ends_with($name, '-api') ||     // API 접미사
                str_ends_with($name, '.json') ||    // JSON 응답
                str_ends_with($name, '.xml')) {     // XML 응답
                continue;
            }
            
            // 너무 깊은 중첩 라우트 제외 (3단계 이상은 메뉴에 부적합)
            $routeDepth = substr_count($name, '.');
            if ($routeDepth > 2) { // a.b.c까지는 허용, a.b.c.d부터는 제외
                continue;
            }
            
            // 상세 페이지는 ID나 slug가 필요한 경우 제외 (메뉴에 부적합)
            if (str_contains($name, '.show') && 
                (str_contains($route->uri(), '{id}') || 
                 str_contains($route->uri(), '{slug}') || 
                 str_contains($route->uri(), '{'))) {
                continue;
            }
            
            // 게시판은 board.index만 허용하고 나머지는 제외
            if (str_starts_with($name, 'board.') && $name !== 'board.index') {
                continue;
            }
            
            // 메뉴에 적합한 라우트 패턴 우선순위
            $priority = $this->getRouteMenuPriority($name);
            
            $routeData[] = [
                'name' => $name,
                'uri' => $route->uri(),
                'methods' => $methods,
                'priority' => $priority,
                'description' => $this->generateRouteDescription($name, $route->uri())
            ];
        }
        
        // 우선순위와 이름으로 정렬 (우선순위 높은 것 먼저, 같으면 이름순)
        usort($routeData, function($a, $b) {
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] - $a['priority']; // 높은 우선순위 먼저
            }
            return strcmp($a['name'], $b['name']);
        });
        
        return $routeData;
    }
    
    /**
     * 라우트의 메뉴 적합성 우선순위 계산
     */
    private function getRouteMenuPriority(string $routeName): int
    {
        // 높은 우선순위 (10점): 메인 인덱스 페이지들
        if (preg_match('/^[^.]+\.index$/', $routeName) || // board.index, news.index 등
            $routeName === 'home' || 
            $routeName === 'welcome' ||
            $routeName === 'sermons.sunday' ||  // 주일설교 메뉴
            $routeName === 'sermons.special' || // 주일 외 설교 메뉴
            preg_match('/^[^.]+$/', $routeName)) { // 단일 레벨 라우트 (home, about 등)
            return 10;
        }
        
        // 중간 우선순위 (5점): 카테고리나 섹션 페이지
        if (str_contains($routeName, '.category') ||
            str_contains($routeName, '.list') ||
            str_contains($routeName, '.archive') ||
            preg_match('/^[^.]+\.[^.]+\.index$/', $routeName)) { // board.category.index 등
            return 5;
        }
        
        // 낮은 우선순위 (1점): 기타 GET 라우트
        return 1;
    }
    
    /**
     * 라우트 설명 생성
     */
    private function generateRouteDescription(string $routeName, string $uri): string
    {
        // 특별한 라우트들에 대한 사용자 친화적 설명
        $specialDescriptions = [
            'home' => 'Home Page',
            'welcome' => 'Welcome Page',
            'about' => 'About Page',
            'contact' => 'Contact Page',
            'vision' => 'Vision & Mission',
            'senior-pastor' => 'Senior Pastor',
            'worship-service' => 'Worship Service',
            'leaders' => 'Church Leaders',
            'news.index' => 'News List',
            'board.index' => 'Board List',
            'gallery.index' => 'Gallery',
            'events.index' => 'Events',
            'services.index' => 'Services',
            'products.index' => 'Products',
            'sermons.sunday' => 'Sunday Sermons (주일설교)',
            'sermons.special' => 'Special Sermons (주일 외 설교)',
        ];
        
        if (isset($specialDescriptions[$routeName])) {
            return $specialDescriptions[$routeName];
        }
        
        // 패턴 기반 설명 생성
        if (preg_match('/^([^.]+)\.index$/', $routeName, $matches)) {
            return ucfirst($matches[1]) . ' List';
        }
        
        if (preg_match('/^([^.]+)\.([^.]+)\.index$/', $routeName, $matches)) {
            return ucfirst($matches[1]) . ' ' . ucfirst($matches[2]);
        }
        
        // 기본 설명: 점과 언더스코어를 공백으로 변환하고 단어 첫글자 대문자화
        return ucwords(str_replace(['.', '_'], [' ', ' '], $routeName));
    }

    /**
     * 특정 route가 현재 존재하는지 확인
     */
    public function routeExists(string $routeName): bool
    {
        try {
            return Route::has($routeName);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 존재하지 않는 route를 사용하는 메뉴들을 찾기
     */
    public function findMenusWithInvalidRoutes(): array
    {
        $invalidMenus = [];
        $routeMenus = Menu::where('type', 'route')->whereNotNull('target')->get();
        
        foreach ($routeMenus as $menu) {
            if (!$this->routeExists($menu->target)) {
                $invalidMenus[] = [
                    'id' => $menu->id,
                    'title' => $menu->title,
                    'target' => $menu->target,
                    'section' => $menu->section
                ];
            }
        }
        
        return $invalidMenus;
    }

    /**
     * Move a node under a new parent and optionally position it.
     * If parent_id is null, make node a root in its section (or new section if needed).
     */
    public function moveNode(int $nodeId, ?int $parentId, int $position, array $extraData): bool
    {
        return DB::transaction(function () use ($nodeId, $parentId, $position, $extraData) {
            $node = Menu::find($nodeId);
            if (!$node) {
                throw new \Exception("메뉴를 찾을 수 없습니다.");
            }

            // 이동 시나리오 분석
            $scenario = $this->analyzeMoveScenario($node, $parentId, $extraData);

            // 사전 검증
            $this->validateMove($node, $parentId, $scenario);

            // 시나리오별 이동 처리
            switch ($scenario['type']) {
                case 'SECTION_ORDER_CHANGE':
                    $this->handleSectionOrderChange($node, $scenario, $position);
                    break;
                    
                case 'INTRA_SECTION_MOVE':
                    $this->handleIntraSectionMove($node, $parentId, $scenario, $position);
                    break;
                    
                case 'CROSS_SECTION_PARENTING':
                    $this->handleCrossSectionParenting($node, $parentId, $scenario, $position);
                    break;
                    
                case 'NEW_SECTION_CREATION':
                    $this->handleNewSectionCreation($node, $scenario, $position);
                    break;
                    
                case 'SECTION_INTEGRATION':
                    $this->handleSectionIntegration($node, $parentId, $scenario, $position);
                    break;
                    
                default:
                    throw new \Exception("지원하지 않는 이동 시나리오입니다: " . $scenario['type']);
            }

            return true;
        });
    }

    /**
     * 노드가 다른 노드의 자손인지 확인
     */
    private function isDescendant(int $possibleDescendantId, int $ancestorId): bool
    {
        $possibleDescendant = Menu::find($possibleDescendantId);
        if (!$possibleDescendant) return false;

        $ancestors = $possibleDescendant->ancestors()->pluck('id')->toArray();
        return in_array($ancestorId, $ancestors);
    }

    /**
     * 노드와 모든 자손의 섹션 업데이트
     */
    private function updateNodeSection(Menu $node, int $newSection): void
    {
        $node->section = $newSection;
        $node->save();

        // 자손들도 업데이트
        $descendants = $node->descendants()->get();
        foreach ($descendants as $descendant) {
            $descendant->section = $newSection;
            $descendant->save();
        }
    }

    /**
     * 형제 노드들 사이에서 노드 위치 조정
     */
    private function repositionNode(Menu $node, int $position): void
    {
        // 부모가 있는 경우 부모의 자식들을 가져오고, 없으면 같은 섹션의 루트 노드들을 가져옴
        if ($node->parent_id) {
            $siblings = Menu::where('parent_id', $node->parent_id)
                ->where('id', '!=', $node->id)
                ->orderBy('_lft')
                ->get();
        } else {
            $siblings = Menu::whereNull('parent_id')
                ->where('section', $node->section)
                ->where('id', '!=', $node->id)
                ->orderBy('_lft')
                ->get();
        }
        
        if (isset($siblings[$position])) {
            $targetSibling = $siblings[$position];
            $node->beforeNode($targetSibling)->save();
        }
    }
    
    /**
     * 부모 내에서 노드 위치 조정 (크로스 섹션 이동 후 사용)
     */
    private function repositionNodeInParent(Menu $node, int $position): void
    {        
        // 부모의 자식들을 가져옴 (현재 노드 제외)
        $siblings = Menu::where('parent_id', $node->parent_id)
            ->where('id', '!=', $node->id)
            ->orderBy('_lft')
            ->get();
        
        // position은 1-based이므로 0-based로 변환
        $targetIndex = $position - 1;
        
        if ($targetIndex <= 0) {
            // 첫 번째 위치로 이동
            if ($siblings->count() > 0) {
                $firstSibling = $siblings->first();
                $node->beforeNode($firstSibling)->save();
            }
        } elseif ($targetIndex >= $siblings->count()) {
            // 마지막 위치로 이동
            if ($siblings->count() > 0) {
                $lastSibling = $siblings->last();
                $node->afterNode($lastSibling)->save();
            }
        } else {
            // 지정된 위치로 이동
            $targetSibling = $siblings->get($targetIndex);
            $node->beforeNode($targetSibling)->save();
        }
    }
    
    /**
     * 메뉴 권한 저장
     */
    private function saveMenuPermissions(int $menuId, array $data, bool $isCreate = false): void
    {
        // 기본 권한을 menus.permission 필드에 저장 (bitmask)
        if (isset($data['permission']) && is_array($data['permission']) && !empty($data['permission'])) {
            $basicPermission = 0;
            foreach ($data['permission'] as $perm) {
                $basicPermission |= (int)$perm;
            }
            Menu::where('id', $menuId)->update(['permission' => $basicPermission]);
        } else {
            // 권한이 설정되지 않았거나 모든 체크박스가 해제된 경우
            if ($isCreate) {
                // 새로 생성할 때는 기본값 3 (index + read)
                Menu::where('id', $menuId)->update(['permission' => 3]);
            } else {
                // 업데이트일 때는 0으로 설정 (모든 권한 해제)
                Menu::where('id', $menuId)->update(['permission' => 0]);
            }
        }
        
        // 기존 menu_permissions 삭제
        MenuPermission::where('menu_id', $menuId)->delete();
        
        // 레벨별 권한 저장
        if (isset($data['level_permissions']) && is_array($data['level_permissions'])) {
            foreach ($data['level_permissions'] as $levelData) {
                if (isset($levelData['level']) && isset($levelData['permissions'])) {
                    $permission = 0;
                    foreach ($levelData['permissions'] as $perm) {
                        $permission |= (int)$perm;
                    }
                    
                    MenuPermission::create([
                        'menu_id' => $menuId,
                        'type' => 'level',
                        'subject_id' => $levelData['level'],
                        'permission' => $permission,
                    ]);
                }
            }
        }
        
        // 그룹별 권한 저장
        if (isset($data['group_permissions']) && is_array($data['group_permissions'])) {
            foreach ($data['group_permissions'] as $groupData) {
                if (isset($groupData['group_id']) && isset($groupData['permissions'])) {
                    $permission = 0;
                    foreach ($groupData['permissions'] as $perm) {
                        $permission |= (int)$perm;
                    }
                    
                    MenuPermission::create([
                        'menu_id' => $menuId,
                        'type' => 'group',
                        'subject_id' => $groupData['group_id'],
                        'permission' => $permission,
                    ]);
                }
            }
        }
        
        // 관리자별 권한 저장
        if (isset($data['admin_permissions']) && is_array($data['admin_permissions'])) {
            foreach ($data['admin_permissions'] as $adminData) {
                if (isset($adminData['member_id'])) {
                    // 관리자는 항상 모든 권한(255)을 가짐
                    MenuPermission::create([
                        'menu_id' => $menuId,
                        'type' => 'admin',
                        'subject_id' => $adminData['member_id'],
                        'permission' => config('permissions.values.all', 255),
                    ]);
                }
            }
        }
    }
    
    /**
     * 권한 문자열을 숫자 값으로 변환
     */
    private function getPermissionValue(string $permission): int
    {
        $permissions = [
            'guest' => 1,
            'member' => 2,
            'admin' => 3,
        ];
        
        return $permissions[$permission] ?? 0;
    }
    
    /**
     * 메뉴 권한 조회
     */
    public function getMenuPermissions(int $menuId): array
    {
        $menu = Menu::find($menuId);
        $permissions = MenuPermission::where('menu_id', $menuId)->get();
        
        $result = [
            'basic_permission' => $menu ? $menu->permission : 0,
            'levels' => [],
            'groups' => [],
            'admins' => [],
        ];
        
        foreach ($permissions as $permission) {
            if ($permission->type === 'level') {
                $levels = config('member.levels');
                $result['levels'][] = [
                    'level' => $permission->subject_id,
                    'level_name' => $levels[$permission->subject_id] ?? 'Unknown',
                    'permission' => $permission->permission,
                ];
            } elseif ($permission->type === 'group') {
                $group = \SiteManager\Models\Group::find($permission->subject_id);
                $result['groups'][] = [
                    'group_id' => $permission->subject_id,
                    'name' => $group ? $group->name : 'Unknown Group',
                    'permission' => $permission->permission,
                ];
            } elseif ($permission->type === 'admin') {
                $member = \SiteManager\Models\Member::find($permission->subject_id);
                $result['admins'][] = [
                    'member_id' => $permission->subject_id,
                    'name' => $member ? $member->name : 'Unknown Member',
                    'username' => $member ? $member->username : 'Unknown',
                    'permission' => $permission->permission,
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * 특정 사용자의 메뉴에 대한 최종 권한을 계산합니다 (최대값 반환).
     */
    public function getUserMenuPermission(int $menuId, $user = null): int
    {
        if (!$user) {
            $user = auth('web')->user();
        }
        
        if (!$user) {
            // 게스트 사용자: 메뉴의 기본 권한만 반환
            $menu = Menu::find($menuId);
            return $menu ? ($menu->permission ?? 0) : 0;
        }
        
        // 관리자는 모든 권한
        if ($user->isAdmin()) {
            return config('permissions.values.all', 255);
        }
        
        $permissions = [];
        
        // 1. 메뉴 기본 권한
        $menu = Menu::find($menuId);
        if ($menu) {
            $permissions[] = $menu->permission ?? 0;
        }
        
        // 2. 레벨별 권한 확인 (사용자 레벨 이하의 모든 권한 중 최대값)
        $levelPermission = MenuPermission::where('menu_id', $menuId)
            ->where('type', 'level')
            ->where('subject_id', '<=', $user->level)
            ->max('permission');
        
        if ($levelPermission) {
            $permissions[] = $levelPermission;
        }
        
        // 3. 관리자 권한 확인 (개별 사용자 권한)
        $adminPermission = MenuPermission::where('menu_id', $menuId)
            ->where('type', 'admin')
            ->where('subject_id', $user->id)
            ->value('permission');
        
        if ($adminPermission) {
            $permissions[] = $adminPermission;
        }
        
        // 4. 그룹 권한 확인 (사용자가 속한 그룹들의 권한 중 최대값)
        $groupPermission = MenuPermission::where('menu_id', $menuId)
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
        
        // 권한이 없으면 0 반환
        if (empty($permissions)) {
            return 0;
        }
        
        // 가장 높은 권한 반환 (최대값)
        return max($permissions);
    }
    
    /**
     * 권한 숫자 값을 문자열로 변환
     */
    private function getPermissionName(int $value): string
    {
        $permissions = [
            1 => 'guest',
            2 => 'member',
            3 => 'admin',
        ];
        
        return $permissions[$value] ?? 'unknown';
    }

    /**
     * 섹션 번호를 재정렬합니다 (빈 섹션 제거 및 번호 연속화)
     */
    private function reorderSections(): void
    {
        // 현재 사용 중인 섹션들을 오름차순으로 가져오기
        $usedSections = Menu::select('section')
            ->distinct()
            ->orderBy('section')
            ->pluck('section')
            ->toArray();

        // 연속된 번호로 재정렬이 필요한지 확인
        $needsReordering = false;
        for ($i = 0; $i < count($usedSections); $i++) {
            if ($usedSections[$i] !== $i + 1) {
                $needsReordering = true;
                break;
            }
        }

        if (!$needsReordering) {
            return;
        }

        // 섹션 재정렬 실행
        foreach ($usedSections as $index => $currentSection) {
            $newSection = $index + 1;
            
            if ($currentSection !== $newSection) {
                Menu::where('section', $currentSection)
                    ->update(['section' => $newSection]);
            }
        }
    }

    /**
     * 섹션 순서를 변경합니다 (섹션 간 드래그 앤 드롭)
     */
    private function moveSectionOrder(int $fromSection, int $toSection, ?int $position = null): void
    {
        // 현재 사용 중인 섹션들을 오름차순으로 가져오기
        $usedSections = Menu::select('section')
            ->distinct()
            ->orderBy('section')
            ->pluck('section')
            ->toArray();

        // fromSection의 인덱스 찾기
        $fromIndex = array_search($fromSection, $usedSections);
        if ($fromIndex === false) {
            return;
        }

        // fromSection을 배열에서 제거하고 새 위치에 삽입
        $movingSection = array_splice($usedSections, $fromIndex, 1)[0];
        
        // position을 0-based 인덱스로 변환 (position 1 = index 0, position 2 = index 1)
        $insertIndex = ($position ?? count($usedSections) + 1) - 1;
        $insertIndex = max(0, min($insertIndex, count($usedSections)));
        
        array_splice($usedSections, $insertIndex, 0, $movingSection);

        // 한 번에 모든 섹션을 1부터 연속으로 재할당
        $sectionMapping = [];
        foreach ($usedSections as $newOrder => $originalSection) {
            $newSectionNumber = $newOrder + 1;
            $sectionMapping[$originalSection] = $newSectionNumber;
        }

        // CASE문을 사용한 단일 UPDATE로 모든 섹션을 한 번에 변경 (충돌 방지)
        if (!empty($sectionMapping)) {
            $caseClauses = [];
            $oldSections = [];
            
            foreach ($sectionMapping as $originalSection => $newSection) {
                if ($originalSection !== $newSection) {
                    $caseClauses[] = "WHEN {$originalSection} THEN {$newSection}";
                    $oldSections[] = $originalSection;
                }
            }
            
            if (!empty($caseClauses)) {
                $caseClause = implode(' ', $caseClauses);
                $oldSectionsList = implode(',', $oldSections);
                
                $sql = "UPDATE menus SET section = CASE section {$caseClause} END WHERE section IN ({$oldSectionsList})";
                
                DB::statement($sql);
            }
        }

        // 영향받은 모든 섹션 재구축
        foreach ($sectionMapping as $newSection) {
            $this->rebuildSection($newSection);
        }
    }

    /**
     * 이동 시나리오 분석
     */
    private function analyzeMoveScenario(Menu $node, ?int $parentId, array $extraData): array
    {
        $sourceSection = $node->section;
        $sourceDepth = $node->depth;
        $isSourceRoot = $node->parent_id === null;
        
        $targetParent = $parentId ? Menu::find($parentId) : null;
        $targetSection = $targetParent ? $targetParent->section : ($extraData['target_section'] ?? null);
        $isRootLevelMove = $extraData['is_root_level'] ?? false;
        
        // 부모 노드가 있는 경우 (자식 노드로 이동)
        if ($targetParent) {
            if ($sourceSection === $targetParent->section) {
                return [
                    'type' => 'INTRA_SECTION_MOVE',
                    'source_section' => $sourceSection,
                    'target_section' => $targetParent->section,
                    'target_parent' => $targetParent,
                    'description' => '같은 섹션 내 부모 변경'
                ];
            } else {
                if ($isSourceRoot) {
                    return [
                        'type' => 'SECTION_INTEGRATION',
                        'source_section' => $sourceSection,
                        'target_section' => $targetParent->section,
                        'target_parent' => $targetParent,
                        'description' => '루트 노드를 다른 섹션에 통합'
                    ];
                } else {
                    return [
                        'type' => 'CROSS_SECTION_PARENTING',
                        'source_section' => $sourceSection,
                        'target_section' => $targetParent->section,
                        'target_parent' => $targetParent,
                        'description' => '중간/리프 노드의 크로스 섹션 이동'
                    ];
                }
            }
        }
        
        // 루트 레벨 이동 (섹션 간 이동)
        if ($isRootLevelMove || $parentId === null) {
            if ($isSourceRoot && $targetSection && $sourceSection !== $targetSection) {
                return [
                    'type' => 'SECTION_ORDER_CHANGE',
                    'source_section' => $sourceSection,
                    'target_section' => $targetSection,
                    'description' => '루트 노드 섹션 간 순서 변경'
                ];
            } elseif ($isSourceRoot && (!$targetSection || $sourceSection === $targetSection)) {
                // 같은 섹션 내에서 루트 위치 변경 또는 target_section이 명시되지 않은 경우
                return [
                    'type' => 'INTRA_SECTION_MOVE',
                    'source_section' => $sourceSection,
                    'target_section' => $sourceSection,
                    'target_parent' => null,
                    'description' => '같은 섹션 내 루트 위치 조정'
                ];
            } elseif (!$isSourceRoot) {
                return [
                    'type' => 'NEW_SECTION_CREATION',
                    'source_section' => $sourceSection,
                    'target_section' => null, // 실제 번호는 핸들러에서 계산
                    'description' => '중간/리프 노드를 새 섹션 루트로 이동'
                ];
            }
        }
        
        throw new \Exception("이동 시나리오를 분석할 수 없습니다.");
    }

    /**
     * 이동 검증
     */
    private function validateMove(Menu $node, ?int $parentId, array $scenario): void
    {
        // 순환 참조 검사
        if ($parentId) {
            $targetParent = Menu::find($parentId);
            if ($this->wouldCreateCircularReference($node, $targetParent)) {
                throw new \Exception("순환 참조가 발생할 수 있는 이동입니다.");
            }
        }
        
        // 섹션 무결성 검사
        if ($scenario['type'] === 'NEW_SECTION_CREATION') {
            // 새 섹션 생성은 항상 허용
        } elseif ($scenario['type'] === 'INTRA_SECTION_MOVE') {
            // 같은 섹션 내 이동에서 루트 중복 방지
            if ($parentId === null && !$node->isRoot()) {
                $existingRoots = Menu::where('section', $scenario['source_section'])
                    ->whereNull('parent_id')
                    ->where('id', '!=', $node->id)
                    ->count();
                if ($existingRoots > 0) {
                    throw new \Exception("섹션에는 하나의 루트 메뉴만 존재할 수 있습니다.");
                }
            }
        }
    }

    /**
     * 순환 참조 검사
     */
    private function wouldCreateCircularReference(Menu $node, Menu $targetParent): bool
    {
        // 자기 자신에게 이동
        if ($node->id === $targetParent->id) {
            return true;
        }
        
        // 자신의 후손에게 이동
        $descendants = $node->descendants()->pluck('id')->toArray();
        return in_array($targetParent->id, $descendants);
    }

    /**
     * 다음 섹션 번호 계산
     */
    private function getNextSectionNumber(): int
    {
        return Menu::max('section') + 1;
    }

    /**
     * 핸들러: 섹션 순서 변경
     */
    private function handleSectionOrderChange(Menu $node, array $scenario, int $position): void
    {
        $this->moveSectionOrder($scenario['source_section'], $scenario['target_section'], $position);
    }

    /**
     * 핸들러: 같은 섹션 내 이동
     */
    private function handleIntraSectionMove(Menu $node, ?int $parentId, array $scenario, ?int $position = null): void
    {
        // 직접 DB 업데이트로 변경
        DB::table('menus')->where('id', $node->id)->update([
            'parent_id' => $parentId
        ]);
        
        // position이 지정된 경우, 형제 노드들의 순서를 조정
        if ($position !== null && $parentId !== null) {
            $this->adjustSiblingOrder($node, $parentId, $position, $scenario['source_section']);
        }
        
        $this->rebuildSection($scenario['source_section']);
    }

    /**
     * 형제 노드들의 순서를 조정하여 원하는 position에 배치
     */
    private function adjustSiblingOrder(Menu $node, int $parentId, int $position, int $section): void
    {
        // 같은 부모를 가진 형제 노드들을 _lft 순으로 가져오기
        $siblings = DB::table('menus')
            ->where('section', $section)
            ->where('parent_id', $parentId)
            ->where('id', '!=', $node->id) // 이동하는 노드 제외
            ->orderBy('_lft')
            ->get(['id', 'title', '_lft'])
            ->toArray();

        // 새로운 순서 배열 생성
        $newOrder = [];
        
        // position은 1-based, 배열은 0-based
        $targetIndex = max(0, $position - 1);
        
        // position이 형제 노드 개수보다 큰 경우, 마지막에 삽입
        if ($targetIndex >= count($siblings)) {
            // 모든 형제 노드 추가 후 마지막에 이동하는 노드 추가
            foreach ($siblings as $sibling) {
                $newOrder[] = $sibling->id;
            }
            $newOrder[] = $node->id;
        } else {
            // 지정된 position에 삽입
            for ($i = 0; $i < count($siblings); $i++) {
                if ($i == $targetIndex) {
                    $newOrder[] = $node->id; // 이동하는 노드 먼저 삽입
                }
                $newOrder[] = $siblings[$i]->id; // 기존 노드 삽입
            }
            
            // 맨 앞에 삽입되지 않았고, 중간에도 삽입되지 않았다면 맨 앞에 삽입
            if ($targetIndex == 0 && !in_array($node->id, $newOrder)) {
                array_unshift($newOrder, $node->id);
            }
        }

        // _lft 값을 새로운 순서에 맞게 임시로 조정
        $startLft = 1000; // 임시 시작값 (실제 트리 재구성에서 올바른 값으로 설정됨)
        foreach ($newOrder as $index => $nodeId) {
            $tempLft = $startLft + ($index * 10); // 10씩 간격으로 임시 설정
            DB::table('menus')->where('id', $nodeId)->update(['_lft' => $tempLft]);
        }
    }

    /**
     * 핸들러: 크로스 섹션 부모-자식 이동
     */
    private function handleCrossSectionParenting(Menu $node, ?int $parentId, array $scenario, int $position): void
    {   
        $targetParent = $scenario['target_parent'];
        
        // sf4l 방식: 직접 DB 업데이트
        DB::table('menus')->where('id', $node->id)->update([
            'section' => $scenario['target_section'],
            'parent_id' => $targetParent->id
        ]);
        
        // 모든 자손 노드들의 섹션 업데이트
        $descendants = $node->descendants()->get();
        foreach ($descendants as $descendant) {
            DB::table('menus')->where('id', $descendant->id)->update([
                'section' => $scenario['target_section']
            ]);
        }
        
        // 영향받은 섹션들 재구축
        $this->rebuildSection($scenario['source_section']);
        $this->rebuildSection($scenario['target_section']);
        
        // 재구축 후 지정된 위치로 이동
        $node->refresh(); // 최신 데이터로 리프레시
        $this->repositionNodeInParent($node, $position);
    }

    /**
     * 핸들러: 새 섹션 생성
     */
    private function handleNewSectionCreation(Menu $node, array $scenario, int $position): void
    {        
        // 새 섹션 번호는 일단 끝에 추가
        $newSection = $this->getNextSectionNumber();
        
        // 노드를 새 섹션의 루트로 만들기
        DB::table('menus')->where('id', $node->id)->update([
            'section' => $newSection,
            'parent_id' => null
        ]);
        
        // 모든 자손 노드들의 섹션 업데이트
        $descendants = $node->descendants()->get();
        foreach ($descendants as $descendant) {
            DB::table('menus')->where('id', $descendant->id)->update([
                'section' => $newSection
            ]);
        }
        
        // 영향받은 섹션들 재구축
        $this->rebuildSection($scenario['source_section']);
        $this->rebuildSection($newSection);
        
        // 위치가 지정된 경우, 해당 위치로 섹션 이동
        if ($position > 0 && $position < $newSection) {
            $this->moveNewSectionToPosition($newSection, $position);
        }
    }

    /**
     * 핸들러: 섹션 통합 (루트 노드를 다른 섹션에 병합)
     */
    private function handleSectionIntegration(Menu $node, ?int $parentId, array $scenario, int $position): void
    {   
        $targetParent = $scenario['target_parent'];
        $originalSection = $scenario['source_section'];
        $targetSection = $scenario['target_section'];
        
        // 루트 노드를 타겟 섹션의 하위로 이동
        DB::table('menus')->where('id', $node->id)->update([
            'section' => $targetSection,
            'parent_id' => $targetParent->id
        ]);
        
        // 모든 자손 노드들의 섹션 업데이트
        $descendants = $node->descendants()->get();
        foreach ($descendants as $descendant) {
            DB::table('menus')->where('id', $descendant->id)->update([
                'section' => $targetSection
            ]);
        }
        
        // 원본 섹션이 비어있다면 섹션 번호 정리
        $remainingInOriginal = Menu::where('section', $originalSection)->count();
        if ($remainingInOriginal === 0) {
            $this->cleanupEmptySection($originalSection);
        }
        
        // 타겟 섹션 재구축
        $this->rebuildSection($targetSection);
        if ($remainingInOriginal > 0) {
            $this->rebuildSection($originalSection);
        }
        
        // 재구축 후 지정된 위치로 이동
        $node->refresh(); // 최신 데이터로 리프레시
        $this->repositionNodeInParent($node, $position);
    }

    /**
     * 새 섹션을 특정 위치에 삽입 (현재 비활성화 - 안전상 이유)
     */
    private function insertSectionAtPosition(int $newSection, int $position): void
    {        
        // 현재 이 기능은 비활성화됨
        // 섹션 번호 재정렬이 복잡하고 데이터 무결성 위험이 있음
        // 대신 새 섹션은 항상 마지막 번호로 추가됨
        
        return;
    }

    /**
     * 빈 섹션 정리
     */
    private function cleanupEmptySection(int $emptySection): void
    {
        // 빈 섹션 이후의 모든 섹션들을 앞으로 당기기
        $sectionsToUpdate = Menu::select('section')
            ->distinct()
            ->where('section', '>', $emptySection)
            ->orderBy('section')
            ->pluck('section')
            ->toArray();
        
        foreach ($sectionsToUpdate as $section) {
            DB::table('menus')
                ->where('section', $section)
                ->update(['section' => $section - 1]);
        }
    }

    /**
     * 새로 생성된 섹션을 특정 위치로 이동 (개선된 안전 버전)
     */
    private function moveNewSectionToPosition(int $newSection, int $targetPosition): void
    {   
        // 현재 섹션들 가져오기 (새 섹션 제외)
        $existingSections = Menu::select('section')
            ->distinct()
            ->where('section', '!=', $newSection)
            ->orderBy('section')
            ->pluck('section')
            ->toArray();
        
        // 목표 위치가 유효한지 확인
        if ($targetPosition < 1 || $targetPosition > count($existingSections) + 1) {
            return;
        }
        
        // 섹션 재정렬을 위한 매핑 생성
        $mapping = [];
        $newOrder = [];
        
        // 새 순서 생성: targetPosition에 newSection 삽입
        for ($i = 1; $i <= count($existingSections) + 1; $i++) {
            if ($i == $targetPosition) {
                $newOrder[] = $newSection;
            } else {
                $existingIndex = $i > $targetPosition ? $i - 2 : $i - 1;
                if (isset($existingSections[$existingIndex])) {
                    $newOrder[] = $existingSections[$existingIndex];
                }
            }
        }
        
        // 매핑 생성
        foreach ($newOrder as $index => $sectionNumber) {
            $newSectionNumber = $index + 1;
            if ($sectionNumber != $newSectionNumber) {
                $mapping[$sectionNumber] = $newSectionNumber;
            }
        }
        
        if (empty($mapping)) {
            return;
        }
        
        // 임시 섹션 번호를 사용하여 충돌 방지 (tinyint 범위 내에서)
        $tempSectionBase = 200; // 200번대를 임시로 사용
        
        // 현재 사용 중인 최대 섹션 번호 확인
        $maxUsedSection = max(array_merge($existingSections, [$newSection]));
        if ($maxUsedSection >= $tempSectionBase) {
            // 더 작은 임시 번호 사용
            $tempSectionBase = min(100, 255 - count($mapping) - 10);
        }
        
        // 1단계: 모든 변경될 섹션을 임시 번호로 이동
        foreach ($mapping as $oldSection => $newSectionNumber) {
            $tempSection = $tempSectionBase + array_search($oldSection, array_keys($mapping));
            DB::table('menus')
                ->where('section', $oldSection)
                ->update(['section' => $tempSection]);
        }
        
        // 2단계: 임시 번호에서 최종 번호로 이동
        foreach ($mapping as $oldSection => $newSectionNumber) {
            $tempSection = $tempSectionBase + array_search($oldSection, array_keys($mapping));
            DB::table('menus')
                ->where('section', $tempSection)
                ->update(['section' => $newSectionNumber]);
        }
    }
}
