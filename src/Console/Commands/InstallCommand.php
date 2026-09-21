<?php

namespace SiteManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'sitemanager:install 
                            {--force : Force the operation to run when in production}';
    
    protected $description = 'Install SiteManager package with all required setup';

    public function handle()
    {
        $this->info('🚀 Installing SiteManager Package...');
        $this->newLine();
        
        // 1. 기존 마이그레이션 백업
        $this->backupExistingMigrations();
        
        // 2. 설정 파일 발행
        $this->publishConfig();
        
        // 3. 마이그레이션 실행
        $this->runMigrations();
        
        // 4. 언어 데이터 복원
        $this->restoreLanguageData();
        
        // 5. 기본 이미지 발행
        $this->publishImages();
        
        // 5. 홈 라우트 설정
        $this->info('🏠 Setting up home route...');
        $this->setupHomeRoute();
        
        // 6. 완료 메시지
        $this->displayCompletionMessage();
        
        return 0;
    }

    /**
     * 기존 Laravel 마이그레이션을 백업합니다.
     */
    protected function backupExistingMigrations(): void
    {
        $migrationPath = database_path('migrations');
        $backupPath = database_path('migrations.backup');
        
        if (!is_dir($migrationPath)) {
            $this->line('📁 No migrations folder found, creating new one...');
            File::makeDirectory($migrationPath, 0755, true);
            return;
        }
        
        $files = File::files($migrationPath);
        
        if (empty($files)) {
            $this->line('📁 No migration files found to backup.');
            return;
        }
        
        if ($this->option('force') || $this->confirm('🗂️  Backup existing migrations to migrations.backup?', true)) {
            // 백업 폴더가 이미 있으면 제거
            if (is_dir($backupPath)) {
                File::deleteDirectory($backupPath);
            }
            
            // 현재 migrations 폴더를 backup으로 이름 변경
            File::move($migrationPath, $backupPath);
            
            // 새로운 migrations 폴더 생성
            File::makeDirectory($migrationPath, 0755, true);
            
            $this->info("   ✅ Backed up " . count($files) . " migration files to migrations.backup/");
        } else {
            $this->line('   ⏭️  Skipped migration backup.');
        }
        
        $this->newLine();
    }

    /**
     * 설정 파일을 발행합니다.
     */
    protected function publishConfig(): void
    {
        $this->info('📦 Publishing configuration files...');
        
        Artisan::call('vendor:publish', [
            '--tag' => 'sitemanager-config',
            '--force' => $this->option('force')
        ]);

        $this->line('   ✅ Configuration files published');

        $this->ensureAuthConfigHasPackageGuards();
        $this->newLine();
    }

    /**
     * Laravel 스켈레톤이 만든 config/auth.php 가 이미 있으면 --force 없는 vendor:publish 가 건너뛴다.
     * 그러면 패키지가 정의하는 customer guard 가 빠져 레이아웃 렌더 시
     * "Auth guard [customer] is not defined" 500 이 난다. 필요할 때만 백업 후 패키지 파일로 교체한다.
     */
    protected function ensureAuthConfigHasPackageGuards(): void
    {
        $hostAuth = config_path('auth.php');
        $packageAuth = __DIR__ . '/../../../config/auth.php';

        if (! File::exists($packageAuth)) {
            return;
        }

        if (File::exists($hostAuth) && str_contains(File::get($hostAuth), "'customer'")) {
            return;
        }

        if (File::exists($hostAuth)) {
            File::copy($hostAuth, config_path('auth.php.backup'));
            $this->line('   ℹ️  Existing config/auth.php lacked the customer guard — backed up to auth.php.backup');
        }

        File::copy($packageAuth, $hostAuth);
        $this->line('   ✅ config/auth.php replaced with SiteManager version (web + customer guards)');
    }

    /**
     * 마이그레이션을 실행합니다.
     */
    protected function runMigrations(): void
    {
        $this->info('🔄 Running migrations...');
        
        // 마이그레이션 실행 (패키지의 마이그레이션은 ServiceProvider에서 이미 로드됨)
        Artisan::call('migrate', [
            '--force' => $this->option('force')
        ]);
        
        $this->line('   ✅ SiteManager migrations executed');
        $this->newLine();
    }

    /**
     * 언어 데이터를 복원합니다.
     */
    protected function restoreLanguageData(): void
    {
        $this->info('🌍 Restoring language data...');
        
        $exitCode = Artisan::call('sitemanager:restore-languages', [
            '--force' => true
        ]);
        
        if ($exitCode === 0) {
            $this->line('   ✅ Language data restored successfully');
        } else {
            $this->warn('   ⚠️  Language data restoration failed');
        }
        
        $this->newLine();
    }

    /**
     * 기본 이미지를 발행합니다.
     */
    protected function publishImages(): void
    {
        $this->info('🖼️  Publishing admin images...');
        
        Artisan::call('vendor:publish', [
            '--tag' => 'sitemanager-images',
            '--force' => $this->option('force')
        ]);
        
        $this->line('   ✅ Admin images published to public/images/');
        $this->newLine();
    }

    /**
     * 완료 메시지를 표시합니다.
     */
    protected function displayCompletionMessage(): void
    {
        $this->newLine();
        $this->info('🎉 SiteManager installation completed!');
        $this->newLine();
        
        $this->line('📋 <comment>What was done:</comment>');
        $this->line('   • Backed up existing Laravel migrations');
        $this->line('   • Published SiteManager configuration files');
        $this->line('   • Ran SiteManager migrations');
        $this->line('   • Restored language data from SQL dump');
        $this->line('   • Published admin images');
        $this->line('   • Backed up original routes and created new web.php');
        $this->newLine();
        
        $this->line('🎯 <comment>Next steps:</comment>');
        $this->line('   1. Create an admin user: <info>php artisan sitemanager:admin</info>');
        $this->line('   2. Visit <info>/</info> to see the main page');
        $this->line('   3. Visit <info>/sitemanager/dashboard</info> to access admin panel');
        $this->newLine();
        
        $this->line('⚙️ <comment>Configuration completed:</comment>');
        $this->line('   • Member model configured for authentication');
        $this->line('   • SiteManager routes loaded');
        $this->line('   • Admin middleware configured');
        $this->newLine();
        
        $this->line('🔧 <comment>Resource management:</comment>');
        $this->line('   • Use: <info>{!! resource(\'sitemanager::css/sitemanager/sitemanager.css\') !!}</info>');
        $this->line('   • Build for production: <info>php artisan resource build</info>');
        $this->line('   • Clear resources: <info>php artisan resource clear</info>');
        $this->newLine();
        
        if (is_dir(database_path('migrations.backup'))) {
            $this->line('💾 <comment>Backup info:</comment>');
            $this->line('   • Original migrations saved in: <info>database/migrations.backup/</info>');
            $this->line('   • Restore if needed: <info>mv database/migrations.backup/* database/migrations/</info>');
            $this->newLine();
        }
        
        if (file_exists(base_path('routes/web.php.backup'))) {
            $this->line('📄 <comment>Routes backup:</comment>');
            $this->line('   • Original routes saved in: <info>routes/web.php.backup</info>');
            $this->line('   • Restore if needed: <info>mv routes/web.php.backup routes/web.php</info>');
            $this->newLine();
        }
    }

    /**
     * 홈 라우트를 설정합니다.
     */
    protected function setupHomeRoute(): void
    {
        $webRoutesPath = base_path('routes/web.php');
        $backupPath = base_path('routes/web.php.backup');
        
        // web.php 파일이 없으면 새로 생성
        if (!file_exists($webRoutesPath)) {
            $this->createNewWebRoutes($webRoutesPath);
            return;
        }
        
        // 기존 web.php 백업
        if ($this->option('force') || $this->confirm('🗂️  Backup existing routes/web.php?', true)) {
            // 기존 백업이 있으면 제거
            if (file_exists($backupPath)) {
                unlink($backupPath);
            }
            
            // 현재 web.php를 백업으로 복사
            copy($webRoutesPath, $backupPath);
            
            // 새로운 web.php 생성
            $this->createNewWebRoutes($webRoutesPath);
            
            $this->line('   ✅ Backed up routes/web.php to web.php.backup');
            $this->line('   ✅ Created new routes/web.php with SiteManager routes');
        } else {
            $this->line('   ⏭️  Skipped routes backup, keeping existing routes/web.php');
        }
    }

    /**
     * 새로운 web.php 파일을 생성합니다.
     */
    protected function createNewWebRoutes(string $webRoutesPath): void
    {
        $webRoutesContent = "<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the \"web\" middleware group. Now create something great!
|
*/

// SiteManager 홈페이지
Route::get('/', function () {
    return view('sitemanager::main');
})->name('home');

// 사용자 정의 라우트를 아래에 추가하세요
// Route::get('/about', [AboutController::class, 'index'])->name('about');
";
        
        file_put_contents($webRoutesPath, $webRoutesContent);
    }
}
