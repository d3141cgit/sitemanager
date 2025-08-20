<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SiteManager Configuration
    |--------------------------------------------------------------------------
    */
    
    'features' => [
        'admin' => true,
        'boards' => true,
        'menus' => true,
        'members' => true,
        'groups' => true,
        'assets' => true,
        'comments' => true,
        'editor' => true,
    ],
    
    'ui' => [
        'theme' => 'default',
        'admin_prefix' => 'admin',
        'pagination_per_page' => 20,
        'board_posts_per_page' => 20,
    ],
    
    'permissions' => [
        'admin_level' => 200,
        'board_level' => 32,
        'member_level' => 1,
    ],
    
    'board' => [
        'default_skin' => 'default',
        'allow_file_upload' => true,
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip'],
        'editor' => [
            'type' => 'ckeditor5', // ckeditor5, tinymce, simple
            'image_upload' => true,
            'max_image_size' => 5 * 1024 * 1024, // 5MB
        ],
    ],
    
    'routes' => [
        'web' => [
            'prefix' => '',
            'middleware' => ['web'],
            // 'as' => 'sitemanager.',
        ],
        'admin' => [
            'prefix' => 'admin',
            'middleware' => ['web', 'auth', 'admin'],
            'as' => 'admin.',
        ],
        'api' => [
            'prefix' => 'api',
            'middleware' => ['api', 'auth'],
            'as' => 'api.',
        ],
    ],
    
    'storage' => [
        'disk' => env('STORAGE_DISK', 'public'),
        'upload_path' => 'uploads',
        'image_path' => 'images',
    ],
    
    // 사이트별 커스터마이징
    'customizations' => [
        'admin_layout' => 'sitemanager::layouts.admin',
        // 'board_layout' => 'sitemanager::layouts.app',
        'controllers' => [
            'admin_board' => null, // 커스텀 컨트롤러로 오버라이드 가능
            'board' => null,
            'member' => null,
        ],
        'views' => [
            'admin_dashboard' => 'sitemanager::admin.dashboard',
            // 'board_index' => 'sitemanager::board.index',
            // 'board_show' => 'sitemanager::board.show',
        ],
    ],
];
