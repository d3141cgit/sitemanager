<?php

namespace SiteManager\Models;

use SiteManager\Services\FileUploadService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $username
 * @property string $password
 * @property string $name
 * @property string $email
 * @property int $level
 * @property bool $active
 * @property string|null $profile_photo
 * @property string|null $phone
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read string $level_display
 * @property-read string $level_name
 * @property-read string $profile_photo_url
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \SiteManager\Models\Group> $groups
 * @property-read int|null $groups_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member byLevel($level)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member admins()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Member staff()
 * @mixin \Eloquent
 */
class Member extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'username',
        'password',
        'name',
        'email',
        'level',
        'active',
        'profile_photo', // 프로필 사진 경로
        'phone',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'active' => 'boolean',
    ];

    protected $dates = ['deleted_at'];

    // 스코프 메서드들
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('active', false);
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    public function scopeAdmins($query)
    {
        return $query->where('level', '>=', config('member.admin_level'));
    }

    public function scopeStaff($query)
    {
        return $query->where('level', '>=', config('member.staff_level'));
    }

    // 헬퍼 메서드들
    public function isAdmin()
    {
        return $this->level >= config('member.admin_level');
    }

    public function isStaff()
    {
        return $this->level >= config('member.staff_level');
    }

    public function getLevelNameAttribute()
    {
        $levels = config('member.levels');
        
        // 정확히 일치하는 레벨이 있으면 반환
        if (isset($levels[$this->level])) {
            return $levels[$this->level];
        }
        
        // 정의되지 않은 레벨이면 해당 레벨보다 낮은 가장 높은 정의 찾기
        $sortedLevels = collect($levels)->sortByDesc(function ($name, $level) {
            return $level;
        });
        
        foreach ($sortedLevels as $definedLevel => $name) {
            if ($this->level >= $definedLevel) {
                return $name;
            }
        }
        
        // 모든 정의된 레벨보다 낮으면 가장 낮은 레벨의 이름 반환
        return $sortedLevels->last();
    }

    public function getLevelDisplayAttribute()
    {
        return $this->level . ' - ' . $this->level_name;
    }

    /**
     * 프로필 사진 URL 접근자
     */
    public function getProfilePhotoUrlAttribute()
    {
        if (!$this->profile_photo) {
            return null;
        }
        
        return FileUploadService::url($this->profile_photo);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_members');
    }
}
