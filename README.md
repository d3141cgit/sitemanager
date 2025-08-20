# SiteManager Package

Laravel용 사이트 관리 패키지입니다. 관리자 시스템을 패키지화하여 여러 프로젝트에서 재사용할 수 있도록 만들었습니다.

## 📁 패키지 개발 구조

### 🎯 **현재 개발 방식 (Path Repository)**
일반적으로 Composer 패키지는 `vendor/` 폴더에 설치되지만, **개발 중인 패키지**의 경우 다음과 같은 구조로 개발합니다:

```
sitemanager/
├── packages/                    # 📦 개발 중인 패키지들
│   └── sitemanager/            # 실제 패키지 소스코드
│       ├── composer.json       # 패키지 정의
│       ├── src/                # 패키지 소스
│       ├── resources/          # 뷰, CSS, JS 등
│       ├── config/             # 설정 파일들
│       └── database/           # 마이그레이션
└── projects/                   # 🚀 패키지를 사용하는 프로젝트들
    └── example.com/       # Laravel 프로젝트
        ├── composer.json       # Path Repository 설정
        └── vendor/             # 심링크로 연결된 패키지
            └── d3141c/
                └── sitemanager -> ../../../packages/sitemanager
```

### 🔗 **Path Repository 방식의 장점**

1. **실시간 개발**: 패키지 코드 수정 즉시 프로젝트에 반영
2. **디버깅 용이**: 패키지 내부 코드 직접 수정 가능  
3. **버전 관리**: Git으로 패키지와 프로젝트 별도 관리
4. **배포 준비**: 완료 후 쉽게 공개 저장소로 이동 가능

### ⚙️ **Composer 설정**

**프로젝트의 composer.json**:
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../../packages/sitemanager"
        }
    ],
    "require": {
        "d3141c/sitemanager": "dev-main"
    }
}
```

- `"type": "path"`: 로컬 디렉토리를 패키지로 사용
- `"url": "../../packages/sitemanager"`: 상대 경로로 패키지 위치 지정
- `"dev-main"`: 개발 브랜치를 직접 사용

### 🎯 **최종 배포 시에는**

개발 완료 후에는 다음과 같이 전환됩니다:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/d3141c/sitemanager"
        }
    ],
    "require": {
        "d3141c/sitemanager": "^1.0"
    }
}
```

그러면 일반적인 `vendor/d3141c/sitemanager/` 경로에 설치됩니다.

## � 패키지 내부 구조

### 🏗️ **디렉토리 구조**
```
packages/sitemanager/
├── composer.json              # 패키지 정의 및 의존성
├── README.md                  # 패키지 문서
├── config/                    # 📁 설정 파일들
│   ├── sitemanager.php       # 메인 설정
│   ├── member.php            # 회원 설정
│   ├── menu.php              # 메뉴 설정
│   └── permissions.php       # 권한 설정
├── database/                  # 📁 데이터베이스
│   └── migrations/           # 마이그레이션 파일들
├── resources/                 # 📁 프론트엔드 리소스
│   ├── views/                # Blade 템플릿
│   │   ├── admin/           # 관리자 뷰 (완전 제공)
│   │   ├── auth/            # 인증 뷰 (스타터)
│   │   ├── board/           # 게시판 뷰 (스타터)
│   │   └── user/            # 사용자 뷰 (스타터)
│   ├── assets/              # 이미지, 폰트 등
│   ├── css/                 # CSS 파일
│   └── js/                  # JavaScript 파일
├── routes/                    # 📁 라우트 정의
│   ├── admin.php            # 관리자 라우트
│   └── web.php              # 웹 라우트
└── src/                       # 📁 PHP 소스코드
    ├── SiteManagerServiceProvider.php  # 서비스 프로바이더
    ├── Console/             # Artisan 명령어들
    ├── Http/                # 컨트롤러, 미들웨어
    │   ├── Controllers/     # 컨트롤러
    │   │   ├── Admin/      # 관리자 컨트롤러
    │   │   └── Auth/       # 인증 컨트롤러
    │   └── Middleware/      # 미들웨어
    ├── Models/              # Eloquent 모델들
    ├── Services/            # 서비스 레이어
    ├── Repositories/        # 리포지토리 패턴
    ├── Helpers/             # 헬퍼 함수들
    └── View/                # 뷰 컴포넌트들
```

### 🎯 **핵심 설계 원칙**

