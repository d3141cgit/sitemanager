<?php

namespace SiteManager\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use SiteManager\Models\Member;
use SiteManager\Models\Group;
use SiteManager\Models\Menu;
use SiteManager\Models\Setting;
use SiteManager\Services\ConfigService;
use SiteManager\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class AdminController extends Controller
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
        $stats = [
            'total_members' => Member::count(),
            'active_members' => Member::where('active', true)->count(),
            'total_groups' => Group::count(),
            'total_menus' => Menu::count(),
        ];

        $recent_members = Member::latest()
            ->limit(10)
            ->get(['id', 'name', 'email', 'active', 'created_at']);

        // 멤버 가입 동향 통계
        $currentDate = now();
        $thisMonth = Member::whereMonth('created_at', $currentDate->month)
            ->whereYear('created_at', $currentDate->year)
            ->count();
        
        $lastMonthDate = $currentDate->copy()->subMonth();
        $lastMonth = Member::whereMonth('created_at', $lastMonthDate->month)
            ->whereYear('created_at', $lastMonthDate->year)
            ->count();
        
        $growth = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : ($thisMonth > 0 ? 100 : 0);
        
        $memberStats = [
            'thisMonth' => $thisMonth,
            'lastMonth' => $lastMonth,
            'growth' => $growth
        ];

        // 그룹별 멤버 분포 통계
        $groupStats = Group::withCount('members')
            ->where('active', true)
            ->orderBy('members_count', 'desc')
            ->limit(5)
            ->get()
            ->map(function($group) {
                return [
                    'name' => $group->name,
                    'count' => $group->members_count
                ];
            });

        // 존재하지 않는 route를 사용하는 메뉴들 확인
        $invalidRouteMenus = $this->menuService->findMenusWithInvalidRoutes();

        return view('admin.dashboard', compact('stats', 'recent_members', 'memberStats', 'groupStats', 'invalidRouteMenus'));
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

        return view('admin.statistics', compact('monthly_stats', 'group_stats'));
    }

    /**
     * 시스템 설정
     */
    public function settings()
    {
        $configs = ConfigService::get();
        $cfg_type = ConfigService::$cfg_type;
        
        return view('admin.settings', compact('configs', 'cfg_type'));
    }

    /**
     * 설정 처리
     */
    public function processConfig(Request $request)
    {
        try {
            ConfigService::process($request);
            return redirect()->route('admin.settings')->with('success', '설정이 성공적으로 저장되었습니다.');
        } catch (\Exception $e) {
            return redirect()->route('admin.settings')->with('error', '설정 저장 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 설정 초기화 (기본값으로 리셋)
     */
    public function resetConfig(Request $request)
    {
        try {
            ConfigService::resetToDefaults();
            return redirect()->route('admin.settings')->with('success', '설정이 기본값으로 초기화되었습니다.');
        } catch (\Exception $e) {
            return redirect()->route('admin.settings')->with('error', '설정 초기화 중 오류가 발생했습니다: ' . $e->getMessage());
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
            
            return redirect()->route('admin.settings')->with('success', '리소스 파일이 성공적으로 초기화되었습니다. CSS/JS 파일들이 다시 생성됩니다.');
        } catch (\Exception $e) {
            return redirect()->route('admin.settings')->with('error', '리소스 초기화 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }
}
