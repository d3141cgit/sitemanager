<?php

namespace SiteManager\Http\Controllers\Auth;

use SiteManager\Http\Controllers\Controller;
use SiteManager\Services\MemberService;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    protected $memberService;
    
    public function __construct(MemberService $memberService)
    {
        $this->memberService = $memberService;
    }
    
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
    
    public function showLoginForm(Request $request)
    {
        // Store the intended URL if it exists and is not the login page itself
        if ($request->has('redirect')) {
            session(['url.intended' => $request->get('redirect')]);
        } elseif (!$request->session()->has('url.intended')) {
            // Only store referer if no intended URL is already set
            $referer = $request->header('referer');
            if ($referer && !str_contains($referer, '/login') && !str_contains($referer, '/logout')) {
                session(['url.intended' => $referer]);
            }
        }
        
        return view($this->selectView('auth.login'));
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            // 1단계: Member 테이블 검사 (관리자/스태프)
            if ($this->memberService->attemptLogin($request->username, $request->password)) {
                return $this->handleSuccessfulLogin($request, 'admin');
            }

            // 2단계: Fallback 인증 (프로젝트에서 override 가능)
            if (config('sitemanager.auth.enable_edm_member_auth', false)) {
                $fallbackResult = $this->attemptFallbackLogin($request);
                if ($fallbackResult) {
                    return $fallbackResult;
                }
            }

        } catch (\Exception $e) {
            return back()->withErrors([
                'username' => $e->getMessage(),
            ])->withInput($request->only('username'));
        }

        return back()->withErrors([
            'username' => 'The username or password is incorrect.',
        ])->withInput($request->only('username'));
    }

    /**
     * Fallback 인증 로직 (프로젝트에서 override)
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|null
     */
    protected function attemptFallbackLogin(Request $request)
    {
        // 기본적으로는 아무것도 하지 않음
        // 프로젝트에서 이 메서드를 override하여 EdmMember 등 추가 인증 구현
        return null;
    }

    /**
     * 성공적인 로그인 후 처리
     * 
     * @param Request $request
     * @param string $userType
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function handleSuccessfulLogin(Request $request, string $userType = 'admin')
    {
        $intendedUrl = session('url.intended', '/');
        session()->forget('url.intended');
        
        if ($this->isValidRedirectUrl($intendedUrl)) {
            return redirect($intendedUrl);
        }
        
        return redirect('/');
    }

    public function logout()
    {
        $this->memberService->logout();
        return redirect('/');
    }
    
    /**
     * Validate if the redirect URL is safe
     */
    private function isValidRedirectUrl($url)
    {
        // Check if URL is empty or null
        if (empty($url)) {
            return false;
        }
        
        // Parse the URL
        $parsedUrl = parse_url($url);
        
        // If it's a relative URL (no host), it's generally safe
        if (!isset($parsedUrl['host'])) {
            // But make sure it doesn't start with double slashes (protocol-relative URLs)
            return !str_starts_with($url, '//');
        }
        
        // If it has a host, check if it matches our app URL
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $currentHost = request()->getHost();
        
        return in_array($parsedUrl['host'], [$appHost, $currentHost]);
    }
}
