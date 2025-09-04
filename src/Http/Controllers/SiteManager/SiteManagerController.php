<?php

namespace SiteManager\Http\Controllers\SiteManager;

use SiteManager\Http\Controllers\Controller;
use SiteManager\Models\Member;
use SiteManager\Models\Group;
use SiteManager\Models\Menu;
use SiteManager\Models\Setting;
use SiteManager\Services\ConfigService;
use SiteManager\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SiteManagerController extends Controller
{
    protected $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    /**
     * 관리자 대시보드
     */
    public function dashboard()
    {
        // 기본 통계
        $stats = [
            'total_boards' => \SiteManager\Models\Board::count(),
            'total_posts' => $this->getTotalPosts(),
            'total_comments' => $this->getTotalComments(),
            'total_attachments' => \SiteManager\Models\BoardAttachment::count(),
            'total_editor_images' => \SiteManager\Models\EditorImage::count(),
            'total_members' => Member::count(),
            'active_members' => Member::where('active', true)->count(),
            'total_menus' => Menu::count(),
        ];

        // 최근 게시글 (모든 게시판에서)
        $recent_posts = $this->getRecentPosts();

        // 최근 댓글
        $recent_comments = $this->getRecentComments();

        // 게시판별 통계
        $board_stats = $this->getBoardStats();

        // 최근 파일 업로드
        $recent_files = \SiteManager\Models\BoardAttachment::with(['board'])
            ->latest()
            ->limit(5)
            ->get(['id', 'board_slug', 'original_name', 'file_size', 'created_at']);

        // 존재하지 않는 route를 사용하는 메뉴들 확인
        $invalidRouteMenus = $this->menuService->findMenusWithInvalidRoutes();

        return view('sitemanager::sitemanager.dashboard', compact(
            'stats', 
            'recent_posts', 
            'recent_comments', 
            'board_stats', 
            'recent_files', 
            'invalidRouteMenus'
        ));
    }

    /**
     * 전체 게시글 수 계산
     */
    private function getTotalPosts()
    {
        $total = 0;
        $boards = \SiteManager\Models\Board::all();
        
        foreach ($boards as $board) {
            try {
                $postModelClass = \SiteManager\Models\BoardPost::forBoard($board->slug);
                $total += $postModelClass::count();
            } catch (\Exception $e) {
                // 테이블이 존재하지 않는 경우 무시
            }
        }
        
        return $total;
    }

    /**
     * 전체 댓글 수 계산
     */
    private function getTotalComments()
    {
        $total = 0;
        $boards = \SiteManager\Models\Board::all();
        
        foreach ($boards as $board) {
            try {
                $commentModelClass = \SiteManager\Models\BoardComment::forBoard($board->slug);
                $total += $commentModelClass::count();
            } catch (\Exception $e) {
                // 테이블이 존재하지 않는 경우 무시
            }
        }
        
        return $total;
    }

    /**
     * 최근 게시글 가져오기
     */
    private function getRecentPosts()
    {
        $posts = collect();
        $boards = \SiteManager\Models\Board::all();
        
        foreach ($boards as $board) {
            try {
                $postModelClass = \SiteManager\Models\BoardPost::forBoard($board->slug);
                $boardPosts = $postModelClass::latest()
                    ->limit(3)
                    ->get(['id', 'title', 'author_name', 'view_count', 'comment_count', 'created_at'])
                    ->map(function($post) use ($board) {
                        $post->board = $board;
                        return $post;
                    });
                $posts = $posts->concat($boardPosts);
            } catch (\Exception $e) {
                // 테이블이 존재하지 않는 경우 무시
            }
        }
        
        return $posts->sortByDesc('created_at')->take(10);
    }

    /**
     * 최근 댓글 가져오기
     */
    private function getRecentComments()
    {
        $comments = collect();
        $boards = \SiteManager\Models\Board::all();
        
        foreach ($boards as $board) {
            try {
                $commentModelClass = \SiteManager\Models\BoardComment::forBoard($board->slug);
                $boardComments = $commentModelClass::with('member')
                    ->latest()
                    ->limit(3)
                    ->get(['id', 'post_id', 'member_id', 'author_name', 'content', 'status', 'created_at'])
                    ->map(function($comment) use ($board) {
                        $comment->board = $board;
                        return $comment;
                    });
                $comments = $comments->concat($boardComments);
            } catch (\Exception $e) {
                // 테이블이 존재하지 않는 경우 무시
            }
        }
        
        return $comments->sortByDesc('created_at')->take(10);
    }

    /**
     * 게시판별 통계
     */
    private function getBoardStats()
    {
        $boards = \SiteManager\Models\Board::all();
        $stats = [];
        
        foreach ($boards as $board) {
            try {
                $postModelClass = \SiteManager\Models\BoardPost::forBoard($board->slug);
                $commentModelClass = \SiteManager\Models\BoardComment::forBoard($board->slug);
                
                $stats[] = [
                    'board' => $board,
                    'posts_count' => $postModelClass::count(),
                    'comments_count' => $commentModelClass::count(),
                    'recent_post_date' => $postModelClass::latest()->first()?->created_at,
                ];
            } catch (\Exception $e) {
                $stats[] = [
                    'board' => $board,
                    'posts_count' => 0,
                    'comments_count' => 0,
                    'recent_post_date' => null,
                ];
            }
        }
        
        return collect($stats)->sortByDesc('posts_count')->take(6);
    }

    /**
     * 통계 페이지
     */
    public function statistics()
    {
        // 월별 가입자 통계
        $monthly_stats = Member::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        // 그룹별 멤버 수
        $group_stats = Group::withCount('members')
            ->orderBy('members_count', 'desc')
            ->get();

        return view('sitemanager::sitemanager.statistics', compact('monthly_stats', 'group_stats'));
    }

    /**
     * 시스템 설정
     */
    public function settings()
    {
        $configs = ConfigService::get();
        $cfg_type = ConfigService::$cfg_type;
        
        return view('sitemanager::sitemanager.settings', compact('configs', 'cfg_type'));
    }

    /**
     * 설정 처리
     */
    public function processConfig(Request $request)
    {
        try {
            ConfigService::process($request);
            return redirect()->route('sitemanager.settings')->with('success', '설정이 성공적으로 저장되었습니다.');
        } catch (\Exception $e) {
            return redirect()->route('sitemanager.settings')->with('error', '설정 저장 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 설정 초기화 (기본값으로 리셋)
     */
    public function resetConfig(Request $request)
    {
        try {
            ConfigService::resetToDefaults();
            return redirect()->route('sitemanager.settings')->with('success', '설정이 기본값으로 초기화되었습니다.');
        } catch (\Exception $e) {
            return redirect()->route('sitemanager.settings')->with('error', '설정 초기화 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 리소스 파일 전체 리셋
     */
    public function resetResources(Request $request)
    {
        try {
            // Artisan 명령어 실행
            Artisan::call('resource', ['action' => 'clear', '--force' => true]);
            
            return redirect()->route('sitemanager.settings')->with('success', '리소스 파일이 성공적으로 초기화되었습니다. CSS/JS 파일들이 다시 생성됩니다.');
        } catch (\Exception $e) {
            return redirect()->route('sitemanager.settings')->with('error', '리소스 초기화 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * SiteManager용 robots.txt 응답
     */
    public function robots()
    {
        $content = "User-agent: *\n";
        $content .= "Disallow: /\n\n";
        $content .= "# SiteManager Admin Area - Access Restricted\n";
        $content .= "# This area is for authorized administrators only\n";
        $content .= "# All crawling and indexing is prohibited\n";

        return response($content, 200)
            ->header('Content-Type', 'text/plain')
            ->header('Cache-Control', 'public, max-age=3600'); // 1시간 캐시
    }
}
