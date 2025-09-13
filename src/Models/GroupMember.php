<?php

namespace SiteManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 */
class GroupMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'member_id',
        'role', // 그룹 내 역할(예: manager, editor, member 등)
    ];

    public $timestamps = false;

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
