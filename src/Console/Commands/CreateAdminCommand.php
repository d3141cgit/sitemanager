<?php

namespace SiteManager\Console\Commands;

use Illuminate\Console\Command;
use SiteManager\Models\Member;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'sitemanager:admin 
                           {--name= : Admin name}
                           {--email= : Admin email}
                           {--password= : Admin password}';
    
    protected $description = 'Create an admin user for SiteManager';

    public function handle()
    {
        $this->info('Creating SiteManager Admin User...');
        
        $name = $this->option('name') ?: $this->ask('Admin Name');
        $email = $this->option('email') ?: $this->ask('Admin Email');
        $password = $this->option('password') ?: $this->secret('Admin Password');
        
        if (!$name || !$email || !$password) {
            $this->error('All fields are required!');
            return 1;
        }
        
        // 이미 존재하는 이메일인지 확인
        if (Member::where('email', $email)->exists()) {
            $this->error('A user with this email already exists!');
            return 1;
        }
        
        // 관리자 생성
        $admin = Member::create([
            'name' => $name,
            'username' => strtolower(str_replace(' ', '', $name)), // 이름에서 공백 제거한 소문자
            'email' => $email,
            'password' => Hash::make($password),
            'level' => config('sitemanager.permissions.admin_level', 200),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        
        $this->info("✅ Admin user created successfully!");
        $this->line("Name: {$admin->name}");
        $this->line("Email: {$admin->email}");
        $this->line("Level: {$admin->level}");
        $this->line('');
        $this->line('You can now login at /login');
        
        return 0;
    }
}
