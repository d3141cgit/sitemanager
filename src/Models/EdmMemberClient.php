<?php

namespace SiteManager\Models;

use Illuminate\Database\Eloquent\Model;

class EdmMemberClient extends Model
{
    protected $connection = 'edm_member';
    protected $table = 'member_client';
    protected $primaryKey = 'mm_uid';
    
    protected $fillable = [
        'mm_uid',
        'mm_name',
        'mm_birthday',
        'mm_phone',
        'mm_mobile', 
        'mm_email2',
        'mm_zip',
        'mm_addr',
        'mm_addr2',
        'mm_memo',
        'mm_parent_name',
        'mm_parent_phone',
        'mm_parent_email',
    ];
    
    public $timestamps = false;
    
    /**
     * sys_member와의 관계
     */
    public function sysMember()
    {
        return $this->belongsTo(EdmMember::class, 'mm_uid', 'mm_uid');
    }
}