<?php

namespace SiteManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SiteManagerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 로그인하지 않은 경우 로그인 페이지로 리다이렉트
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', '로그인이 필요합니다.');
        }

        $user = Auth::user();
        
        // 사이트매니저 권한 체크 (Member 모델의 isAdmin() 메서드 사용)
        if (!$user instanceof \SiteManager\Models\Member || !$user->isAdmin()) {
            return redirect('/')
                ->with('error', '사이트매니저 권한이 필요합니다.');
        }

        return $next($request);
    }
}