1. **관리자 기능 = 완전 제공**
   - `/admin` 모든 기능이 패키지에서 완성된 형태로 제공
   - 사용자는 설정만으로 바로 사용 가능

2. **프론트엔드 = 스타터 템플릿**
   - 기본 레이아웃과 템플릿을 제공
   - 각 프로젝트에서 커스터마이징 전제

3. **네임스페이스 분리**
   - 패키지: `SiteManager\*`
   - 프로젝트: `App\*`
   - 뷰: `sitemanager::*`

## 패키지 구성

### 🎯 **핵심 기능 (모든 프로젝트 공통)**
- **관리자 시스템**: 완전한 Admin Dashboard
- **게시판 시스템**: 다중 게시판, 댓글, 파일 업로드
- **회원 관리**: 그룹 관리, 권한 시스템
- **메뉴 관리**: 계층형 메뉴 구조

### 🎨 **스타터 템플릿 (선택적)**
- **기본 레이아웃**: 프런트엔드 시작점
- **인증 뷰**: 로그인 템플릿
- **게시판 뷰**: 기본 게시판 템플릿

> **💡 개발 철학**: Admin 기능은 패키지에서 완전히 제공하고, 프런트엔드는 스타터 템플릿에서 시작하여 각 프로젝트별로 커스터마이징

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
- MySQL
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

> **⚠️ 중요:** 이 패키지는 현재 개발 버전만 제공됩니다. 안정된 릴리스가 없어 명시적으로 `dev-main` 버전을 지정해야 합니다.

### 📦 방법 1: Private Git Server (권장)

```bash
# 1. 새 Laravel 프로젝트 생성
composer create-project laravel/laravel your-project-name

# 2. 프로젝트 디렉토리로 이동
cd your-project-name

# 3. Git 저장소 등록 (로컬 접속)
composer config repositories.sitemanager vcs ssh://miles@server/home/miles/git/sitemanager.git

# 또는 외부 접속
composer config repositories.sitemanager vcs ssh://miles@d3141c.ddns.net/home/miles/git/sitemanager.git

# 4. 패키지 설치
composer require d3141c/sitemanager:dev-main

# 5. 설정 파일 및 자원 발행
php artisan vendor:publish --provider="SiteManager\SiteManagerServiceProvider"

# 6. 데이터베이스 마이그레이션
php artisan migrate

# 7. 관리자 계정 생성
php artisan sitemanager:admin
```

### 📦 방법 2: 로컬 패키지 (현재 개발 구조)

```bash
# 1. 워크스페이스 구조 생성
mkdir sitemanager-workspace && cd sitemanager-workspace
mkdir packages projects

# 2. 패키지 클론 (개발용)
cd packages
git clone [sitemanager-repo] sitemanager

# 3. 새 프로젝트 생성
cd ../projects  
composer create-project laravel/laravel your-project-name
cd your-project-name

# 4. composer.json에 로컬 패키지 등록
{
    "repositories": [
        {
            "type": "path",
            "url": "../../packages/sitemanager"
        }
    ],
    "require": {
        "d3141c/sitemanager": "dev-main"
    }
}

# 5. 패키지 설치
composer require d3141c/sitemanager:dev-main --prefer-source

# 6. 관리자 전용 설치 (홈 라우트 자동 설정 포함)
php artisan sitemanager:install

# 또는 스타터 템플릿 포함 설치
php artisan sitemanager:install --with-starter
```
```

### 🚀 빠른 설치 (일괄 설치)

```bash
# 설정, 마이그레이션, 자원 발행, 홈 라우트 설정을 한 번에
php artisan sitemanager:install

# 관리자 계정 생성 (대화형)
php artisan sitemanager:admin

