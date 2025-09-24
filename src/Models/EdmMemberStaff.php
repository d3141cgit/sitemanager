<?php

namespace SiteManager\Models;

use Illuminate\Database\Eloquent\Model;

class EdmMemberStaff extends Model
{
    protected $connection = 'edm_member';
    protected $table = 'member_staff';
    protected $primaryKey = 'mm_uid';
    
    protected $fillable = [
        'mm_uid',
        'mm_name',
        'mm_division',
        'mm_position',
        'mm_phone',
        'mm_mobile',
        'mm_email2',
        'mm_zip',
        'mm_addr',
        'mm_addr2',
        'mm_memo',
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