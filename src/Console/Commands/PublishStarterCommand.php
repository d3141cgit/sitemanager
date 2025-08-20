<?php

namespace SiteManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PublishStarterCommand extends Command
{
    protected $signature = 'sitemanager:publish-starter 
                            {--force : Force the operation to run when in production}
                            {--auth : Also publish authentication views}
                            {--routes : Also create basic routes}';
    
    protected $description = 'Publish SiteManager starter templates for front-end development';

    public function handle()
    {
        $this->info('🚀 Publishing SiteManager Starter Templates...');
        
        // 기본 스타터 템플릿 발행
        $this->info('Publishing layout and basic templates...');
        Artisan::call('vendor:publish', [
            '--tag' => 'sitemanager-starter',
            '--force' => $this->option('force')
        ]);
        
        // 인증 관련 추가 설정
        if ($this->option('auth')) {
            $this->info('Setting up authentication...');
            
            // Laravel 기본 인증 스캐폴딩이 있다면
            if ($this->confirm('Do you want to install Laravel UI for authentication?')) {
                $this->call('ui', ['type' => 'bootstrap', '--auth' => true]);
            }
        }
        
        // 기본 라우트 생성
        if ($this->option('routes')) {
            $this->info('Creating basic routes...');
            $this->createBasicRoutes();
        }
        
        $this->info('✅ Starter templates published successfully!');
        $this->line('');
        $this->line('📁 Published files:');
        $this->line('   • resources/views/layouts/app.blade.php');
        $this->line('   • resources/views/welcome.blade.php (from main.blade.php)');
        $this->line('   • resources/views/board/');
        $this->line('   • resources/views/auth/');
        $this->line('   • resources/views/user/');
        $this->line('   • resources/views/components/');
        $this->line('');
        $this->line('🔧 Next steps:');
        $this->line('1. Customize the views in resources/views/');
        $this->line('2. Update your routes in routes/web.php');
        $this->line('3. Modify the layout to match your design');
        $this->line('4. Configure authentication if needed');
    }
    
    private function createBasicRoutes()
    {
        $routeContent = "
// SiteManager Basic Routes
Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/login', function () {
    return view('auth.login');
})->name('login');

// Add your custom routes here...
";
        
        $routeFile = base_path('routes/web.php');
        $currentContent = file_get_contents($routeFile);
        
        if (strpos($currentContent, 'SiteManager Basic Routes') === false) {
            file_put_contents($routeFile, $currentContent . $routeContent);
            $this->line('✅ Basic routes added to routes/web.php');
        } else {
            $this->line('ℹ️  Basic routes already exist in routes/web.php');
        }
    }
}