# 또는 옵션으로 직접 생성
php artisan sitemanager:admin --name="Admin" --email="admin@example.com" --password="password123"
```

### � 설치 트러블슈팅

**패키지 버전 오류가 발생하는 경우:**
```bash
# 오류: Could not find a version of package d3141c/sitemanager matching your minimum-stability (stable)
# 해결: 명시적으로 dev 버전 지정
composer require d3141c/sitemanager:dev-main --prefer-source
```

**composer.json 중복 require 섹션 오류:**
```bash
# 오류: composer.json에 require 섹션이 중복되어 있는 경우
# 해결: composer.json을 수정하여 require 섹션을 하나로 통합
composer update
```

### �📁 발행되는 파일들

설치 시 다음 파일들이 프로젝트에 복사됩니다:

**설정 파일:**
- `config/sitemanager.php` - 메인 설정
- `config/member.php` - 회원 관련 설정  
- `config/menu.php` - 메뉴 관련 설정
- `config/permissions.php` - 권한 관련 설정

**뷰 파일:**
- `resources/views/vendor/sitemanager/` - 모든 뷰 템플릿

**CSS/JS 리소스:** (개발용)
- `resources/css/` - CSS 파일들 (패키지에서 복사)
- `resources/js/` - JavaScript 파일들 (패키지에서 복사)

**Admin 기본 이미지:**
- `public/images/sitemanager.svg` - Admin 패널 로고

**뷰 파일:** (스타터 템플릿 선택시)
- `resources/views/vendor/sitemanager/` - 패키지 뷰 (참조용)
- `resources/views/layouts/app.blade.php` - 기본 레이아웃
- `resources/views/auth/` - 인증 뷰들
- `resources/views/board/` - 게시판 뷰들
- `resources/views/user/` - 사용자 뷰들

**DB 마이그레이션:**
- `database/migrations/` - 데이터베이스 스키마

**Public 자원:**
- `public/vendor/sitemanager/` - 개발용 에셋 (선택사항)

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
# 🚀 패키지 설치 (관리자 기능만)
php artisan sitemanager:install

# 🎨 패키지 설치 + 스타터 템플릿 발행
php artisan sitemanager:install --with-starter

# 📁 스타터 템플릿만 별도 발행 (기존 설치에 추가)
php artisan sitemanager:publish-starter

# 📁 스타터 템플릿 + 인증 뷰 + 기본 라우트
php artisan sitemanager:publish-starter --auth --routes

# 👤 관리자 계정 생성 (대화형)
php artisan sitemanager:admin

# 👤 관리자 계정 생성 (옵션 사용)
php artisan sitemanager:admin --name="Admin" --email="admin@test.com" --password="password123"

# ☁️ S3 연결 테스트
php artisan sitemanager:test-s3

# ☁️ S3 설정 확인
php artisan sitemanager:check-s3

# 📸 이미지를 S3로 마이그레이션
php artisan sitemanager:migrate-images-s3
```

### 🎯 설치 방식 선택

**방식 1: 관리자만 사용 (권장 - 운영 사이트)**
```bash
php artisan sitemanager:install
# ✅ 관리자 대시보드와 API만 설치
# ✅ 프런트엔드는 완전히 별도 개발
```

**방식 2: 스타터 템플릿 포함 (개발/프로토타입)**
```bash
php artisan sitemanager:install --with-starter
# ✅ 관리자 + 기본 템플릿 제공
# ✅ resources/views/에 템플릿 복사되어 커스터마이징 가능
```

### 접속 및 사용

설치가 완료되면 다음 주소로 접속할 수 있습니다:

- **홈페이지**: `http://yoursite.com/` (sitemanager::main 뷰 자동 설정)
- **관리자 대시보드**: `http://yoursite.com/admin/dashboard`
- **로그인**: `http://yoursite.com/login`
- **게시판**: `http://yoursite.com/board/{slug}`

## 📦 패키지 리소스 시스템

SiteManager는 패키지 리소스를 효율적으로 관리할 수 있는 시스템을 제공합니다.

### 🎨 리소스 사용법

```blade
{{-- 패키지 CSS 로드 --}}
{!! resource('sitemanager::css/admin/admin.css') !!}

{{-- 패키지 JavaScript 로드 --}}
{!! resource('sitemanager::js/admin/admin.js') !!}

{{-- 프로젝트 리소스와 혼용 가능 --}}
{!! resource('css/custom.css') !!}
```

### 🚀 리소스 관리 명령어

```bash
# 현재 리소스 상태 확인
php artisan resource status

# 프로덕션용 리소스 빌드
php artisan resource build --build-version=v1.0.0

# 리소스 캐시 정리
php artisan resource clear

# 오래된 리소스 파일 정리
php artisan resource cleanup
```

### 🔄 개발 vs 프로덕션

**개발 환경**:
- 리소스가 실시간으로 처리됨
- 패키지 파일이 `storage/app/public/assets/`에 복사됨

