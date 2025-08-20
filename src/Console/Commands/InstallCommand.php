<?php

namespace SiteManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'sitemanager:install 
                            {--force : Force the operation to run when in production}
                            {--with-starter : Publish starter templates for front-end development}';
    
    protected $description = 'Install SiteManager package with all required setup';

    public function handle()
    {
        $this->info('🚀 Installing SiteManager Package...');
        $this->newLine();
        
        // 1. 기존 마이그레이션 백업
        $this->backupExistingMigrations();
        
        // 2. 설정 파일 발행
        $this->publishConfig();
        
        // 3. 마이그레이션 발행 및 실행
        $this->publishAndRunMigrations();
        
        // 4. 기본 이미지 발행
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
        $this->newLine();
    }

    /**
     * 마이그레이션을 발행하고 실행합니다.
     */
    protected function publishAndRunMigrations(): void
    {
        $this->info('🔄 Publishing and running migrations...');
        
        // 마이그레이션 발행
        Artisan::call('vendor:publish', [
            '--tag' => 'sitemanager-migrations',
            '--force' => $this->option('force')
        ]);
        
        // 마이그레이션 실행
        Artisan::call('migrate', [
            '--force' => $this->option('force')
        ]);
        
        $this->line('   ✅ SiteManager migrations published and executed');
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
        $this->line('   • Published and ran SiteManager migrations');
        $this->line('   • Published admin images');
        $this->line('   • Backed up original routes and created new web.php');
        $this->newLine();
        
        $this->line('🎯 <comment>Next steps:</comment>');
        $this->line('   1. Create an admin user: <info>php artisan sitemanager:admin</info>');
        $this->line('   2. Visit <info>/</info> to see the main page');
        $this->line('   3. Visit <info>/admin/dashboard</info> to access admin panel');
        $this->newLine();
        
        $this->line('⚙️ <comment>Configuration completed:</comment>');
        $this->line('   • Member model configured for authentication');
        $this->line('   • SiteManager routes loaded');
        $this->line('   • Admin middleware configured');
        $this->newLine();
        
        $this->line('🔧 <comment>Resource management:</comment>');
        $this->line('   • Use: <info>{!! resource(\'sitemanager::css/admin/admin.css\') !!}</info>');
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
