<?php

namespace SiteManager\Models;

use Illuminate\Database\Eloquent\Model;

/**
 */
class Setting extends Model
{
    protected $table = 'settings';
    
    protected $fillable = [
        'key',
        'value', 
        'type'
    ];

    protected $casts = [
        'value' => 'string',
    ];

    public $timestamps = false;

    /**
     * 설정값 가져오기
     */
    public static function getValue($key, $default = null)
    {
        $config = self::where('key', $key)->first();
        return $config ? $config->value : $default;
    }

    /**
     * 설정값 설정하기
     */
    public static function setValue($key, $value, $type = 'text')
    {
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }

    /**
     * Boolean 값 처리
     */
    public function getValueAttribute($value)
    {
        if ($this->type === 'bool' || $this->type === 'cfg.bool') {
            return $value === 'true' || $value === '1' || $value === 1;
        }
        return $value;
    }

    public function setValueAttribute($value)
    {
        if ($this->type === 'bool' || $this->type === 'cfg.bool') {
            $this->attributes['value'] = $value ? 'true' : 'false';
        } else {
            $this->attributes['value'] = $value;
        }
    }
}
