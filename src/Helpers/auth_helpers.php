<?php

use Illuminate\Support\Facades\Auth;

if (!function_exists('current_user')) {
    /**
     * 현재 로그인한 사용자를 반환 (멀티가드 지원)
     * 우선순위: web 가드 (Member) -> customer 가드 (EdmMember)
     */
    function current_user()
    {
        // 관리자 가드 (web) 우선 확인
        if (Auth::guard('web')->check()) {
            return Auth::guard('web')->user();
        }
        
        // 고객 가드 (customer) 확인
        if (Auth::guard('customer')->check()) {
            return Auth::guard('customer')->user();
        }
        
        return null;
    }
}

if (!function_exists('current_user_id')) {
    /**
     * 현재 로그인한 사용자의 ID 반환
     * Member와 EdmMember 모두 getId() 메서드 사용
     */
    function current_user_id()
    {
        $user = current_user();
        return $user ? $user->getId() : null;
    }
}

if (!function_exists('is_logged_in')) {
    /**
     * 로그인 상태 확인 (멀티가드 지원)
     */
    function is_logged_in()
    {
        return Auth::guard('web')->check() || Auth::guard('customer')->check();
    }
}

if (!function_exists('current_user_name')) {
    /**
     * 현재 사용자 이름 반환
     */
    function current_user_name()
    {
        $user = current_user();
        if (!$user) return null;
        
        // Member의 경우
        if (isset($user->name)) {
            return $user->name;
        }
        
        // EdmMember의 경우
        if (isset($user->mm_name)) {
            return $user->mm_name;
        }
        
        // ID로 대체
        return $user->mm_id ?? $user->username ?? 'User';
    }
}

if (!function_exists('current_user_email')) {
    /**
     * 현재 사용자 이메일 반환
     */
    function current_user_email()
    {
        $user = current_user();
        if (!$user) return null;
        
        // Member의 경우
        if (isset($user->email)) {
            return $user->email;
        }
        
        // EdmMember의 경우
        if (isset($user->mm_email)) {
            return $user->mm_email;
        }
        
        return null;
    }
}

if (!function_exists('current_guard')) {
    /**
     * 현재 활성 가드 이름 반환
     */
    function current_guard()
    {
        if (Auth::guard('web')->check()) {
            return 'web';
        }
        
        if (Auth::guard('customer')->check()) {
            return 'customer';
        }
        
        return null;
    }
}

if (!function_exists('is_admin_logged_in')) {
    /**
     * 관리자(web 가드) 로그인 상태 확인
     */
    function is_admin_logged_in()
    {
        return Auth::guard('web')->check();
    }
}

if (!function_exists('is_customer_logged_in')) {
    /**
     * 고객(customer 가드) 로그인 상태 확인
     */
    function is_customer_logged_in()
    {
        return Auth::guard('customer')->check();
    }
}