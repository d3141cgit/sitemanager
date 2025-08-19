<?php

namespace SiteManager\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use SiteManager\Services\MemberService;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    protected $memberService;
    
    public function __construct(MemberService $memberService)
    {
        $this->memberService = $memberService;
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
        
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            if ($this->memberService->attemptLogin($request->username, $request->password)) {
                // Get the intended URL from session or fallback to default
                $intendedUrl = session('url.intended', '/');
                
                // Clear the intended URL from session
                session()->forget('url.intended');
                
                // Validate the intended URL for security
                if ($this->isValidRedirectUrl($intendedUrl)) {
                    return redirect($intendedUrl);
                }
                
                return redirect('/');
            }
        } catch (\Exception $e) {
            return back()->withErrors([
                'username' => $e->getMessage(),
            ])->withInput($request->only('username'));
        }

        return back()->withErrors([
            'username' => '아이디 또는 비밀번호가 일치하지 않습니다.',
        ])->withInput($request->only('username'));
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
