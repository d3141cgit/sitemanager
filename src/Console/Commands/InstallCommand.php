<?php

namespace SiteManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallCommand extends Command
{
    protected $signature = 'sitemanager:install {--force : Force the operation to run when in production}';
    
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
        
        $this->info('✅ SiteManager installation completed!');
        $this->line('');
        $this->line('Next steps:');
        $this->line('1. Configure your settings in config/sitemanager.php');
        $this->line('2. Create an admin user');
        $this->line('3. Visit /admin/dashboard to start managing your site');
        
        return 0;
    }
}
