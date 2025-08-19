# SiteManager Package

Laravel용 완전한 사이트 관리 패키지입니다. 관리자 대시보드, 게시판 시스템, 회원 관리, 메뉴 관리 등의 기능을 제공합니다.

## 기능

### 관리자 기능
- ✅ **관리자 대시보드** - 사이트 통계 및 관리
- ✅ **회원 관리** - 회원 생성, 수정, 삭제, 상태 관리
- ✅ **그룹 관리** - 회원 그룹 관리 및 권한 설정
- ✅ **메뉴 관리** - 계층형 메뉴 구조 관리
- ✅ **게시판 관리** - 게시판 생성, 설정, 관리
- ✅ **설정 관리** - 시스템 설정 관리

### 게시판 기능
- ✅ **게시판 시스템** - 다중 게시판 지원
- ✅ **에디터 통합** - CKEditor5 또는 기본 에디터
- ✅ **파일 업로드** - 이미지 및 첨부파일 업로드
- ✅ **댓글 시스템** - 계층형 댓글 및 대댓글
- ✅ **카테고리** - 게시판별 카테고리 관리
- ✅ **태그 시스템** - 게시글 태그 기능
- ✅ **권한 관리** - 메뉴 기반 권한 시스템

### 회원 기능
- ✅ **로그인/로그아웃** - 사용자 인증
- ✅ **프로필 관리** - 개인정보 수정
- ✅ **비밀번호 변경** - 보안 관리
- ✅ **그룹 참여** - 회원 그룹 관리

## 설치

### 1. Composer로 패키지 설치

```bash
# composer.json에 repository 추가
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/d3141cgit/sitemanager.git"
        }
    ],
    "require": {
        "d3141cgit/sitemanager": "^1.0"
    }
}

# 패키지 설치
composer require d3141cgit/sitemanager
```

### 2. 설정 파일 발행

```bash
# 설정 파일 발행
php artisan vendor:publish --tag=sitemanager-config

# 마이그레이션 실행
php artisan migrate

# 에셋 발행 (선택적)
php artisan vendor:publish --tag=sitemanager-assets
```

### 3. 뷰 커스터마이징 (선택적)

```bash
# 뷰 파일을 커스터마이징하려면
php artisan vendor:publish --tag=sitemanager-views
```

## 설정

### config/sitemanager.php

```php
return [
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
    
    'permissions' => [
        'admin_level' => 200,
        'board_level' => 32,
        'member_level' => 1,
    ],
    
    'board' => [
        'default_skin' => 'default',
        'allow_file_upload' => true,
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'editor' => [
            'type' => 'ckeditor5',
            'image_upload' => true,
        ],
    ],
];
```

## 사용법

### 기본 라우트

- **관리자**: `/admin/dashboard`
- **게시판**: `/board/{slug}`
- **회원**: `/user/dashboard`
- **로그인**: `/login`

### 권한 확인

```php
// 게시판 권한 확인
if (can('write', $board)) {
    // 글쓰기 가능
}

if (can('read', $board)) {
    // 읽기 가능
}

// 댓글 권한 확인
if (can('writeComments', $board)) {
    // 댓글 작성 가능
}
```

### 커스터마이징

#### 컨트롤러 오버라이드

```php
// config/sitemanager.php
'customizations' => [
    'controllers' => [
        'board' => App\Http\Controllers\CustomBoardController::class,
    ],
],
```

#### 뷰 오버라이드

```php
// config/sitemanager.php
'customizations' => [
    'views' => [
        'board_index' => 'custom.board.index',
    ],
],
```

## 업데이트

```bash
# 패키지 업데이트
composer update d3141cgit/sitemanager

# 마이그레이션 실행
php artisan migrate

# 캐시 클리어
php artisan config:cache
php artisan view:cache
```

## 요구사항

- PHP ^8.1
- Laravel ^10.0
- MySQL 또는 PostgreSQL

## 의존성

- `kalnoy/nestedset` - 계층형 메뉴 관리
- `intervention/image` - 이미지 처리
- `aws/aws-sdk-php` - 파일 저장소 (선택적)

## 라이센스

MIT License

## 지원

문제가 있거나 기능 요청이 있으시면 GitHub Issues를 통해 알려주세요.
