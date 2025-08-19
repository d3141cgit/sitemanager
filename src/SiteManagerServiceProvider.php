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

class SiteManagerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // 설정 파일 로드
        $this->mergeConfigFrom(__DIR__.'/../config/sitemanager.php', 'sitemanager');
        
        // 뷰 로드
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sitemanager');
        
        // 마이그레이션 로드
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        
        // 라우트 로드
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        
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
            ], 'sitemanager-config');
            
            // 뷰 파일 발행 (커스터마이징용)
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/sitemanager'),
            ], 'sitemanager-views');
            
            // 마이그레이션 발행
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'sitemanager-migrations');
            
            // 에셋 발행
            $this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/sitemanager'),
            ], 'sitemanager-assets');
            
            // 콘솔 명령어 등록
            $this->commands([
                \SiteManager\Console\Commands\InstallCommand::class,
                \SiteManager\Console\Commands\CreateAdminCommand::class,
                \SiteManager\Console\Commands\TestS3Connection::class,
                \SiteManager\Console\Commands\CheckS3Configuration::class,
                \SiteManager\Console\Commands\MigrateImagesToS3::class,
                \SiteManager\Console\Commands\ResourceCommand::class,
            ]);
        }
        
        // 권한 정의
        $this->defineGates();
    }

    public function register()
    {
        // 서비스 바인딩
        $this->app->singleton(BoardService::class);
        $this->app->singleton(ConfigService::class);
        
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
