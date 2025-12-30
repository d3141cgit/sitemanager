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
    
    'auth' => [
        'model' => env('AUTH_MODEL', 'SiteManager\Models\Member'),
        
        // EdmMember 고객 인증 시스템 활성화
        'enable_edm_member_auth' => env('ENABLE_EDM_MEMBER_AUTH', false),
        'admin_guard' => env('ADMIN_GUARD', 'web'),
        'customer_guard' => env('CUSTOMER_GUARD', 'customer'),
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

    'upload' => [
        'max_image_size' => 10240, // KB (10MB) - 이미지 업로드 최대 크기
        'max_file_size' => 10240,  // KB (10MB) - 일반 파일 업로드 최대 크기
    ],

    'menu' => [
        /*
        |--------------------------------------------------------------------------
        | Menu Tab Behavior
        |--------------------------------------------------------------------------
        |
        | 섹션별 메뉴 탭 동작을 설정합니다.
        |
        | 'siblings' (기본값): 같은 부모의 형제 메뉴들만 탭으로 표시
        | 'same_depth_in_section': 같은 섹션 내 같은 depth의 모든 메뉴들을 탭으로 표시
        |
        | 예: 2 => 'same_depth_in_section' 설정 시,
        |     Section 2의 모든 depth 2 메뉴들이 탭으로 표시됨
        |
        */
        'tab_behavior' => [
            // 2 => 'same_depth_in_section',
        ],
    ],

    'language' => [
        'trace_enabled' => env('SITEMANAGER_LANGUAGE_TRACE', false),
        'available_locales' => ['en', 'ko', 'tw'],
        'default_locale' => 'en',
    ],
    
    // 보안 설정
    'security' => [
        'email_verification' => [
            'enabled' => env('SITEMANAGER_EMAIL_VERIFICATION_ENABLED', true),
            'verification_token_expires' => 24, // 초기 인증 토큰 유효시간 (시간)
            'edit_token_expires' => 1, // 수정/삭제 인증 토큰 유효시간 (시간)
        ],
        'recaptcha' => [
            'enabled' => env('SITEMANAGER_RECAPTCHA_ENABLED', false),
            'site_key' => env('RECAPTCHA_SITE_KEY'),
            'secret_key' => env('RECAPTCHA_SECRET_KEY'),
            'version' => 'v3', // v3 전용으로 고정
            'score_threshold' => 0.5, // v3용 점수 임계값 (고정값)
        ],
        'honeypot' => [
            'enabled' => env('SITEMANAGER_HONEYPOT_ENABLED', true),
            'fields' => ['website', 'url', 'homepage', 'phone_number', 'company_phone'],
        ],
        'behavior_tracking' => [
            'enabled' => env('SITEMANAGER_BEHAVIOR_TRACKING_ENABLED', true),
            'min_interaction_time' => 3, // 최소 상호작용 시간 (초)
            'max_form_time' => 1800, // 최대 폼 시간 (초, 30분)
        ],
        'blocked_email_domains' => [
            '10minutemail.com',
            'guerrillamail.com',
            'mailinator.com',
            'tempmail.org',
            'temp-mail.org',
            'yopmail.com',
            'throwaway.email',
            'dispostable.com',
            '33mail.com',
        ],
        'rate_limiting' => [
            'max_attempts' => 5, // IP당 최대 시도 횟수
            'decay_minutes' => 60, // 제한 해제 시간 (분)
        ],
        'spam_keywords' => [
            'casino', 'poker', 'viagra', 'cialis', 'loan', 'mortgage',
            'bitcoin', 'crypto', 'investment', 'forex', 'trading',
            'weight loss', 'diet pill', 'sex', 'adult', 'porn',
            'dating', 'hookup', 'escort', 'pharmacy', 'drugs'
        ],
        'max_urls_per_post' => 3,
        'min_form_time' => 3, // 최소 폼 작성 시간 (초)
        'guest_posting' => [
            'enabled' => true,
            'require_email_verification' => true,
            'require_captcha' => true,
        ],
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
