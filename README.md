# SiteManager Package

Laravel용 완전한 사이트 관리 패키지입니다. 관리자 대시보드, 게시판 시스템, 회원 관리, 메뉴 관리 등의 기능을 제공합니다.

## 📋 목차

- [기능](#기능)
- [요구사항](#요구사항)
- [설치방법](#설치방법)
- [설정](#설정)
- [사용법](#사용법)
- [커스터마이징](#커스터마이징)
- [업데이트](#업데이트)

## 요구사항

- PHP ^8.1
- Laravel ^10.0|^11.0|^12.0
- MySQL 또는 PostgreSQL 또는 SQLite
- Composer

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

### 아키텍처 패턴
- ✅ **Repository Pattern** - 데이터 접근 계층 분리
- ✅ **Service Layer** - 비즈니스 로직 캡슐화
- ✅ **Console Commands** - 설치 및 관리 명령어
- ✅ **View Components** - 재사용 가능한 뷰 컴포넌트

## 설치방법

### 📦 방법 1: Private Git Server (권장)

```bash
# 1. 새 Laravel 프로젝트 생성
composer create-project laravel/laravel your-project-name

# 2. 프로젝트 디렉토리로 이동
cd your-project-name

# 3. Git 저장소 등록
composer config repositories.sitemanager vcs ssh://miles@server/home/miles/git/sitemanager.git

# 4. 패키지 설치
composer require d3141cgit/sitemanager:dev-main

# 5. 설정 파일 및 자원 발행
php artisan vendor:publish --provider="SiteManager\SiteManagerServiceProvider"

# 6. 데이터베이스 마이그레이션
php artisan migrate

# 7. 관리자 계정 생성
php artisan sitemanager:admin
```

### 📦 방법 2: 로컬 패키지 (개발용)

```bash
# 1. composer.json에 로컬 패키지 등록
{
    "repositories": [
        {
            "type": "path",
            "url": "/path/to/packages/sitemanager"
        }
    ],
    "require": {
        "d3141cgit/sitemanager": "*"
    }
}

# 2. 패키지 설치
composer require d3141cgit/sitemanager --prefer-source

# 3. 나머지 설치 과정은 동일
```

### 🚀 빠른 설치 (일괄 설치)

```bash
# 설정, 마이그레이션, 자원 발행을 한 번에
php artisan sitemanager:install

# 관리자 계정 생성 (대화형)
php artisan sitemanager:admin

# 또는 옵션으로 직접 생성
php artisan sitemanager:admin --name="Admin" --email="admin@example.com" --password="password123"
```

### 📁 발행되는 파일들

설치 시 다음 파일들이 프로젝트에 복사됩니다:

**설정 파일:**
- `config/sitemanager.php` - 메인 설정
- `config/member.php` - 회원 관련 설정  
- `config/menu.php` - 메뉴 관련 설정
- `config/permissions.php` - 권한 관련 설정

**뷰 파일:**
- `resources/views/vendor/sitemanager/` - 모든 뷰 템플릿

**CSS/JS 파일:**
- `resources/css/vendor/sitemanager/` - CSS 파일들
- `resources/js/vendor/sitemanager/` - JavaScript 파일들

**DB 마이그레이션:**
- `database/migrations/` - 데이터베이스 스키마

**Public 자원:**
- `public/vendor/sitemanager/` - 이미지, 아이콘 등

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

### Console Commands

```bash
# 패키지 설치 (설정 발행, 마이그레이션, 자원 복사 일괄 처리)
php artisan sitemanager:install

# 관리자 계정 생성 (대화형)
php artisan sitemanager:admin

# 관리자 계정 생성 (옵션 사용)
php artisan sitemanager:admin --name="Admin" --email="admin@test.com" --password="password123"

# S3 연결 테스트
php artisan sitemanager:test-s3

# S3 설정 확인
php artisan sitemanager:check-s3

# 이미지를 S3로 마이그레이션
php artisan sitemanager:migrate-images-s3
```

### 접속 및 사용

설치가 완료되면 다음 주소로 접속할 수 있습니다:

- **관리자 대시보드**: `http://yoursite.com/admin/dashboard`
- **로그인**: `http://yoursite.com/login`
- **회원 대시보드**: `http://yoursite.com/user/dashboard`  
- **게시판**: `http://yoursite.com/board/{slug}`

### 첫 로그인

1. `php artisan sitemanager:admin`으로 생성한 계정으로 `/login`에서 로그인
2. 관리자 권한으로 `/admin/dashboard` 접속
3. 메뉴, 게시판, 회원 등을 설정

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

### 패키지 업데이트

```bash
# Private Git Server에서 업데이트
composer update d3141cgit/sitemanager

# 새로운 마이그레이션이 있다면 실행
php artisan migrate

# 새로운 설정이나 자원이 추가되었다면 재발행
php artisan vendor:publish --provider="SiteManager\SiteManagerServiceProvider" --force

# 캐시 클리어
php artisan config:clear
php artisan view:clear
php artisan cache:clear
```

### 버전 관리

```bash
# 특정 커밋으로 설치
composer require d3141cgit/sitemanager:dev-main#abc1234

# 최신 버전으로 업데이트
composer require d3141cgit/sitemanager:dev-main
```

## 문제 해결

### 일반적인 문제들

1. **마이그레이션 오류**: `php artisan migrate:fresh`로 DB 초기화 후 재설치
2. **권한 문제**: `storage` 및 `bootstrap/cache` 디렉토리 권한 확인
3. **CSS/JS 로드 안됨**: `php artisan vendor:publish --provider="SiteManager\SiteManagerServiceProvider" --force`
4. **로그인 안됨**: 관리자 계정 재생성 `php artisan sitemanager:admin`

### 로그 확인

```bash
# Laravel 로그 확인
tail -f storage/logs/laravel.log

# 디버그 모드 활성화 (.env)
APP_DEBUG=true
```

## 개발 워크플로우

### ⚠️ 중요: vendor/ 디렉토리에서 직접 수정하지 마세요!

`vendor/d3141cgit/sitemanager`에서 직접 수정하면 `composer update` 시 모든 변경사항이 사라집니다.

### 📝 올바른 패키지 수정 방법

#### 방법 1: 패키지 개발 환경 구성 (권장)

```bash
# 1. 패키지 소스를 로컬에 클론
cd /path/to/your/packages
git clone ssh://miles@server/home/miles/git/sitemanager.git

# 2. 프로젝트의 composer.json에 로컬 패키지 경로 설정
{
    "repositories": [
        {
            "type": "path",
            "url": "/path/to/your/packages/sitemanager",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "d3141cgit/sitemanager": "*"
    }
}

# 3. 패키지 재설치 (심볼릭 링크로)
composer remove d3141cgit/sitemanager
composer require d3141cgit/sitemanager --prefer-source
```

이제 `/path/to/your/packages/sitemanager`에서 수정하면 프로젝트에 바로 반영됩니다.

#### 방법 2: Fork & 개발

```bash
# 1. 패키지를 별도 디렉토리에 클론
git clone ssh://miles@server/home/miles/git/sitemanager.git sitemanager-dev
cd sitemanager-dev

# 2. 수정 작업 수행
# 파일 수정...

# 3. 변경사항 커밋
git add .
git commit -m "Fix: something"
git push origin main

# 4. 프로젝트에서 패키지 업데이트
cd /path/to/your/project
composer update d3141cgit/sitemanager
```

### 🔄 변경사항 서버 적용 과정

#### 1. 패키지 개발 및 테스트

```bash
# 패키지 개발 디렉토리에서
cd /path/to/packages/sitemanager

# 수정 작업 수행
vim src/Http/Controllers/SomeController.php

# 테스트 (연결된 프로젝트에서 바로 확인 가능)
```

#### 2. 변경사항 커밋 및 푸시

```bash
# 패키지 디렉토리에서
git add .
git commit -m "Feature: Add new functionality"
git push origin main
```

#### 3. 다른 프로젝트들에 배포

```bash
# 각 프로젝트에서 패키지 업데이트
cd /path/to/project1
composer update d3141cgit/sitemanager

cd /path/to/project2  
composer update d3141cgit/sitemanager

# 필요시 새로운 마이그레이션이나 설정 발행
php artisan migrate
php artisan vendor:publish --provider="SiteManager\SiteManagerServiceProvider" --force
```

### 🛠️ 개발 시 유용한 명령어

```bash
# 패키지를 심볼릭 링크로 설치 (개발용)
composer require d3141cgit/sitemanager --prefer-source

# 패키지를 실제 파일로 설치 (운영용)
composer require d3141cgit/sitemanager --prefer-dist

# 특정 커밋으로 설치
composer require d3141cgit/sitemanager:dev-main#abc1234

# 캐시 강제 새로고침
composer clear-cache
composer update d3141cgit/sitemanager --no-cache
```

### 🔧 로컬 개발 환경 예시

```bash
# 디렉토리 구조
/Users/yourname/
├── packages/
│   └── sitemanager/          # 패키지 개발
├── projects/
│   ├── church-site1/         # 프로젝트 1
│   ├── church-site2/         # 프로젝트 2
│   └── church-site3/         # 프로젝트 3

# 각 프로젝트의 composer.json
{
    "repositories": [
        {
            "type": "path",
            "url": "../packages/sitemanager"
        }
    ]
}
```

이렇게 하면 `packages/sitemanager`에서 수정한 내용이 모든 프로젝트에 바로 반영됩니다!

## 개발 환경 설정

### SSH 키 설정 (Private Git Server 접속용)

```bash
# SSH 키가 없다면 생성
ssh-keygen -t rsa -b 4096 -C "your-email@example.com"

# 공개키를 서버에 등록
ssh-copy-id miles@server

# 연결 테스트
ssh miles@server
```

## 의존성

- `kalnoy/nestedset` - 계층형 메뉴 관리
- `intervention/image` - 이미지 처리
- `aws/aws-sdk-php` - 파일 저장소 (선택적)

## 라이센스

MIT License

## 연락처

- **개발자**: Songhyun Dong
- **이메일**: d3141c@gmail.com
- **저장소**: Private Git Server (ssh://miles@server/home/miles/git/sitemanager.git)

## 지원

문제가 있거나 기능 요청이 있으시면 이메일로 연락해 주세요.
