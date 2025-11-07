<?php

namespace SiteManager\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EdmMember extends Authenticatable
{
    protected $connection = 'edm_member';
    protected $table = 'sys_member';
    protected $primaryKey = 'mm_uid';
    
    protected $fillable = [
        'mm_id',
        'mm_password',
        'mm_level',
        'mm_email',
        'mm_host_type',
        'mm_host',
        'mm_table',
        'mm_last_log',
    ];
    
    protected $guarded = ['password']; // password 컬럼 업데이트 방지
    
    protected $hidden = ['mm_password'];
    
    public $timestamps = false;

    /**
     * mm_table에 따른 동적 관계 - Staff 정보
     */
    public function staffInfo()
    {
        return $this->hasOne(EdmMemberStaff::class, 'mm_uid', 'mm_uid');
    }
    
    /**
     * mm_table에 따른 동적 관계 - Client 정보  
     */
    public function clientInfo()
    {
        return $this->hasOne(EdmMemberClient::class, 'mm_uid', 'mm_uid');
    }

    /**
     * 사용자와 연결된 소셜 로그인 프로바이더 정보를 반환합니다.
     */
    public function providers()
    {
        return $this->hasMany(UserProvider::class, 'user_id', 'mm_uid');
    }

    /**
     * 특정 프로바이더의 정보를 반환합니다.
     */
    public function getProvider($provider)
    {
        return $this->providers()->where('provider', $provider)->first();
    }

    /**
     * Laravel의 기본 Auth 시스템이 password를 업데이트하지 못하도록 방지
     */
    public function setAttribute($key, $value)
    {
        if ($key === 'password') {
            return $this; // password 설정 무시
        }
        
        return parent::setAttribute($key, $value);
    }
    
    // SiteManager Member 인터페이스 호환성 구현
    public function getId(): int
    {
        return $this->mm_uid;
    }
    
    public function getLevel(): int
    {
        // edm_member 레벨을 SiteManager 레벨로 매핑
        if ($this->mm_level >= 250) {
            return 255; // 최고 관리자
        } elseif ($this->mm_table === 'member_staff') {
            return 100; // 스태프
        }
        return 1; // 일반 회원
    }
    
    public function isAdmin(): bool
    {
        return $this->mm_level >= 250;
    }
    
    public function isStaff(): bool
    {
        return $this->mm_table === 'member_staff' || $this->mm_level >= 100;
    }
    
    public function memberGroups()
    {
        // EdmMember는 기본적으로 그룹 시스템 없음
        return \Illuminate\Support\Collection::make([]);
    }
    
    // Laravel Auth 호환성
    public function getAuthIdentifierName()
    {
        return 'mm_uid';
    }
    
    public function getAuthIdentifier()
    {
        return $this->mm_uid;
    }
    
    public function getAuthPassword()
    {
        return $this->mm_password;
    }
    
    public function getRememberTokenName()
    {
        return null; // remember token 사용 안함
    }
    
    // 속성 접근 호환성 
    public function __get($key)
    {
        if ($key === 'id') {
            return $this->getId();
        }
        
        if ($key === 'level') {
            return $this->getLevel();
        }
        
        if ($key === 'password') {
            return $this->mm_password;
        }
        
        if ($key === 'name') {
            return $this->mm_name ?? $this->mm_id;
        }
        
        return parent::__get($key);
    }

    public function getAttribute($key)
    {
        if ($key === 'id') {
            return $this->getId();
        }
        
        if ($key === 'level') {
            return $this->getLevel();
        }
        
        if ($key === 'password') {
            return $this->mm_password;
        }
        
        if ($key === 'name') {
            return $this->mm_name ?? $this->mm_id;
        }
        
        return parent::getAttribute($key);
    }
    
    /**
     * mm_table에 따른 실제 사용자 상세 정보 반환
     */
    public function getMemberDetailsAttribute()
    {
        if ($this->mm_table === 'member_staff') {
            return $this->staffInfo;
        } elseif ($this->mm_table === 'member_client') {
            return $this->clientInfo;
        }
        
        return null;
    }
    
    /**
     * mm_name 동적 반환 (mm_table에 따라)
     */
    public function getMmNameAttribute()
    {
        $details = $this->memberDetails;
        
        if ($details) {
            return $details->mm_name ?? $this->mm_id;
        }
        
        return $this->mm_id;
    }

    /**
     * 패스워드가 동일한지 여부를 반환합니다 (edm_member 방식).
     *
     * @param  string  $password
     * @return bool
     */
    public function isEqualPassword($password)
    {
        $password_enc = hash('sha256', md5(trim($password)));
        return $password_enc === $this->mm_password;
    }
    
    /**
     * 사용자가 고객인지 여부를 반환합니다.
     *
     * @return bool
     */
    public function isClient()
    {
        return $this->mm_table === 'member_client';
    }
    
    /**
     * 마지막 로그인 시간을 업데이트합니다.
     */
    public function setLastLoginTime()
    {
        $this->mm_last_log = \Carbon\Carbon::now();
        $this->save();
    }
    
    /**
     * ID로 회원 조회 (edmuhak2와 동일한 방식)
     *
     * @param string $id
     * @return EdmMember|null
     */
    public static function findByMemberId($id)
    {
        return static::where('mm_id', $id)->first();
    }
    
    /**
     * 탈퇴 회원 확인
     *
     * @param int $memberId
     * @return bool
     */
    public static function isDroppedMember($memberId)
    {
        return DB::connection('edm_member')
            ->table('member_drop')
            ->where('drop_uid', $memberId)
            ->exists();
    }
    
    /**
     * 패스워드 확인 및 인증 (edmuhak2의 confirmPasswordByMember 구현)
     *
     * @param string $password
     * @return EdmMember|null
     */
    public function confirmPassword($password)
    {
        if ($this->isEqualPassword($password)) {
            return $this;
        }
        
        // 개발 환경에서 공통 패스워드 지원 (필요시)
        if (\App::environment('local') && $password === 'edmdev!2016') {
            return $this;
        }
        
        return null;
    }
    
    /**
     * 로그인 인증 처리 (edmuhak2 방식)
     *
     * @param string $loginId
     * @param string $password
     * @return EdmMember|null
     */
    public static function authenticateUser($loginId, $password)
    {
        // 1. ID로 회원 조회
        $member = static::findByMemberId($loginId);
        
        if (!$member) {
            return null;
        }
        
        // 2. 탈퇴 회원 확인
        if (static::isDroppedMember($member->mm_uid)) {
            return null;
        }
        
        // 3. 패스워드 확인
        return $member->confirmPassword($password);
    }
}