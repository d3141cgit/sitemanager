<?php

namespace SiteManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallCommand extends Command
{
    protected $signature = 'sitemanager:install 
                            {--force : Force the operation to run when in production}
                            {--with-starter : Publish starter templates for front-end development}';
    
    protected $description = 'Install SiteManager package with all required setup';

    public function handle()
    {
        $this->info('Installing SiteManager Package...');
        
        // 설정 파일 발행
        $this->info('Publishing configuration...');
        Artisan::call('vendor:publish', [
            '--tag' => 'sitemanager-config',
            '--force' => $this->option('force')
        ]);
        
        // 마이그레이션 실행
        $this->info('Running migrations...');
        Artisan::call('migrate', [
            '--force' => $this->option('force')
        ]);
        
        // 에셋 발행
        $this->info('Publishing assets...');
        Artisan::call('vendor:publish', [
            '--tag' => 'sitemanager-assets',
            '--force' => $this->option('force')
        ]);
        
        // 기본 이미지 발행 (Admin용)
        $this->info('Publishing admin images...');
        Artisan::call('vendor:publish', [
            '--tag' => 'sitemanager-images',
            '--force' => $this->option('force')
        ]);
        
        // 스타터 템플릿 발행 (옵션)
        if ($this->option('with-starter')) {
            $this->info('Publishing starter templates...');
            Artisan::call('vendor:publish', [
                '--tag' => 'sitemanager-starter',
                '--force' => $this->option('force')
            ]);
            $this->line('📁 Starter templates published to resources/views/');
        }
        
        // 홈 라우트 설정
        $this->info('Setting up home route...');
        $this->setupHomeRoute();
        
        $this->info('✅ SiteManager installation completed!');
        $this->line('');
        $this->line('🎯 Next steps:');
        $this->line('1. Create an admin user: php artisan sitemanager:admin');
        $this->line('2. Visit / to see the main page');
        $this->line('3. Visit /admin/dashboard to access admin panel');
        if ($this->option('with-starter')) {
            $this->line('4. Customize views in resources/views/');
            $this->line('5. Update routes in routes/web.php');
        } else {
            $this->line('4. Build your frontend with your preferred tools');
        }
        $this->line('');
        $this->line('� Using package resources:');
        $this->line('• CSS: {!! resource(\'sitemanager::css/admin/admin.css\') !!}');
        $this->line('• JS:  {!! resource(\'sitemanager::js/admin/admin.js\') !!}');
        $this->line('• Build for production: php artisan resource build');
        $this->line('');
        $this->line('�📚 Documentation: Check README.md for more details');
        $this->line('🐛 Issues: Report at your repository issue tracker');
        
        return 0;
    }
    
    /**
     * 홈 라우트를 설정합니다.
     */
    protected function setupHomeRoute(): void
    {
        $webRoutesPath = base_path('routes/web.php');
        
        if (!file_exists($webRoutesPath)) {
            $this->warn('routes/web.php file not found, skipping home route setup.');
            return;
        }
        
        $content = file_get_contents($webRoutesPath);
        
        // Laravel 기본 welcome 라우트를 sitemanager::main으로 교체
        if (preg_match('/return\s+view\(\s*[\'"]welcome[\'"]\s*\)/', $content)) {
            $content = preg_replace(
                '/return\s+view\(\s*[\'"]welcome[\'"]\s*\)/',
                "return view('sitemanager::main')",
                $content
            );
            file_put_contents($webRoutesPath, $content);
            $this->line('🏠 Laravel welcome route updated to use sitemanager::main view');
            return;
        }
        
        // 이미 sitemanager::main을 사용하고 있는지 확인
        if (preg_match('/sitemanager::main/', $content)) {
            $this->line('🏠 Home route already uses sitemanager::main, skipping...');
            return;
        }
        
        // 홈 라우트가 없으면 새로 추가
        if (!preg_match('/Route::get\(\s*[\'"]\/[\'"]/', $content)) {
            $homeRoute = "
Route::get('/', function () {
    return view('sitemanager::main');
})->name('home');";
            
            // <?php 태그 다음에 use 구문이 있는지 확인
            if (preg_match('/<\?php\s*\n\s*use\s+/', $content)) {
                // use 구문 다음에 추가
                $content = preg_replace(
                    '/(use\s+[^;]+;\s*\n)/',
                    "$1$homeRoute\n",
                    $content,
                    1
                );
            } else {
                // use 구문이 없으면 <?php 다음에 추가
                $content = preg_replace(
                    '/(<\?php\s*\n)/',
                    "$1\nuse Illuminate\Support\Facades\Route;$homeRoute\n",
                    $content,
                    1
                );
            }
            
            file_put_contents($webRoutesPath, $content);
            $this->line('🏠 Home route added to use sitemanager::main view');
        } else {
            $this->line('🏠 Home route already exists with different view, skipping...');
        }
    }
}
