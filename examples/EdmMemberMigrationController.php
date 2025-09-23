<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SiteManager\Services\MemberServiceFactory;
use SiteManager\Services\DualAuthMemberService;

class EdmMemberMigrationController extends Controller
{
    /**
     * EdmMember에서 Members로 사용자 이전
     */
    public function migrateUser(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string', // 아이디 또는 이메일
            'password' => 'nullable|string|min:6', // 새로운 패스워드 (선택사항)
        ]);
        
        $memberService = MemberServiceFactory::create();
        
        // Dual Auth 모드인지 확인
        if (!($memberService instanceof DualAuthMemberService)) {
            return response()->json([
                'error' => 'Dual Auth 모드가 아닙니다.'
            ], 400);
        }
        
        try {
            $additionalData = [];
            
            // 새로운 패스워드가 제공된 경우
            if ($request->password) {
                $additionalData['password'] = bcrypt($request->password);
            }
            
            $member = $memberService->migrateFromEdmMember(
                $request->identifier,
                $additionalData
            );
            
            return response()->json([
                'success' => true,
                'message' => 'EdmMember에서 Members로 사용자가 성공적으로 이전되었습니다.',
                'member' => $member
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * 이전 가능한 EdmMember 사용자 목록 조회
     */
    public function getAvailableUsers()
    {
        $memberService = MemberServiceFactory::create();
        
        if (!($memberService instanceof DualAuthMemberService)) {
            return response()->json([
                'error' => 'Dual Auth 모드가 아닙니다.'
            ], 400);
        }
        
        $availableUsers = $memberService->getAvailableEdmMembers();
        
        return response()->json([
            'users' => $availableUsers,
            'total' => $availableUsers->count()
        ]);
    }
    
    /**
     * 일괄 사용자 이전
     */
    public function batchMigrate(Request $request)
    {
        $request->validate([
            'identifiers' => 'required|array',
            'identifiers.*' => 'required|string',
        ]);
        
        $memberService = MemberServiceFactory::create();
        
        if (!($memberService instanceof DualAuthMemberService)) {
            return response()->json([
                'error' => 'Dual Auth 모드가 아닙니다.'
            ], 400);
        }
        
        $results = [
            'success' => [],
            'failed' => []
        ];
        
        foreach ($request->identifiers as $identifier) {
            try {
                $member = $memberService->migrateFromEdmMember($identifier);
                $results['success'][] = [
                    'identifier' => $identifier,
                    'member_id' => $member->id
                ];
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'identifier' => $identifier,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return response()->json([
            'message' => '일괄 이전이 완료되었습니다.',
            'results' => $results,
            'summary' => [
                'success_count' => count($results['success']),
                'failed_count' => count($results['failed'])
            ]
        ]);
    }
}