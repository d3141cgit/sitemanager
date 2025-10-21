<?php

namespace SiteManager\Console\Commands;

use Illuminate\Console\Command;
use SiteManager\Models\Member;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'sitemanager:admin 
                           {--id= : Admin ID (optional, auto-increment if not specified)}
                           {--name= : Admin name}
                           {--username= : Admin username (optional, auto-generated from name if not specified)}
                           {--email= : Admin email}
                           {--password= : Admin password}
                           {--reset-password : Reset password mode - change password for existing user by username}';
    
    protected $description = 'Create an admin user for SiteManager or reset password for existing user';

    public function handle()
    {
        // 비밀번호 재설정 모드
        if ($this->option('reset-password')) {
            return $this->handlePasswordReset();
        }
        
        // 관리자 생성 모드
        return $this->handleAdminCreation();
    }
    
    protected function handlePasswordReset()
    {
        $this->info('Reset Password for SiteManager User...');
        
        $username = $this->option('username') ?: $this->ask('Username');
        $password = $this->option('password') ?: $this->secret('New Password');
        
        if (!$username || !$password) {
            $this->error('Username and password are required!');
            return 1;
        }
        
        // 사용자 찾기
        $user = Member::where('username', $username)->first();
        
        if (!$user) {
            $this->error("User with username '{$username}' not found!");
            return 1;
        }
        
        // 비밀번호 업데이트
        $user->password = Hash::make($password);
        $user->save();
        
        $this->info("✅ Password reset successfully!");
        $this->line("ID: {$user->id}");
        $this->line("Username: {$user->username}");
        $this->line("Name: {$user->name}");
        $this->line("Email: {$user->email}");
        
        return 0;
    }
    
    protected function handleAdminCreation()
    {
        $this->info('Creating SiteManager Admin User...');
        
        $id = $this->option('id');
        $name = $this->option('name') ?: $this->ask('Admin Name');
        $username = $this->option('username') ?: $this->ask('Admin Username', strtolower(str_replace(' ', '', $name)));
        $email = $this->option('email') ?: $this->ask('Admin Email');
        $password = $this->option('password') ?: $this->secret('Admin Password');
        
        if (!$name || !$username || !$email || !$password) {
            $this->error('All fields are required!');
            return 1;
        }
        
        // ID가 지정된 경우 해당 ID가 이미 존재하는지 확인
        if ($id !== null) {
            if (!is_numeric($id) || $id <= 0) {
                $this->error('ID must be a positive integer!');
                return 1;
            }
            
            if (Member::where('id', $id)->exists()) {
                $this->error("A user with ID {$id} already exists!");
                return 1;
            }
        }
        
        // 이미 존재하는 username인지 확인
        if (Member::where('username', $username)->exists()) {
            $this->error('A user with this username already exists!');
            return 1;
        }
        
        // 이미 존재하는 이메일인지 확인
        if (Member::where('email', $email)->exists()) {
            $this->error('A user with this email already exists!');
            return 1;
        }
        
        // 관리자 생성 데이터 준비
        $adminData = [
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'level' => 255, // 최고 관리자 레벨
            'status' => 'active',
            'email_verified_at' => now(),
        ];
        
        // ID가 지정된 경우 추가
        if ($id !== null) {
            $adminData['id'] = $id;
        }
        
        // 관리자 생성
        $admin = Member::create($adminData);
        
        $this->info("✅ Admin user created successfully!");
        $this->line("ID: {$admin->id}");
        $this->line("Name: {$admin->name}");
        $this->line("Username: {$admin->username}");
        $this->line("Email: {$admin->email}");
        $this->line("Level: {$admin->level}");
        $this->line('');
        $this->line('You can now login at /login');
        
        return 0;
    }
}