**프로덕션 환경**:
- `php artisan resource build`로 최적화된 파일 생성
- 빌드된 파일이 `public/assets/`에 저장됨
- 버전 관리 및 캐싱 지원

## 🎭 게시판 스킨 시스템

SiteManager는 게시판별로 다른 스킨을 적용할 수 있는 동적 뷰 시스템을 제공합니다.

### 📁 스킨 디렉토리 구조

```
resources/views/board/
├── default/              # 기본 스킨 (선택사항)
├── gallery/             # 갤러리 스킨
│   ├── index.blade.php  # 게시글 목록
│   ├── show.blade.php   # 게시글 상세
│   ├── form.blade.php   # 작성/수정 폼
│   └── partials/        # 부분 템플릿
│       ├── comment.blade.php
│       └── comments.blade.php
└── blog/                # 블로그 스킨
    ├── index.blade.php
    ├── show.blade.php
    └── form.blade.php
```

### 🎯 뷰 우선순위

게시판의 `skin` 필드가 `gallery`인 경우:

1. `resources/views/board/gallery/index.blade.php` (프로젝트 스킨 뷰)
2. `sitemanager::board.gallery.index` (패키지 스킨 뷰)  
3. `resources/views/board/index.blade.php` (프로젝트 기본 뷰)
4. `sitemanager::board.index` (패키지 기본 뷰)

### ⚙️ 스킨 설정

```php
// 게시판 생성 시 스킨 지정
$board = Board::create([
    'name' => '포토갤러리',
    'slug' => 'gallery',
    'skin' => 'gallery',  // 스킨 지정
    // ...
]);
```

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
# Private Git Server에서 업데이트 (로컬)
composer update d3141c/sitemanager

# 또는 외부에서 접속 시
composer config repositories.sitemanager vcs ssh://miles@d3141c.ddns.net/home/miles/git/sitemanager.git
composer update d3141c/sitemanager

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
composer require d3141c/sitemanager:dev-main#abc1234

# 최신 버전으로 업데이트
composer require d3141c/sitemanager:dev-main
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
composer update d3141c/sitemanager
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
composer update d3141c/sitemanager

cd /path/to/project2  
composer update d3141c/sitemanager

# 필요시 새로운 마이그레이션이나 설정 발행
php artisan migrate
php artisan vendor:publish --provider="SiteManager\SiteManagerServiceProvider" --force
```

### 🛠️ 개발 시 유용한 명령어

```bash
# 패키지를 심볼릭 링크로 설치 (개발용)
composer require d3141c/sitemanager --prefer-source

# 패키지를 실제 파일로 설치 (운영용)
composer require d3141c/sitemanager --prefer-dist

# 특정 커밋으로 설치
composer require d3141c/sitemanager:dev-main#abc1234

# 캐시 강제 새로고침
composer clear-cache
composer update d3141c/sitemanager --no-cache
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

# 공개키를 서버에 등록 (로컬)
ssh-copy-id miles@server

# 공개키를 서버에 등록 (외부)
ssh-copy-id miles@d3141c.ddns.net

# 연결 테스트 (로컬)
ssh miles@server

# 연결 테스트 (외부)
ssh miles@d3141c.ddns.net
```

## 🔧 개발 워크플로우

### 📋 **현재 개발 환경**

```bash
# 현재 작업 중인 구조
/Users/songhyundong/www/sitemanager/
├── packages/sitemanager/           # 📦 패키지 개발
└── projects/hanurichurch.org/      # 🧪 테스트 프로젝트
```

### 🚀 **개발 사이클**

1. **패키지 수정**
   ```bash
   cd /Users/songhyundong/www/sitemanager/packages/sitemanager
   # 코드 수정...
   ```

2. **즉시 테스트**
   ```bash
   cd /Users/songhyundong/www/sitemanager/projects/hanurichurch.org
   php artisan serve
   # 변경사항이 즉시 반영됨 (Path Repository 장점)
   ```

3. **패키지 커밋**
   ```bash
   cd /Users/songhyundong/www/sitemanager/packages/sitemanager
   git add .
   git commit -m "기능 추가/수정"
   git push origin main
   ```

### 🔄 **프로젝트에서 패키지 업데이트**

```bash
# 프로젝트에서 패키지 최신 버전으로 업데이트
cd /Users/songhyundong/www/sitemanager/projects/hanurichurch.org
composer update d3141c/sitemanager

# 새로운 마이그레이션이 있다면
php artisan migrate

