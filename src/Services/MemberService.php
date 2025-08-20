<?php

namespace SiteManager\Services;

use SiteManager\Models\Member;
use SiteManager\Repositories\MemberRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MemberService
{
    protected $memberRepository;
    
    public function __construct(MemberRepositoryInterface $memberRepository)
    {
        $this->memberRepository = $memberRepository;
    }
    
    /**
     * 모든 회원 목록 조회 (페이지네이션)
     */
    public function getAllMembers(int $perPage = 15): LengthAwarePaginator
    {
        return $this->memberRepository->paginate($perPage);
    }
    
    /**
     * 회원 상세 조회
     */
    public function getMember(int $id): ?Member
    {
        return $this->memberRepository->find($id);
    }
    
    /**
     * 회원 생성
     */
    public function createMember(array $data): Member
    {
        // 중복 체크
        $this->validateUniqueFields($data);
        
        return $this->memberRepository->create($data);
    }
    
    /**
     * 회원 정보 수정
     */
    public function updateMember(int $id, array $data): bool
    {
        $member = $this->memberRepository->find($id);
        
        if (!$member) {
            throw new \Exception('회원을 찾을 수 없습니다.');
        }
        
        // 중복 체크 (본인 제외)
        $this->validateUniqueFields($data, $id);
        
        return $this->memberRepository->update($id, $data);
    }
    
    /**
     * 회원 삭제 (소프트 삭제)
     */
    public function deleteMember(int $id): bool
    {
        $member = $this->memberRepository->find($id);
        
        if (!$member) {
            throw new \Exception('회원을 찾을 수 없습니다.');
        }
        
        return $this->memberRepository->delete($id);
    }
    
    /**
     * 회원 복원
     */
    public function restoreMember(int $id): bool
    {
        return $this->memberRepository->restore($id);
    }
    
    /**
     * 회원 영구 삭제
     */
    public function forceDeleteMember(int $id): bool
    {
        return $this->memberRepository->forceDelete($id);
    }
    
    /**
     * 로그인 처리
     */
    public function attemptLogin(string $username, string $password): bool
    {
        $member = $this->memberRepository->findByUsername($username);
        
        if (!$member || !Hash::check($password, $member->password)) {
            return false;
        }
        
        // 비활성화된 계정 체크
        if (!$member->active) {
            throw new \Exception('비활성화된 계정입니다. 관리자에게 문의하세요.');
        }
        
        Auth::login($member);
        return true;
    }
    
    /**
     * 로그아웃 처리
     */
    public function logout(): void
    {
        Auth::logout();
    }
    
    /**
     * 중복 필드 검증
     */
    private function validateUniqueFields(array $data, ?int $excludeId = null): void
    {
        if (isset($data['username'])) {
            $existing = $this->memberRepository->findByUsername($data['username']);
            if ($existing && ($excludeId === null || $existing->id !== $excludeId)) {
                throw ValidationException::withMessages([
                    'username' => ['이미 사용중인 사용자명입니다.']
                ]);
            }
        }
        
        if (isset($data['email'])) {
            $existing = $this->memberRepository->findByEmail($data['email']);
            if ($existing && ($excludeId === null || $existing->id !== $excludeId)) {
                throw ValidationException::withMessages([
                    'email' => ['이미 사용중인 이메일입니다.']
                ]);
            }
        }
    }
}
