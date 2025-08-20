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
        
        // CSS/JS 리소스 발행
        $this->info('Publishing CSS/JS resources...');
        Artisan::call('vendor:publish', [
            '--tag' => 'sitemanager-resources',
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
        
        $this->info('✅ SiteManager installation completed!');
        $this->line('');
        $this->line('🎯 Next steps:');
        $this->line('1. Create an admin user: php artisan sitemanager:admin');
        $this->line('2. Visit /admin/dashboard to access admin panel');
        if ($this->option('with-starter')) {
            $this->line('3. Customize views in resources/views/');
            $this->line('4. Update routes in routes/web.php');
        } else {
            $this->line('3. Build your frontend with your preferred tools');
        }
        $this->line('');
        $this->line('📚 Documentation: Check README.md for more details');
        $this->line('🐛 Issues: Report at your repository issue tracker');
        
        return 0;
    }
}
