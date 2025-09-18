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
        
        // 0. 설치 상태 확인
        if ($this->isAlreadyInstalled()) {
            $this->warn('⚠️  SiteManager appears to be already installed.');
            
            if (!$this->option('force') && !$this->confirm('🔄 Proceed with reinstallation?', false)) {
                $this->line('❌ Installation cancelled.');
                return 1;
            }
            
            $this->line('🔄 Proceeding with reinstallation...');
        }
        
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
        
        // 7. 설치 후 정리
        $this->cleanupAfterInstallation();
        
        // 8. 완료 메시지
        $this->displayCompletionMessage();
        
        return 0;
    }

    /**
     * SiteManager가 이미 설치되었는지 확인합니다.
     */
    protected function isAlreadyInstalled(): bool
    {
        try {
            // 주요 테이블들 확인
            $requiredTables = ['menus', 'members', 'languages'];
            $existingTables = [];
            
            foreach ($requiredTables as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    $existingTables[] = $table;
                }
            }
            
            // 모든 주요 테이블이 존재하면 설치됨으로 간주
            return count($existingTables) === count($requiredTables);
            
        } catch (\Exception $e) {
            // DB 연결 문제 등이 있으면 설치되지 않은 것으로 간주
            return false;
        }
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
        
        // Laravel이 절대 경로를 인식하지 못하는 문제가 있으므로 
        // 마이그레이션을 먼저 publish한 후 실행하는 방식으로 변경
        $this->warn('   💡 Using publish method for better compatibility...');
        $this->publishMigrationsAndRun();
        
        try {
            // 마이그레이션 파일 목록 확인
            $migrationFiles = glob($migrationPath . '/*.php');
            $this->line('   📄 Found ' . count($migrationFiles) . ' migration files');
            
            if (empty($migrationFiles)) {
                throw new \Exception('No migration files found in the path');
            }
            
            // 마이그레이션 파일 이름들 표시
            $this->line('   📋 Migration files:');
            foreach ($migrationFiles as $file) {
                $filename = basename($file);
                $this->line('      • ' . $filename);
            }
            
            // 마이그레이션 상태 확인
            $this->line('   🔍 Checking migration status...');
            $migrationCount = $this->getMigrationRecordCount();
            
            if ($migrationCount > 0) {
                $this->warn('   ⚠️  Found ' . $migrationCount . ' migration records. Clearing for fresh installation...');
                
                // migrations 테이블 비우기
                DB::table('migrations')->truncate();
                $this->line('   🗑️  Cleared migration records');
            } else {
                $this->line('   ✅ Migration table is empty, ready for fresh installation');
            }
            
            // Laravel 마이그레이션 상태 확인
            $this->line('   🔍 Checking Laravel migration status...');
            $statusExitCode = Artisan::call('migrate:status', [
                '--path' => $migrationPath
            ]);
            $statusOutput = Artisan::output();
            $this->line('   📝 Migration status output:');
            foreach (explode("\n", trim($statusOutput)) as $line) {
                if (!empty(trim($line))) {
                    $this->line('      ' . $line);
                }
            }
            
            // vendor 내의 마이그레이션을 직접 실행
            $this->line('   🔄 Executing migrations...');
            $exitCode = Artisan::call('migrate', [
                '--path' => $migrationPath,
                '--force' => $this->option('force'),
                '--verbose' => true
            ]);
            
            // 마이그레이션 결과 확인
            $output = Artisan::output();
            if ($exitCode !== 0) {
                throw new \Exception('Migration command failed with exit code: ' . $exitCode);
            }
            
            // "Nothing to migrate" 체크
            if (strpos($output, 'Nothing to migrate') !== false) {
                $this->warn('   ⚠️  Laravel says "Nothing to migrate" but checking table status...');
                
                // migrations 테이블 레코드 확인
                $migrationCount = $this->getMigrationRecordCount();
                $this->line('   📊 Migration records in database: ' . $migrationCount);
                
                // 실제 테이블 존재 확인
                $createdTables = $this->verifyTablesCreated();
                $this->line('   📊 Created tables found: ' . count($createdTables));
                
                if (count($createdTables) <= 1 || $migrationCount === 0) { // migrations 테이블만 있거나 레코드가 없는 경우
                    $this->line('   💡 Tables missing despite "Nothing to migrate". Forcing execution...');
                    
                    // migrations 테이블 완전 초기화
                    if ($migrationCount > 0) {
                        DB::table('migrations')->truncate();
                        $this->line('   🗑️  Cleared migration records');
                    }
                    
                    // --step 옵션으로 강제 실행
                    $this->line('   🔄 Forcing migration with --step option...');
                    $exitCode = Artisan::call('migrate', [
                        '--path' => $migrationPath,
                        '--force' => true,
                        '--step' => true,
                        '--verbose' => true
                    ]);
                    
                    $output = Artisan::output();
                    if ($exitCode !== 0) {
                        throw new \Exception('Forced migration with --step failed with exit code: ' . $exitCode);
                    }
                    
                    $this->line('   📝 Forced migration output:');
                    foreach (explode("\n", trim($output)) as $line) {
                        if (!empty(trim($line))) {
                            $this->line('      ' . $line);
                        }
                    }
                }
            }
            
            // 실제 테이블 생성 확인
            $this->line('   🔍 Verifying table creation...');
            $createdTables = $this->verifyTablesCreated();
            
            if (count($createdTables) > 0) {
                $this->line('   ✅ SiteManager migrations executed successfully');
                $this->line('   📊 Created tables: ' . implode(', ', $createdTables));
            } else {
                throw new \Exception('No tables were created despite successful migration command');
            }
            
            // 마이그레이션 출력 표시 (디버깅용)
            if (!empty(trim($output))) {
                $this->line('   📝 Migration output:');
                foreach (explode("\n", trim($output)) as $line) {
                    if (!empty(trim($line))) {
                        $this->line('      ' . $line);
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->warn('   ⚠️  Direct migration failed: ' . $e->getMessage());
            $this->line('   💡 Trying publish method as fallback...');
            $this->publishMigrationsAndRun();
        }
        
        $this->newLine();
    }

    /**
     * 실제로 테이블이 생성되었는지 확인합니다.
     */
    protected function verifyTablesCreated(): array
    {
        $expectedTables = [
            'migrations', 'languages', 'members', 'menus', 'boards', 'posts', 
            'groups', 'group_members', 'menu_permissions', 'site_configs'
        ];
        
        $createdTables = [];
        foreach ($expectedTables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $createdTables[] = $table;
            }
        }
        
        return $createdTables;
    }

    /**
     * migrations 테이블의 레코드 수를 확인합니다.
     */
    protected function getMigrationRecordCount(): int
    {
        try {
            // migrations 테이블이 존재하는지 확인
            if (!DB::getSchemaBuilder()->hasTable('migrations')) {
                return 0;
            }
            
            return DB::table('migrations')->count();
            
        } catch (\Exception $e) {
            $this->line('   ⚠️  Cannot check migration count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 마이그레이션 상태를 확인합니다.
     */
    protected function checkMigrationStatus(string $migrationPath): array
    {
        try {
            // migrations 테이블이 존재하는지 확인
            if (!DB::getSchemaBuilder()->hasTable('migrations')) {
                return [];
            }
            
            // 마이그레이션 파일 목록 가져오기
            $migrationFiles = glob($migrationPath . '/*.php');
            $migratedFiles = [];
            
            foreach ($migrationFiles as $file) {
                $filename = basename($file, '.php');
                
                // migrations 테이블에서 해당 마이그레이션이 실행되었는지 확인
                $exists = DB::table('migrations')
                    ->where('migration', $filename)
                    ->exists();
                    
                if ($exists) {
                    $migratedFiles[] = $filename;
                }
            }
            
            return $migratedFiles;
            
        } catch (\Exception $e) {
            $this->line('   ⚠️  Cannot check migration status: ' . $e->getMessage());
            return [];
        }
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
            $exitCode = Artisan::call('migrate', [
                '--force' => $this->option('force'),
                '--verbose' => true
            ]);
            
            $output = Artisan::output();
            if ($exitCode !== 0) {
                throw new \Exception('Migration command failed with exit code: ' . $exitCode);
            }
            
            // "Nothing to migrate" 체크 (published 버전)
            if (strpos($output, 'Nothing to migrate') !== false) {
                $this->warn('   ⚠️  Published migrations also show "Nothing to migrate".');
                
                // 발행된 마이그레이션 파일들 확인
                $publishedMigrations = glob(database_path('migrations/????_??_??_??????_create_*_table.php'));
                $this->line('   📋 Published migration files: ' . count($publishedMigrations));
                
                if (count($publishedMigrations) > 0) {
                    foreach ($publishedMigrations as $file) {
                        $this->line('      • ' . basename($file));
                    }
                    
                    // migrations 테이블 초기화
                    $migrationCount = $this->getMigrationRecordCount();
                    if ($migrationCount >= 0) {
                        DB::table('migrations')->truncate();
                        $this->line('   🗑️  Cleared migration records');
                    }
                    
                    // --seed 옵션 없이 강제 실행
                    $this->line('   🔄 Forcing published migration without any cache...');
                    
                    // config cache 클리어
                    Artisan::call('config:clear');
                    
                    $exitCode = Artisan::call('migrate', [
                        '--force' => true,
                        '--verbose' => true
                    ]);
                    
                    $output = Artisan::output();
                    if ($exitCode !== 0) {
                        throw new \Exception('Forced published migration failed with exit code: ' . $exitCode);
                    }
                } else {
                    throw new \Exception('No migration files were published to database/migrations');
                }
            }
            
            // 실제 테이블 생성 확인
            $this->line('   🔍 Verifying table creation...');
            $createdTables = $this->verifyTablesCreated();
            
            if (count($createdTables) > 0) {
                $this->line('   ✅ Migrations published and executed successfully');
                $this->line('   📊 Created tables: ' . implode(', ', $createdTables));
            } else {
                throw new \Exception('No tables were created despite successful migration command');
            }
            
            // 마이그레이션 출력 표시 (디버깅용)
            if (!empty(trim($output))) {
                $this->line('   📝 Migration output:');
                foreach (explode("\n", trim($output)) as $line) {
                    if (!empty(trim($line))) {
                        $this->line('      ' . $line);
                    }
                }
            }
            
        } catch (\Exception $e) {
            // 테이블이 이미 존재하는 경우 처리
            if (strpos($e->getMessage(), 'Base table or view already exists') !== false) {
                $this->warn('   ⚠️  Tables already exist. Verifying installation...');
                
                $createdTables = $this->verifyTablesCreated();
                if (count($createdTables) >= 8) { // 주요 테이블들이 존재
                    $this->line('   ✅ All required tables already exist');
                    $this->line('   📊 Existing tables: ' . implode(', ', $createdTables));
                    return; // 성공적으로 처리됨
                }
            }
            
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
        $missingTables = [];
        $existingTables = [];
        
        foreach ($requiredTables as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                $missingTables[] = $table;
                $this->line("   ❌ Table '{$table}' not found");
            } else {
                $existingTables[] = $table;
                $this->line("   ✅ Table '{$table}' exists");
            }
        }
        
        if (!empty($missingTables)) {
            $this->warn('   ⚠️  Missing tables: ' . implode(', ', $missingTables));
            return false;
        }
        
        $this->line('   ✅ All required tables exist: ' . implode(', ', $existingTables));
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

    /**
     * 설치 완료 후 임시 파일들을 정리합니다.
     */
    protected function cleanupAfterInstallation(): void
    {
        $this->info('🧹 Cleaning up after installation...');
        
        $migrationPath = database_path('migrations');
        
        if (!is_dir($migrationPath)) {
            $this->line('   ✅ No migration folder to clean up.');
            $this->newLine();
            return;
        }
        
        // SiteManager 마이그레이션 파일들 식별 (2025_08_* 또는 2025_09_* 패턴)
        $siteManagerMigrations = glob($migrationPath . '/2025_0[89]_*_create_*_table.php');
        
        if (empty($siteManagerMigrations)) {
            $this->line('   ✅ No SiteManager migration files to clean up.');
            $this->newLine();
            return;
        }
        
        $this->line('   📋 Found ' . count($siteManagerMigrations) . ' SiteManager migration files to clean up:');
        foreach ($siteManagerMigrations as $file) {
            $this->line('      • ' . basename($file));
        }
        
        if ($this->option('force') || $this->confirm('🗑️  Remove published SiteManager migration files?', true)) {
            $deletedCount = 0;
            
            foreach ($siteManagerMigrations as $file) {
                if (File::delete($file)) {
                    $deletedCount++;
                }
            }
            
            $this->line("   ✅ Deleted {$deletedCount} SiteManager migration files");
            $this->line('   💡 Migration files removed, but database tables remain intact');
            
            // migrations 폴더가 비어있다면 새로 생성
            $remainingFiles = File::files($migrationPath);
            if (empty($remainingFiles)) {
                File::deleteDirectory($migrationPath);
                File::makeDirectory($migrationPath, 0755, true);
                $this->line('   🔄 Recreated empty migrations folder');
            }
        } else {
            $this->line('   ⏭️  Skipped migration cleanup. Files remain in database/migrations/');
        }
        
        $this->newLine();
    }
}
