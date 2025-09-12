<?php

namespace SiteManager;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SiteManager\Http\Middleware\SiteManagerMiddleware;
use SiteManager\Http\Middleware\CheckMenuPermission;
use SiteManager\Services\BoardService;
use SiteManager\Services\ConfigService;
use SiteManager\Services\PermissionService;
use SiteManager\Services\MemberService;
use SiteManager\Services\FileUploadService;
use SiteManager\Services\EmailVerificationService;

class SiteManagerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // 헬퍼 함수 자동 로드
        foreach (glob(__DIR__.'/Helpers/*.php') as $file) {
            require_once $file;
        }
        
        // 설정 파일 로드
        $this->mergeConfigFrom(__DIR__.'/../config/sitemanager.php', 'sitemanager');
        $this->mergeConfigFrom(__DIR__.'/../config/member.php', 'member');
        $this->mergeConfigFrom(__DIR__.'/../config/menu.php', 'menu');
        $this->mergeConfigFrom(__DIR__.'/../config/permissions.php', 'permissions');
        $this->mergeConfigFrom(__DIR__.'/../config/auth.php', 'auth');
        
        // 뷰 로드 (패키지 기본)
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sitemanager');
        
        // 뷰 컴포넌트 등록 (오버라이드 지원)
        $this->registerViewComponents();
        
        // 마이그레이션 로드
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        
        // 마이그레이션 후 언어 데이터 복원 이벤트 등록
        $this->registerMigrationEvents();
        
        // 라우트 로드 (모두 web 미들웨어 그룹으로)
        Route::middleware('web')->group(function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
        
        Route::middleware('web')->group(function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/sitemanager.php');
        });
        
        // 미들웨어 등록
        $router = $this->app['router'];
        $router->aliasMiddleware('sitemanager', SiteManagerMiddleware::class);
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
                __DIR__.'/../config/auth.php' => config_path('auth.php'),
            ], 'sitemanager-config');
            
            // 뷰 파일 발행 (커스터마이징용)
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/sitemanager'),
            ], 'sitemanager-views');
            
            // 스타터 템플릿 발행 (일반 사이트용 - 프로젝트 루트로)
            // $this->publishes([
            //     __DIR__.'/../resources/views/layouts/app.blade.php' => resource_path('views/layouts/app.blade.php'),
            //     __DIR__.'/../resources/views/main.blade.php' => resource_path('views/welcome.blade.php'),
            //     __DIR__.'/../resources/views/board' => resource_path('views/board'),
            //     __DIR__.'/../resources/views/auth' => resource_path('views/auth'),
            //     __DIR__.'/../resources/views/components' => resource_path('views/components'),
            // ], 'sitemanager-starter');
            
            // 마이그레이션 발행
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'sitemanager-migrations');
            
            // 에셋 발행
            // $this->publishes([
            //     __DIR__.'/../resources/assets' => public_path('vendor/sitemanager'),
            //     __DIR__.'/../resources/css' => public_path('vendor/sitemanager/css'),
            //     __DIR__.'/../resources/js' => public_path('vendor/sitemanager/js'),
            // ], 'sitemanager-assets');
            
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
        $this->app->singleton(FileUploadService::class);
        $this->app->singleton(EmailVerificationService::class);
        
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
        $this->app->alias(FileUploadService::class, 'sitemanager.fileupload');
        $this->app->alias(EmailVerificationService::class, 'sitemanager.emailverification');
        
        // 콘솔 명령어 등록 (웹에서도 사용 가능)
        $this->commands([
            \SiteManager\Console\Commands\InstallCommand::class,
            \SiteManager\Console\Commands\CreateAdminCommand::class,
            \SiteManager\Console\Commands\PublishStarterCommand::class,
            \SiteManager\Console\Commands\TestS3Connection::class,
            \SiteManager\Console\Commands\CheckS3Configuration::class,
            \SiteManager\Console\Commands\MigrateImagesToS3::class,
            \SiteManager\Console\Commands\ResourceCommand::class,
            \SiteManager\Console\Commands\RestoreLanguageCommand::class,
            \SiteManager\Console\Commands\DumpLanguageCommand::class,
        ]);
    }
    
    /**
     * 마이그레이션 이벤트를 등록합니다.
     */
    private function registerMigrationEvents()
    {
        if (!$this->app->runningInConsole()) {
            return;
        }
        
        // 마이그레이션 완료 후 언어 데이터 복원
        Event::listen('Illuminate\Database\Events\MigrationsEnded', function ($event) {
            // languages 테이블이 존재하는지 확인
            if (!Schema::hasTable('languages')) {
                return;
            }
            
            // 언어 데이터가 비어있는지 확인
            $languageCount = DB::table('languages')->count();
            if ($languageCount > 0) {
                return; // 이미 데이터가 있으면 복원하지 않음
            }
            
            // 언어 데이터 복원
            $sqlPath = dirname(__DIR__, 2) . '/database/sql/languages.sql';
            if (file_exists($sqlPath)) {
                try {
                    $sql = file_get_contents($sqlPath);
                    if (!empty($sql)) {
                        DB::unprepared($sql);
                        Log::info('SiteManager: Language data restored automatically after migration');
                    }
                } catch (\Exception $e) {
                    Log::error('SiteManager: Failed to restore language data after migration: ' . $e->getMessage());
                }
            }
        });
    }
    
    private function defineGates()
    {
        Gate::define('admin', function ($user) {
            return $user->isAdmin();
        });
        
        Gate::define('board.write', function ($user, $board) {
            return can('write', $board);
        });
        
        Gate::define('board.read', function ($user, $board) {
            return can('read', $board);
        });
    }

    /**
     * 뷰 컴포넌트 등록 (Laravel 표준 경로 오버라이드 지원)
     */
    protected function registerViewComponents()
    {
        $components = $this->getViewComponents();
        
        $this->loadViewComponentsAs('sitemanager', $components);
    }

    /**
     * 뷰 컴포넌트 정의 (프로젝트에서 Laravel 표준 방식으로 오버라이드 가능)
     */
    protected function getViewComponents()
    {
        $defaultComponents = [
            'file-upload' => \SiteManager\View\Components\FileUpload::class,
            'editor' => \SiteManager\View\Components\Editor::class,
            'menu-breadcrumb' => \SiteManager\View\Components\MenuBreadcrumb::class,
            'menu-tabs' => \SiteManager\View\Components\MenuTabs::class,
            'menu-navigation' => \SiteManager\View\Components\MenuNavigation::class,
        ];

        // 프로젝트에서 컴포넌트 오버라이드 파일이 있는지 확인
        $overrideFile = app_path('SiteManager/ComponentOverrides.php');
        if (file_exists($overrideFile)) {
            $overrides = include $overrideFile;
            if (is_array($overrides)) {
                $defaultComponents = array_merge($defaultComponents, $overrides);
            }
        }

        return $defaultComponents;
    }
}
