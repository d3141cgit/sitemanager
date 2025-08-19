<?php

namespace SiteManager\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use SiteManager\Models\Board;
use SiteManager\Models\Menu;
use SiteManager\Services\BoardService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class AdminBoardController extends Controller
{
    public function __construct(
        private BoardService $boardService
    ) {}

    /**
     * 게시판 목록
     */
    public function index(): View
    {
        $boards = Board::with('menu')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // 각 게시판의 통계 정보를 미리 계산
        foreach ($boards as $board) {
            $board->posts_count = $board->getPostsCount();
            $board->comments_count = $board->getCommentsCount();
        }

        return view('admin.board.index', compact('boards'));
    }

    /**
     * 게시판 생성 폼
     */
    public function create(): View
    {
        $menus = Menu::orderBy('section')
            ->orderBy('_lft')
            ->get();

        return view('admin.board.form', compact('menus'));
    }

    /**
     * 게시판 생성
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:50|unique:boards,slug|regex:/^[a-z0-9_]+$/',
            'description' => 'nullable|string|max:500',
            'menu_id' => 'nullable|exists:menus,id',
            'skin' => 'nullable|string|max:50',
            'posts_per_page' => 'nullable|integer|min:5|max:100',
            'use_categories' => 'nullable|boolean',
            'use_files' => 'nullable|boolean',
            'allow_comments' => 'nullable|boolean',
            'use_tags' => 'nullable|boolean',
            'categories' => 'nullable|string',
            'settings' => 'nullable|array',
            'settings.max_file_size' => 'nullable|integer|min:100|max:51200',
            'settings.max_files_per_post' => 'nullable|integer|min:1|max:20',
            'settings.allowed_file_types' => 'nullable|string',
        ]);

        // use_* 체크박스 값들을 settings에 추가
        $settings = $validated['settings'] ?? [];
        $settings['useCategories'] = $request->has('use_categories');
        $settings['allowFileUpload'] = $request->has('use_files');
        $settings['allow_comments'] = $request->has('allow_comments');
        $settings['useTags'] = $request->has('use_tags');
        
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
                ->route('admin.boards.index')
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

        return view('admin.board.form', compact('board', 'menus'));
    }

    /**
     * 게시판 수정 폼
     */
    public function edit(Board $board): View
    {
        $menus = Menu::orderBy('section')
            ->orderBy('_lft')
            ->get();

        return view('admin.board.form', compact('board', 'menus'));
    }

    /**
     * 게시판 수정
     */
    public function update(Request $request, Board $board): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('boards', 'slug')->ignore($board->id),
            ],
            'description' => 'nullable|string|max:500',
            'menu_id' => 'nullable|exists:menus,id',
            'status' => 'required|in:active,inactive',
            'skin' => 'nullable|string|max:50',
            'posts_per_page' => 'nullable|integer|min:5|max:100',
            'use_categories' => 'nullable|boolean',
            'use_files' => 'nullable|boolean',
            'allow_comments' => 'nullable|boolean',
            'use_tags' => 'nullable|boolean',
            'categories' => 'nullable|string',
            'settings' => 'nullable|array',
            'settings.max_file_size' => 'nullable|integer|min:100|max:51200',
            'settings.max_files_per_post' => 'nullable|integer|min:1|max:20',
            'settings.allowed_file_types' => 'nullable|string',
        ]);

        // use_* 체크박스 값들을 settings에 추가
        $settings = array_merge($board->settings ?? [], $validated['settings'] ?? []);
        $settings['useCategories'] = $request->has('use_categories');
        $settings['allowFileUpload'] = $request->has('use_files');
        $settings['allow_comments'] = $request->has('allow_comments');
        $settings['useTags'] = $request->has('use_tags');
        
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
                ->route('admin.boards.edit', $board)
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
                ->route('admin.boards.index')
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
