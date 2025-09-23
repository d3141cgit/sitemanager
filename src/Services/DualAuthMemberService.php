<?php

namespace SiteManager\Services;

use SiteManager\Models\Member;
use SiteManager\Models\EdmMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class DualAuthMemberService extends MemberService
{
    /**
     * 회원 생성 (EdmMember 기존 사용자만 - Dual Auth 전용)
     * 
     * Dual Auth 모드에서는 EdmMember에 이미 존재하는 사용자만 Members로 이전/연동합니다.
     * 새로운 사용자 생성은 EdmMember에서 먼저 이루어져야 합니다.
     */
    public function createMember(array $data): Member
    {
        // EdmMember 연결 가능성 확인
        if (!$this->canConnectToEdmMember()) {
            throw new \Exception('EdmMember 데이터베이스에 접근할 수 없습니다. Dual Auth 모드를 사용할 수 없습니다.');
        }

        // EdmMember에서 기존 사용자 확인 (필수)
        $existingEdmMember = $this->findExistingEdmMember($data);
        
        if (!$existingEdmMember) {
            throw new \Exception(
                'Dual Auth 모드에서는 EdmMember에 이미 존재하는 사용자만 Members로 이전할 수 있습니다. ' .
                '새로운 사용자는 EdmMember에서 먼저 생성해주세요.'
            );
        }

        // 이미 Members에 해당 사용자가 있는지 확인
        if (Member::where('id', $existingEdmMember->mm_uid)->exists()) {
            throw new \Exception("이미 동일한 사용자(ID: {$existingEdmMember->mm_uid})가 Members 테이블에 존재합니다.");
        }
        
        // 중복 체크 (이메일, 사용자명 등)
        $this->validateUniqueFields($data);
        
        // EdmMember의 ID를 사용하여 Members에 생성 (새 비밀번호 사용)
        $data['id'] = $existingEdmMember->mm_uid;
        
        // name 필드가 없으면 username을 사용
        if (empty($data['name'])) {
            $data['name'] = $data['username'] ?? $existingEdmMember->mm_id;
        }
        
        // 불필요한 필드 제거
        unset($data['password_confirmation']);
        
        // Repository를 통해 생성 (패스워드 해시 자동 처리)
        return $this->memberRepository->create($data);
    }
    
    /**
     * EdmMember 데이터베이스 연결 가능 여부 확인
     */
    private function canConnectToEdmMember(): bool
    {
        try {
            // EdmMember 연결 테스트
            DB::connection('edm_member')->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * EdmMember 사용자 정보 동기화
     */
    private function findExistingEdmMember(array $data): ?EdmMember
    {
        try {
            $query = EdmMember::query();
            
            // 아이디로 검색
            if (!empty($data['username'])) {
                $member = $query->where('mm_id', $data['username'])->first();
                if ($member) {
                    return $member;
                }
            }
            
            // 이메일로 검색
            if (!empty($data['email'])) {
                return EdmMember::where('mm_email', $data['email'])->first();
            }
            
            return null;
        } catch (\Exception $e) {
            // EdmMember 모델 사용 불가시 null 반환
            return null;
        }
    }
    
    /**
     * EdmMember 사용자를 Members로 이전
     * 
     * @param string $identifier 아이디 또는 이메일
     * @param array $additionalData Members 테이블에 추가할 데이터
     * @return Member
     */
    public function migrateFromEdmMember(string $identifier, array $additionalData = []): Member
    {
        // EdmMember에서 사용자 찾기
        $edmMember = $this->findEdmMemberByIdentifier($identifier);
        
        if (!$edmMember) {
            throw new \Exception("EdmMember에서 사용자를 찾을 수 없습니다: {$identifier}");
        }
        
        // 이미 Members에 존재하는지 확인
        if (Member::where('id', $edmMember->mm_uid)->exists()) {
            throw new \Exception("이미 Members에 존재하는 사용자입니다 (ID: {$edmMember->mm_uid})");
        }
        
        // EdmMember 정보를 바탕으로 Members 데이터 구성
        $memberData = array_merge([
            'id' => $edmMember->mm_uid,
            'username' => $edmMember->mm_id,
            'email' => $edmMember->mm_email,
            'name' => $edmMember->mm_name,
            'password' => $edmMember->mm_password, // 또는 새로운 패스워드 설정
            // 필요한 다른 필드들...
        ], $additionalData);
        
        return $this->memberRepository->create($memberData);
    }
    
    /**
     * 아이디 또는 이메일로 EdmMember 찾기
     */
    private function findEdmMemberByIdentifier(string $identifier): ?EdmMember
    {
        try {
            // 이메일 형식인지 확인
            if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                return EdmMember::where('mm_email', $identifier)->first();
            } else {
                return EdmMember::where('mm_id', $identifier)->first();
            }
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * EdmMember에 존재하는 사용자 목록 조회 (Members에 없는 사용자만)
     */
    public function getAvailableEdmMembers(): \Illuminate\Support\Collection
    {
        try {
            // EdmMember에는 있지만 Members에는 없는 사용자들
            $existingMemberIds = Member::pluck('id')->toArray();
            
            return EdmMember::whereNotIn('mm_uid', $existingMemberIds)
                ->select('mm_uid', 'mm_id', 'mm_email', 'mm_name')
                ->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }
}