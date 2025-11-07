<?php

namespace SiteManager\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UserProvider extends Model
{
    /**
     * DB Connection
     */
    protected $connection = 'edm_member';

    /**
     * Table name
     */
    protected $table = 'user_providers';

    /**
     * PK column name
     */
    protected $primaryKey = 'id';

    /**
     * 모델의 timestamp 설정
     */
    public $timestamps = true;

    /**
     * 대량 할당할 수 있는 속성들을 정의합니다.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'activated',
        'provider',
        'provider_id',
        'provider_email',
        'last_signed_at',
    ];

    /**
     * 형변환할 속성들을 정의합니다.
     *
     * @var array
     */
    protected $casts = [
        'activated' => 'boolean',
        'last_signed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 사용자와 연결된 관계를 반환합니다.
     */
    public function member()
    {
        return $this->belongsTo(EdmMember::class, 'user_id', 'mm_uid');
    }

    /**
     * 활성화된 프로바이더인지 확인합니다.
     */
    public function isActivated()
    {
        return $this->activated;
    }

    /**
     * 마지막 로그인 시간을 업데이트합니다.
     */
    public function updateLastSignedAt()
    {
        $this->last_signed_at = Carbon::now();
        $this->save();
    }

    /**
     * 모델 업데이트 시 로그 추가
     */
    protected static function booted()
    {
        static::updating(function ($userProvider) {
            Log::info('UserProvider 업데이트', [
                'id' => $userProvider->id,
                'user_id' => $userProvider->user_id,
                'provider' => $userProvider->provider,
                'old_activated' => $userProvider->getOriginal('activated'),
                'new_activated' => $userProvider->activated,
                'changes' => $userProvider->getDirty()
            ]);
        });

        static::updated(function ($userProvider) {
            Log::info('UserProvider 업데이트 완료', [
                'id' => $userProvider->id,
                'user_id' => $userProvider->user_id,
                'provider' => $userProvider->provider,
                'activated' => $userProvider->activated,
                'was_changed' => $userProvider->wasChanged('activated')
            ]);
        });
    }
}
