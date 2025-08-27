<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SiteManager Configuration
    |--------------------------------------------------------------------------
    */
    
    'features' => [
        'sitemanager' => true,
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
        'sitemanager_prefix' => 'sitemanager',
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
        'max_files_per_post' =>5,
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
        'sitemanager' => [
            'prefix' => 'sitemanager',
            'middleware' => ['web', 'auth', 'sitemanager'],
            'as' => 'sitemanager.',
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
        'sitemanager_layout' => 'sitemanager::layouts.sitemanager',
        // 'board_layout' => 'sitemanager::layouts.app',
        'controllers' => [
            'sitemanager_board' => null, // 커스텀 컨트롤러로 오버라이드 가능
            'board' => null,
            'member' => null,
        ],
        'views' => [
            'sitemanager_dashboard' => 'sitemanager::sitemanager.dashboard',
            // 'board_index' => 'sitemanager::board.index',
            // 'board_show' => 'sitemanager::board.show',
        ],
    ],
];
