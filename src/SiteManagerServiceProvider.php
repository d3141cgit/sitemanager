<?php

namespace SiteManager;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Gate;
use SiteManager\Http\Middleware\AdminMiddleware;
use SiteManager\Http\Middleware\CheckMenuPermission;
use SiteManager\Services\BoardService;
use SiteManager\Services\ConfigService;
use SiteManager\Services\PermissionService;
use SiteManager\Services\MemberService;

class SiteManagerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // 헬퍼 함수 로드
        require_once __DIR__.'/Helpers/functions.php';
        require_once __DIR__.'/Helpers/navigation_helpers.php';
        require_once __DIR__.'/Helpers/PermissionHelper.php';
        require_once __DIR__.'/Helpers/ResourceHelper.php';
        
        // 설정 파일 로드
        $this->mergeConfigFrom(__DIR__.'/../config/sitemanager.php', 'sitemanager');
        $this->mergeConfigFrom(__DIR__.'/../config/member.php', 'member');
        $this->mergeConfigFrom(__DIR__.'/../config/menu.php', 'menu');
        $this->mergeConfigFrom(__DIR__.'/../config/permissions.php', 'permissions');
        
        // 뷰 로드
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sitemanager');
        
        // 마이그레이션 로드
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        
        // 라우트 로드 (모두 web 미들웨어 그룹으로)
        Route::middleware('web')->group(function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
        
        Route::middleware('web')->group(function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        });
        
        // 미들웨어 등록
        $router = $this->app['router'];
        $router->aliasMiddleware('admin', AdminMiddleware::class);
        $router->aliasMiddleware('menu.permission', CheckMenuPermission::class);
        
        // 뷰 컴포저 등록
        View::composer('*', \SiteManager\Http\View\Composers\NavigationComposer::class);
        
        // 퍼블리싱 설정
        if ($this->app->runningInConsole()) {
            // 설정 파일 발행
            $this->publishes([
                __DIR__.'/../config/sitemanager.php' => config_path('sitemanager.php'),
                __DIR__.'/../config/member.php' => config_path('member.php'),
                __DIR__.'/../config/menu.php' => config_path('menu.php'),
                __DIR__.'/../config/permissions.php' => config_path('permissions.php'),
            ], 'sitemanager-config');
            
            // 뷰 파일 발행 (커스터마이징용)
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/sitemanager'),
            ], 'sitemanager-views');
            
            // 스타터 템플릿 발행 (일반 사이트용 - 프로젝트 루트로)
            $this->publishes([
                __DIR__.'/../resources/views/layouts/app.blade.php' => resource_path('views/layouts/app.blade.php'),
                __DIR__.'/../resources/views/main.blade.php' => resource_path('views/welcome.blade.php'),
                __DIR__.'/../resources/views/board' => resource_path('views/board'),
                __DIR__.'/../resources/views/auth' => resource_path('views/auth'),
                __DIR__.'/../resources/views/user' => resource_path('views/user'),
                __DIR__.'/../resources/views/components' => resource_path('views/components'),
            ], 'sitemanager-starter');
            
            // 마이그레이션 발행
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'sitemanager-migrations');
            
            // 에셋 발행
            $this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/sitemanager'),
                __DIR__.'/../resources/css' => public_path('vendor/sitemanager/css'),
                __DIR__.'/../resources/js' => public_path('vendor/sitemanager/js'),
            ], 'sitemanager-assets');
            
            // 기본 이미지 발행 (Admin용)
            $this->publishes([
                __DIR__.'/../resources/assets/images' => public_path('images'),
            ], 'sitemanager-images');
        }
        
        // 권한 정의
        $this->defineGates();
    }

    public function register()
    {
        // 서비스 바인딩
        $this->app->singleton(BoardService::class);
        $this->app->singleton(ConfigService::class);
        $this->app->singleton(PermissionService::class);
        $this->app->singleton(MemberService::class);
        
        // Repository 바인딩
        $this->app->bind(
            \SiteManager\Repositories\MemberRepositoryInterface::class,
            \SiteManager\Repositories\MemberRepository::class
        );
        $this->app->bind(
            \SiteManager\Repositories\MenuRepositoryInterface::class,
            \SiteManager\Repositories\MenuRepository::class
        );
        
        // 별칭 등록
        $this->app->alias(BoardService::class, 'sitemanager.board');
        $this->app->alias(ConfigService::class, 'sitemanager.config');
        $this->app->alias(PermissionService::class, 'sitemanager.permission');
        $this->app->alias(MemberService::class, 'sitemanager.member');
        
        // 콘솔 명령어 등록 (웹에서도 사용 가능)
        $this->commands([
            \SiteManager\Console\Commands\InstallCommand::class,
            \SiteManager\Console\Commands\CreateAdminCommand::class,
            \SiteManager\Console\Commands\PublishStarterCommand::class,
            \SiteManager\Console\Commands\TestS3Connection::class,
            \SiteManager\Console\Commands\CheckS3Configuration::class,
            \SiteManager\Console\Commands\MigrateImagesToS3::class,
            \SiteManager\Console\Commands\ResourceCommand::class,
        ]);
    }
    
    private function defineGates()
    {
        Gate::define('admin', function ($user) {
            return $user->level >= config('sitemanager.permissions.admin_level', 200);
        });
        
        Gate::define('board.write', function ($user, $board) {
            return can('write', $board);
        });
        
        Gate::define('board.read', function ($user, $board) {
            return can('read', $board);
        });
    }
}
