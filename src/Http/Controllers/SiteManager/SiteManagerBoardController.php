<?php

namespace SiteManager\Http\Controllers\SiteManager;

use SiteManager\Http\Controllers\Controller;
use SiteManager\Models\Board;
use SiteManager\Models\Menu;
use SiteManager\Services\BoardService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class SiteManagerBoardController extends Controller
{
    /**
     * 시스템 정의 설정들
     */
    protected array $systemSettings = [
        'use_categories' => [
            'boolean', 
            'Use Categories', 
            'Enable category system for posts',
            [
                'sub_section' => [
                    'title' => 'Category Settings',
                    'settings' => [
                        'categories' => [
                            'special', // 특별 처리 필드 (별도 컬럼 저장)
                            'Category List',
                            'Enter one category name per line.',
                            [
                                'type' => 'textarea',
                                'placeholder' => 'Enter one category per line',
                                'rows' => 5
                            ]
                        ],
                        'category_multiple' => [
                            'boolean', 
                            'Multiple Categories', 
                            'Allow posts to have multiple categories'
                        ],
                    ]
                ]
            ]
        ],
        'allow_file_upload' => [
            'boolean', 
            'Allow File Upload', 
            'Allow users to upload files with posts',
            [
                'sub_section' => [
                    'title' => 'File Upload Settings',
                    'settings' => [
                        'max_file_size' => [
                            'integer|min:100|max:51200', 
                            'Max File Size (KB)', 
                            'Maximum file size allowed for uploads',
                            [
                                'type' => 'number',
                                'min' => 100,
                                'max' => 51200,
                                'default' => 2048
                            ]
                        ],
                        'max_files_per_post' => [
                            'integer|min:1|max:20', 
                            'Max Files Per Post', 
                            'Maximum number of files per post',
                            [
                                'type' => 'number',
                                'min' => 1,
                                'max' => 20,
                                'default' => 5
                            ]
                        ],
                        'allowed_file_types' => [
                            'string', 
                            'Allowed File Types', 
                            'Comma-separated list of allowed file extensions',
                            [
                                'type' => 'text',
                                'placeholder' => 'jpg,jpeg,png,gif,pdf',
                                'default' => 'jpg,jpeg,png,gif,pdf'
                            ]
                        ],
                        'file_categories' => [
                            'string', 
                            'File Categories', 
                            'Categories for organizing files',
                            [
                                'type' => 'textarea',
                                'placeholder' => "thumbnail\nseo\nheader",
                                'rows' => 3,
                                'default' => ''
                            ]
                        ]
                    ]
                ]
            ]
        ],
        'allow_comments' => [
            'boolean', 
            'Allow Comments', 
            'Enable commenting system for posts'
        ],
        'moderate_comments' => [
            'boolean', 
            'Moderate Comments', 
            'Comments require admin approval before appearing'
        ],
        'use_tags' => [
            'boolean', 
            'Use Tags', 
            'Enable tag system for posts'
        ],
        'require_approval' => [
            'boolean', 
            'Require Approval', 
            'Posts require admin approval before publishing'
        ],
        'show_name' => [
            'boolean', 
            'Show Name', 
            'Display author information on posts'
        ],
        'show_info' => [
            'boolean', 
            'Show Info', 
            'Display meta data on posts'
        ],
        'enable_search' => [
            'boolean', 
            'Enable Search', 
            'Enable search functionality for this board'
        ],
        'allow_secret_posts' => [
            'boolean', 
            'Allow Secret Posts', 
            'Allow secret post functionality with password protection'
        ],
    ];

    public function __construct(
        private BoardService $boardService
    ) {}

    /**
     * 모든 시스템 설정 키 수집 (중첩 구조 포함)
     */
    public static function getSystemSettingKeys(): array
    {
        // 인스턴스 생성해서 systemSettings 프로퍼티 사용
        $instance = new self(app(\SiteManager\Services\BoardService::class));
        
        $keys = [];
        
        foreach ($instance->systemSettings as $key => $config) {
            $keys[] = $key;
            
            // sub_section이 있는 경우 하위 설정들도 포함
            if (isset($config[3]['sub_section']['settings'])) {
                foreach ($config[3]['sub_section']['settings'] as $subKey => $subConfig) {
                    $keys[] = $subKey;
                }
            }
        }
        
        return $keys;
    }

    /**
     * 모든 시스템 설정 키 수집 (중첩 구조 포함) - 인스턴스 메서드
     */
    private function getAllSystemSettingKeys(): array
    {
        return self::getSystemSettingKeys();
    }

    /**
     * 모든 시스템 설정을 플랫하게 변환 (validation용)
     */
    private function getFlatSystemSettings(): array
    {
        $flat = [];
        
        foreach ($this->systemSettings as $key => $config) {
            $flat[$key] = $config;
            
            // sub_section이 있는 경우 하위 설정들도 포함
            if (isset($config[3]['sub_section']['settings'])) {
                foreach ($config[3]['sub_section']['settings'] as $subKey => $subConfig) {
                    $flat[$subKey] = $subConfig;
                }
            }
        }
        
        return $flat;
    }

    /**
     * 시스템 설정에 대한 validation 규칙 생성
     */
    private function getSystemSettingsValidationRules(): array
    {
        $rules = [];
        $flatSettings = $this->getFlatSystemSettings();
        
        foreach ($flatSettings as $key => $config) {
            $type = $config[0]; // 첫 번째 요소가 validation 타입
            $rules["settings.{$key}"] = "nullable|{$type}";
        }
        return $rules;
    }

    /**
     * 기본 validation 규칙 반환
     */
    private function getBaseValidationRules(bool $isUpdate = false, ?Board $board = null): array
    {
        $rules = [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'menu_id' => 'nullable|exists:menus,id',
            'skin' => 'nullable|string|max:50',
            'posts_per_page' => 'nullable|integer|min:5|max:100',
            'categories' => 'nullable|string',
            'settings' => 'nullable|array',
            'custom_settings' => 'nullable|array',
            'custom_settings.*.key' => 'nullable|string|max:50',
            'custom_settings.*.value' => 'nullable|string|max:500',
        ];

        // slug 규칙
        if ($isUpdate && $board) {
            $rules['slug'] = [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('boards', 'slug')->ignore($board->id),
            ];
            $rules['status'] = 'required|in:active,inactive';
        } else {
            $rules['slug'] = 'required|string|max:50|unique:boards,slug|regex:/^[a-z0-9_]+$/';
        }

        // 시스템 설정 규칙 추가
        return array_merge($rules, $this->getSystemSettingsValidationRules());
    }

    /**
     * 시스템 설정 처리
     */
    private function processSystemSettings(Request $request, array $existingSettings = []): array
    {
        $settings = $existingSettings;
        $flatSettings = $this->getFlatSystemSettings();
        
        foreach ($flatSettings as $key => $config) {
            $type = $config[0]; // 첫 번째 요소가 validation 타입
            
            // 별도 필드로 저장되는 항목들은 스킵 (posts_per_page, categories)
            if ($key === 'posts_per_page' || $key === 'categories') {
                continue;
            }
            
            if (str_contains($type, 'boolean')) {
                // 키값이 곧 폼 필드명
                $settings[$key] = $request->has($key);
            } elseif ($type === 'special') {
                // 특별 처리 필드 (categories)는 별도 처리되므로 스킵
                continue;
            } else {
                // 다른 타입들은 settings 배열에서 가져옴
                if ($request->has("settings.{$key}")) {
                    $value = $request->input("settings.{$key}");
                    if ($value !== null && $value !== '') {
                        $settings[$key] = $value;
                    }
                }
            }
        }
        
        return $settings;
    }

    /**
     * 커스텀 설정 처리
     */
    private function processCustomSettings(array $customSettings, array $existingSettings = []): array
    {
        // 시스템 설정 키들
        $systemKeys = $this->getAllSystemSettingKeys();
        
        // 기존 설정에서 시스템 설정만 보존
        $settings = array_intersect_key($existingSettings, array_flip($systemKeys));
        
        // 새로운 커스텀 설정만 추가 (기존 커스텀 설정은 완전 대체)
        if (!empty($customSettings)) {
            foreach ($customSettings as $customSetting) {
                if (!empty($customSetting['key']) && isset($customSetting['value'])) {
                    $key = trim($customSetting['key']);
                    $value = trim($customSetting['value']);
                    
                    // 시스템 설정과 중복되지 않도록 체크
                    if (in_array($key, $systemKeys)) {
                        continue;
                    }
                    
                    // JSON 형태인지 확인해서 파싱
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $settings[$key] = $decoded;
                    } else {
                        // boolean 값 처리
                        if (strtolower($value) === 'true') {
                            $settings[$key] = true;
                        } elseif (strtolower($value) === 'false') {
                            $settings[$key] = false;
                        } elseif (is_numeric($value)) {
                            $settings[$key] = is_float($value) ? (float)$value : (int)$value;
                        } else {
                            $settings[$key] = $value;
                        }
                    }
                }
            }
        }
        
        return $settings;
    }

    /**
     * 시스템 설정 목록 반환 (뷰에서 사용)
     */
    public function getSystemSettings(): array
    {
        return $this->systemSettings;
    }

    /**
     * 시스템 설정과 커스텀 설정 분리
     */
    private function separateSettings(array $settings): array
    {
        $systemKeys = $this->getAllSystemSettingKeys();
        $systemSettings = array_intersect_key($settings, array_flip($systemKeys));
        $customSettings = array_diff_key($settings, array_flip($systemKeys));
        
        return [
            'system' => $systemSettings,
            'custom' => $customSettings
        ];
    }

    /**
     * 게시판 목록
     */
    public function index(): View
    {
        $boards = Board::with('menu')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // 각 게시판의 통계 정보를 미리 계산
        $pendingCommentsCount = $this->boardService->getAllBoardsPendingCommentsCount();
        
        foreach ($boards as $board) {
            $board->posts_count = $board->getPostsCount();
            $board->comments_count = $board->getCommentsCount();
            $board->deleted_posts_count = $board->getDeletedPostsCount();
            $board->deleted_comments_count = $board->getDeletedCommentsCount();
            $board->attachments_count = $board->getAttachmentsCount();
            $board->pending_comments_count = $pendingCommentsCount[$board->id] ?? 0;
        }

        return view('sitemanager::sitemanager.board.index', compact('boards'));
    }

    /**
     * 게시판 생성 폼
     */
    public function create(): View
    {
        $menus = Menu::orderBy('section')
            ->orderBy('_lft')
            ->get();

        $systemSettings = $this->systemSettings;
        $separatedSettings = ['system' => [], 'custom' => []]; // 새 게시판용 빈 설정
        $availableSkins = $this->boardService->getAvailableSkins();

        return view('sitemanager::sitemanager.board.form', compact('menus', 'systemSettings', 'separatedSettings', 'availableSkins'));
    }

    /**
     * 게시판 생성
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->getBaseValidationRules());

        // 시스템 설정 처리
        $settings = $this->processSystemSettings($request, $validated['settings'] ?? []);
        
        // custom_settings 처리
        $settings = $this->processCustomSettings($validated['custom_settings'] ?? [], $settings);
        
        $validated['settings'] = $settings;

        // categories 문자열을 배열로 변환
        if (!empty($validated['categories'])) {
            $validated['categories'] = array_filter(
                array_map('trim', explode("\n", $validated['categories']))
            );
        } else {
            $validated['categories'] = [];
        }

        try {
            $board = $this->boardService->createBoard($validated);
            
            // 메뉴에 연결된 경우 메뉴의 라우트 정보 업데이트
            if ($validated['menu_id']) {
                $menu = Menu::find($validated['menu_id']);
                $menu->update([
                    'type' => 'route',
                    'target' => 'board.index',
                ]);
            }
            
            return redirect()
                ->route('sitemanager.boards.index')
                ->with('success', "Board \"{$board->name}\" has been created successfully.");
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Error creating board: ' . $e->getMessage());
        }
    }

    /**
     * Display the board (same as edit)
     */
    public function show(Board $board): View
    {
        $menus = Menu::orderBy('section')
            ->orderBy('_lft')
            ->get();

        $systemSettings = $this->systemSettings;
        $separatedSettings = $this->separateSettings($board->settings ?? []);
        $availableSkins = $this->boardService->getAvailableSkins();

        return view('sitemanager::sitemanager.board.form', compact('board', 'menus', 'systemSettings', 'separatedSettings', 'availableSkins'));
    }

    /**
     * 게시판 수정 폼
     */
    public function edit(Board $board): View
    {
        $menus = Menu::orderBy('section')
            ->orderBy('_lft')
            ->get();

        $systemSettings = $this->systemSettings;
        $separatedSettings = $this->separateSettings($board->settings ?? []);
        $availableSkins = $this->boardService->getAvailableSkins();

        return view('sitemanager::sitemanager.board.form', compact('board', 'menus', 'systemSettings', 'separatedSettings', 'availableSkins'));
    }

    /**
     * 게시판 수정
     */
    public function update(Request $request, Board $board): RedirectResponse
    {
        $validated = $request->validate($this->getBaseValidationRules(true, $board));

        // 기존 설정과 새 설정 병합
        $existingSettings = $board->settings ?? [];
        
        // 시스템 설정 처리
        $settings = $this->processSystemSettings($request, $existingSettings);
        
        // 커스텀 설정 처리 (기존 커스텀 설정은 완전 대체됨)
        $settings = $this->processCustomSettings($validated['custom_settings'] ?? [], $settings);
        
        $validated['settings'] = $settings;

        // categories 문자열을 배열로 변환
        if (!empty($validated['categories'])) {
            $validated['categories'] = array_filter(
                array_map('trim', explode("\n", $validated['categories']))
            );
        } else {
            $validated['categories'] = [];
        }

        try {
            DB::beginTransaction();
            
            // 기존 메뉴 연결 해제 처리
            $oldMenuId = $board->menu_id;
            
            // 슬러그가 변경된 경우 테이블명도 변경
            if ($board->slug !== $validated['slug']) {
                $this->boardService->renameBoardTables($board->slug, $validated['slug']);
            }

            $board->update($validated);
            
            // 메뉴 연결 변경 처리
            if ($oldMenuId !== $validated['menu_id']) {
                // 기존 메뉴에서 연결 해제 (라우트 정보 제거)
                if ($oldMenuId) {
                    $oldMenu = Menu::find($oldMenuId);
                    if ($oldMenu) {
                        $oldMenu->update([
                            'type' => 'text',
                            'target' => null,
                        ]);
                    }
                }
                
                // 새 메뉴에 연결 (라우트 정보 설정)
                if ($validated['menu_id']) {
                    $newMenu = Menu::find($validated['menu_id']);
                    if ($newMenu) {
                        $newMenu->update([
                            'type' => 'route',
                            'target' => 'board.index',
                        ]);
                    }
                }
            }
            
            DB::commit();

            return redirect()
                ->route('sitemanager.boards.edit', $board)
                ->with('success', 'Board has been updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Error updating board: ' . $e->getMessage());
        }
    }

    /**
     * 게시판 삭제
     */
    public function destroy(Board $board): RedirectResponse
    {
        try {
            DB::beginTransaction();
            
            // 연결된 메뉴의 라우트 정보 제거
            if ($board->menu_id) {
                $menu = Menu::find($board->menu_id);
                if ($menu) {
                    $menu->update([
                        'type' => 'text',
                        'target' => null,
                    ]);
                }
            }
            
            $boardName = $board->name;
            $this->boardService->deleteBoard($board);
            
            DB::commit();
            
            return redirect()
                ->route('sitemanager.boards.index')
                ->with('success', "Board \"{$boardName}\" has been deleted successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->with('error', 'Error deleting board: ' . $e->getMessage());
        }
    }

    /**
     * Toggle board status
     */
    public function toggleStatus(Board $board): RedirectResponse
    {
        $board->update([
            'status' => $board->status === 'active' ? 'inactive' : 'active'
        ]);

        $status = $board->status === 'active' ? 'activated' : 'deactivated';
        
        return back()->with('success', "Board has been {$status}.");
    }

    /**
     * Regenerate board tables
     */
    public function regenerateTables(Board $board): RedirectResponse
    {
        try {
            $this->boardService->regenerateBoardTables($board->slug);
            
            return back()->with('success', 'Board tables have been regenerated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error regenerating tables: ' . $e->getMessage());
        }
    }
}