# 새로운 설정/뷰가 있다면
php artisan vendor:publish --provider="SiteManager\SiteManagerServiceProvider" --force
```

### 📝 **네임스페이스 컨벤션**

- **패키지 PHP 클래스**: `SiteManager\*`
- **패키지 뷰**: `sitemanager::*`
- **패키지 라우트**: `sitemanager.*`
- **프로젝트 클래스**: `App\*`

### 🎯 **개발 시 주의사항**

1. **뷰 네임스페이스**: 모든 패키지 뷰는 `sitemanager::` 접두사 사용
2. **라우트 이름**: 패키지 라우트는 `sitemanager.` 접두사 사용
3. **설정 파일**: 패키지 설정은 `config/sitemanager.php` 등 별도 파일로 분리
4. **asset 경로**: 패키지 리소스는 `resource()` 헬퍼 함수 사용

### 🏗️ **새 프로젝트에 패키지 적용**

```bash
# 1. 새 Laravel 프로젝트 생성
composer create-project laravel/laravel new-project

# 2. 패키지 등록 (Path Repository)
cd new-project
composer config repositories.sitemanager path ../../packages/sitemanager

# 3. 패키지 설치
composer require d3141c/sitemanager:dev-main

# 4. 설치 및 설정
php artisan sitemanager:install --with-starter
php artisan migrate
php artisan sitemanager:admin

# 5. 개발 서버 시작
php artisan serve
```

## 의존성

- `kalnoy/nestedset` - 계층형 메뉴 관리
- `intervention/image` - 이미지 처리
- `aws/aws-sdk-php` - 파일 저장소 (선택적)

## 라이센스

MIT License

## 연락처

- **개발자**: Songhyun Dong (d3141c)
- **이메일**: d3141c@gmail.com
- **저장소**: 
  - 로컬: ssh://miles@server/home/miles/git/sitemanager.git
  - 외부: ssh://miles@d3141c.ddns.net/home/miles/git/sitemanager.git

## 📋 최근 업데이트 (v2025.08.19)

### ✅ 주요 변경사항

#### 🗂️ **구조 정리**
- **API 라우트 제거**: `routes/api.php` 삭제 (중복 기능 정리)
- **UserController 제거**: 프로젝트별 구현으로 변경
- **ExamplePostController 제거**: 불필요한 예제 코드 정리

#### 🎨 **패키지 리소스 시스템 구축**
- **동적 리소스 로딩**: `resource('sitemanager::css/admin/admin.css')` 지원
- **개발/프로덕션 분리**: 개발 시 실시간, 프로덕션 시 빌드된 파일 사용
- **리소스 관리 명령어**: `php artisan resource build/clear/status/cleanup`
- **자동 최적화**: 파일 해시, 버전 관리, 캐싱 지원

#### 🎭 **게시판 스킨 시스템**
- **동적 뷰 선택**: 게시판 `skin` 필드에 따른 자동 뷰 선택
- **우선순위 기반**: 프로젝트 스킨 → 패키지 스킨 → 기본 뷰 순서
- **계층형 구조**: `board/{skin}/index.blade.php` 형태의 디렉토리 구조
- **댓글 템플릿**: 스킨별 댓글 템플릿도 지원

#### 🏠 **자동 홈페이지 설정**
- **welcome 라우트 교체**: Laravel 기본 `welcome` 뷰를 `sitemanager::main`으로 자동 변경
- **스마트 감지**: 기존 홈 라우트 존재 시 건너뛰기
- **즉시 사용 가능**: 설치 후 바로 SiteManager 메인 페이지 표시

#### ⚡ **설치 프로세스 개선**
- **원클릭 설치**: `php artisan sitemanager:install`로 모든 설정 완료
- **자동 라우트 설정**: 홈페이지 라우트 자동 구성
- **불필요한 복사 제거**: CSS/JS 파일을 프로젝트로 복사하지 않음

### 🚀 **향후 계획**
- [ ] 다중 테마 시스템 확장
- [ ] 컴포넌트 기반 뷰 시스템
- [ ] API 패키지 분리 (별도 패키지)
- [ ] 플러그인 시스템 구축

---

## 지원

**📝 마지막 업데이트**: 2025년 8월 19일  
**📧 문의**: d3141c@gmail.com

문제가 있거나 기능 요청이 있으시면 이메일로 연락해 주세요.
