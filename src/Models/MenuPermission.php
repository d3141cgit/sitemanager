<?php

namespace SiteManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 */
class MenuPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id', // menus.id 참조
        'subject_id', // 권한 주체 id: member level(1-255), groups.id, admin id 등
        'type', // admin, level, group
        'permission', // bitmask 권한
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}
