<?php

namespace SiteManager\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SiteManager\Models\EdmMember;

class MemberServiceFactory
{
    /**
     * 적절한 MemberService 인스턴스 반환
     */
    public static function create(): MemberService
    {
        // Dual Auth 활성화 체크
        if (self::isDualAuthEnabled()) {
            return \App::make(DualAuthMemberService::class);
        }
        
        return \App::make(MemberService::class);
    }
    
    /**
     * Dual Auth 시스템 활성화 여부 확인
     */
    private static function isDualAuthEnabled(): bool
    {
        // 1. 환경 변수로 활성화 확인
        if (!Config::get('sitemanager.enable_edm_member_auth', false)) {
            return false;
        }
        
        // 2. EdmMember 모델 존재 확인
        if (!class_exists(EdmMember::class)) {
            return false;
        }
        
        // 3. EdmMember 데이터베이스 연결 가능 확인
        try {
            DB::connection('edm_member')->getPdo();
            return true;
        } catch (\Exception $e) {
            Log::info('EdmMember 데이터베이스 접근 불가: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 현재 사용 중인 서비스 타입 반환
     */
    public static function getServiceType(): string
    {
        return self::isDualAuthEnabled() ? 'dual_auth' : 'standard';
    }
}