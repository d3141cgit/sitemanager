<?php

namespace SiteManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

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
        
        // 4. 언어 데이터 복원
        $this->restoreLanguageData();
        
        // 5. 기본 이미지 발행
        $this->publishImages();
        
        // 6. 홈 라우트 설정
        $this->info('🏠 Setting up home route...');
        $this->setupHomeRoute();
        
        // 7. 완료 메시지
        $this->displayCompletionMessage();
        
        return 0;
    }

    /**
     * 기존 Laravel 마이그레이션을 백업하고 SiteManager 전용으로 정리합니다.
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
        
        $this->warn('📋 Found existing migrations (Laravel defaults like users/cache/jobs not needed for SiteManager)');
        $this->line('   💡 SiteManager uses Member model and file-based cache/queue');
        $this->newLine();
        
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
            $this->line('   📝 Starting fresh with SiteManager-only migrations');
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
     * SiteManager 마이그레이션을 직접 실행합니다.
     */
    protected function publishAndRunMigrations(): void
    {
        $this->info('🔄 Running SiteManager migrations...');
        
        // 패키지 마이그레이션 경로 자동 감지
        $migrationPath = $this->getPackageMigrationPath();
        
        if (!$migrationPath || !is_dir($migrationPath)) {
            $this->warn('   ⚠️  Direct migration execution failed. Trying publish method...');
            $this->publishMigrationsAndRun();
            return;
        }
        
        $this->line('   📁 Migration path found: ' . $migrationPath);
        
        try {
            // vendor 내의 마이그레이션을 직접 실행
            Artisan::call('migrate', [
                '--path' => $migrationPath,
                '--force' => $this->option('force')
            ]);
            
            $this->line('   ✅ SiteManager migrations executed successfully');
        } catch (\Exception $e) {
            $this->warn('   ⚠️  Direct migration failed: ' . $e->getMessage());
            $this->line('   � Trying publish method as fallback...');
            $this->publishMigrationsAndRun();
        }
        
        $this->newLine();
    }

    /**
     * 마이그레이션을 발행한 후 실행합니다 (fallback 방법).
     */
    protected function publishMigrationsAndRun(): void
    {
        try {
            // 마이그레이션 발행
            $this->line('   📦 Publishing migrations to database/migrations...');
            Artisan::call('vendor:publish', [
                '--tag' => 'sitemanager-migrations',
                '--force' => $this->option('force')
            ]);
            
            // 발행된 마이그레이션 실행
            $this->line('   🔄 Running published migrations...');
            Artisan::call('migrate', [
                '--force' => $this->option('force')
            ]);
            
            $this->line('   ✅ Migrations published and executed successfully');
            
        } catch (\Exception $e) {
            $this->error('   ❌ Migration execution failed: ' . $e->getMessage());
            throw new \Exception('Unable to execute SiteManager migrations. Installation aborted.');
        }
    }

    /**
     * 패키지 마이그레이션 경로를 자동으로 감지합니다.
     */
    protected function getPackageMigrationPath(): ?string
    {
        $possiblePaths = [];
        
        // 1. 현재 파일 기준으로 상대 경로 계산 (개발환경)
        $relativePath = __DIR__ . '/../../../database/migrations';
        $possiblePaths['relative'] = $relativePath;
        if (is_dir($relativePath)) {
            $realPath = realpath($relativePath);
            $this->line('   ✅ Found migration path (relative): ' . $realPath);
            return $realPath;
        }
        
        // 2. Composer vendor 경로 (설치된 패키지)
        $vendorPath = base_path('vendor/d3141cgit/sitemanager/database/migrations');
        $possiblePaths['vendor'] = $vendorPath;
        if (is_dir($vendorPath)) {
            $this->line('   ✅ Found migration path (vendor): ' . $vendorPath);
            return $vendorPath;
        }
        
        // 3. 패키지 디스커버리를 통한 경로 찾기
        try {
            $reflection = new \ReflectionClass(\SiteManager\SiteManagerServiceProvider::class);
            $packagePath = dirname($reflection->getFileName());
            $migrationPath = $packagePath . '/../database/migrations';
            $possiblePaths['reflection'] = $migrationPath;
            
            if (is_dir($migrationPath)) {
                $realPath = realpath($migrationPath);
                $this->line('   ✅ Found migration path (reflection): ' . $realPath);
                return $realPath;
            }
        } catch (\Exception $e) {
            $possiblePaths['reflection_error'] = $e->getMessage();
        }
        
        // 4. ServiceProvider에서 마이그레이션 경로 확인
        try {
            $serviceProvider = app(\SiteManager\SiteManagerServiceProvider::class);
            // SiteManagerServiceProvider에서 loadMigrationsFrom 호출하는 경로 확인
            $providerPath = (new \ReflectionClass($serviceProvider))->getFileName();
            $packageRoot = dirname(dirname(dirname($providerPath)));
            $migrationPath = $packageRoot . '/database/migrations';
            $possiblePaths['service_provider'] = $migrationPath;
            
            if (is_dir($migrationPath)) {
                $realPath = realpath($migrationPath);
                $this->line('   ✅ Found migration path (service provider): ' . $realPath);
                return $realPath;
            }
        } catch (\Exception $e) {
            $possiblePaths['service_provider_error'] = $e->getMessage();
        }
        
        // 5. 모든 vendor 디렉토리 스캔
        $vendorDir = base_path('vendor');
        if (is_dir($vendorDir)) {
            $searchPaths = [
                $vendorDir . '/d3141cgit/sitemanager/database/migrations',
                $vendorDir . '/*/sitemanager/database/migrations'
            ];
            
            foreach ($searchPaths as $searchPath) {
                $possiblePaths['vendor_scan_' . basename(dirname($searchPath))] = $searchPath;
                if (is_dir($searchPath)) {
                    $realPath = realpath($searchPath);
                    $this->line('   ✅ Found migration path (vendor scan): ' . $realPath);
                    return $realPath;
                }
            }
        }
        
        // 디버깅 정보 출력
        $this->warn('   ❌ No migration paths found. Searched locations:');
        foreach ($possiblePaths as $type => $path) {
            $status = is_string($path) && is_dir($path) ? '✅' : '❌';
            $this->line("      {$status} {$type}: {$path}");
        }
        
        return null;
    }

    /**
     * 언어 데이터를 복원합니다.
     */
    protected function restoreLanguageData(): void
    {
        $this->info('🌍 Restoring language data...');
        
        // 테이블 존재 여부 확인
        try {
            if (!$this->checkTablesExist()) {
                $this->warn('   ⚠️  Required tables not found. Skipping language data restoration.');
                $this->line('   💡 Run "php artisan sitemanager:restore-languages" after ensuring tables exist.');
                $this->newLine();
                return;
            }
        } catch (\Exception $e) {
            $this->warn('   ⚠️  Cannot verify table existence: ' . $e->getMessage());
            $this->line('   💡 Attempting language restoration anyway...');
        }
        
        try {
            $exitCode = Artisan::call('sitemanager:restore-languages', [
                '--force' => true
            ]);
            
            if ($exitCode === 0) {
                $this->line('   ✅ Language data restored successfully');
            } else {
                $this->warn('   ⚠️  Language data restoration failed (exit code: ' . $exitCode . ')');
                $this->line('   💡 You can retry with: php artisan sitemanager:restore-languages');
            }
        } catch (\Exception $e) {
            $this->warn('   ⚠️  Language data restoration failed: ' . $e->getMessage());
            $this->line('   💡 You can retry with: php artisan sitemanager:restore-languages');
        }
        
        $this->newLine();
    }

    /**
     * 필요한 테이블들이 존재하는지 확인합니다.
     */
    protected function checkTablesExist(): bool
    {
        $requiredTables = ['languages', 'menus', 'members'];
        
        foreach ($requiredTables as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                $this->line("   ❌ Table '{$table}' not found");
                return false;
            }
        }
        
        $this->line('   ✅ All required tables exist');
        return true;
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
        $this->line('   • Backed up existing Laravel migrations (users/cache/jobs not needed)');
        $this->line('   • Published SiteManager configuration files');
        $this->line('   • Executed SiteManager migrations from vendor directory');
        $this->line('   • Verified table creation before data restoration');
        $this->line('   • Restored language data from SQL dump');
        $this->line('   • Published SiteManager images');
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
