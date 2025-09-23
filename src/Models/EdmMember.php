<?php

namespace SiteManager\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;

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
    ];
    
    protected $hidden = ['mm_password'];
    
    public $timestamps = false;
    
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
        
        return parent::getAttribute($key);
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
}