<?php

namespace SiteManager\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use SiteManager\Models\EdmMember;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class CustomerLoginController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
    protected $redirectTo = '/my';

    /**
     * Select view with priority: project views > package views
     */
    private function selectView($viewName)
    {
        $filePath = str_replace('.', '/', $viewName);
        $projectViewPath = resource_path("views/{$filePath}.blade.php");
        
        if (file_exists($projectViewPath)) {
            return $viewName;
        }
        
        return "sitemanager::{$viewName}";
    }

    /**
     * 고객용 로그인 폼 표시
     */
    public function showLoginForm()
    {
        return view($this->selectView('auth.customer-login'));
    }

    /**
     * 고객 로그인 처리 (edmuhak2 방식)
     */
    public function login(Request $request)
    {
        $this->validateLogin($request);

        $loginId = $request->input('mm_id');
        $password = $request->input('password');

        // edmuhak2 방식으로 직접 인증
        $member = EdmMember::authenticateUser($loginId, $password);

        if ($member) {
            // 직접 로그인 처리 (attempt() 사용 안함)
            Auth::guard('customer')->login($member, $request->filled('remember'));
            
            // 마지막 로그인 시간 업데이트
            $member->setLastLoginTime();
            
            $request->session()->regenerate();
            
            return redirect()->intended($this->redirectTo);
        }

        throw ValidationException::withMessages([
            'mm_id' => ['입력하신 정보와 일치하는 회원 정보가 없습니다.'],
        ]);
    }

    /**
     * 로그인 폼 유효성 검사
     */
    protected function validateLogin(Request $request)
    {
        $request->validate([
            'mm_id' => 'required|string',
            'password' => 'required|string',
        ], [
            'mm_id.required' => '아이디를 입력해주세요.',
            'password.required' => '비밀번호를 입력해주세요.',
        ]);
    }

    /**
     * 고객 로그아웃
     */
    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}