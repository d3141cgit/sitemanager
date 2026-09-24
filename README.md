# SiteManager for Laravel

Laravel용 사이트 관리 패키지입니다. 관리자 시스템, 게시판, 회원 관리 등 웹사이트 기본 기능을 패키지화하여 여러 프로젝트에서 재사용할 수 있습니다.

## ✨ 주요 기능

- 🛡 Admin Dashboard, 회원 관리, 권한 시스템
- 📝 **게시판 시스템**: 다중 게시판, 댓글, 파일 업로드
- 👥 **회원 관리**: 그룹 관리, 권한 시스템, 프로필 관리
- 🧭 **메뉴 관리**: 계층형 메뉴 구조로 사이트 네비게이션 구성
- 📦 **패키지 시스템**: Laravel 패키지로 개발되어 재사용 가능

## 📋 시스템 요구사항

- PHP ^8.1
- Laravel ^10.0|^11.0|^12.0
- MySQL
- Composer

## 🚀 설치 방법

### 📦 Production 설치 (Vendor 방식)

일반적인 Laravel 프로젝트에서 사용하는 방법입니다.

```bash
# 1. Laravel 프로젝트 생성
composer create-project laravel/laravel my-website
cd my-website

# 2. 패키지 설치
composer require d3141cgit/sitemanager:dev-main

# 3. 환경 설정 (.env 파일에서 데이터베이스 설정)
cp .env.example .env
php artisan key:generate
php artisan storage:link

# AUTH_MODEL 설정 추가
echo "AUTH_MODEL=SiteManager\Models\Member" >> .env

# 4. 🎯 SiteManager 설치 (통합 설치 명령어)
php artisan sitemanager:install

# 5. 관리자 계정 생성
php artisan sitemanager:admin

# 6. 개발 서버 시작
php artisan serve
```

#### 🎯 자동화된 설치

`sitemanager:install` 명령어가 다음을 자동으로 처리합니다:
- 기존 Laravel 마이그레이션 백업
- SiteManager 설정 파일 발행
- 데이터베이스 마이그레이션 실행
- 관리자 이미지 발행
- 홈 라우트 자동 설정

### 🔧 Development 설치 (Path Repository 방식)

패키지 개발을 위한 로컬 개발 환경 설정입니다.

```bash
# 1. SiteManager 저장소 클론
git clone https://github.com/d3141cgit/sitemanager.git
cd sitemanager

# 2. 새 Laravel 프로젝트 생성
cd projects
composer create-project laravel/laravel example.com
cd example.com

# 3. 로컬 패키지 경로 추가
composer config repositories.sitemanager path ../../packages/sitemanager

# 4. 로컬 패키지 설치
composer require d3141cgit/sitemanager:dev-main

# 5. 환경 설정
cp .env.example .env
php artisan key:generate
php artisan storage:link
echo "AUTH_MODEL=SiteManager\Models\Member" >> .env

# 6. 🎯 SiteManager 설치
php artisan sitemanager:install

# 7. 관리자 계정 생성
php artisan sitemanager:admin

# 8. 개발 서버 시작
php artisan serve
```

#### �️ 개발자 유용 명령어

- `php artisan resource clear` - 리소스 캐시 정리
- `php artisan resource build` - 프로덕션 빌드
- `php artisan view:clear` - 뷰 캐시 정리

## 📁 개발 환경 구조

### Path Repository 방식의 구조

```
sitemanager/
├── packages/sitemanager/     # 📦 패키지 소스코드
│   ├── src/                  # PHP 클래스들
│   ├── resources/            # 뷰, CSS, JS
│   └── composer.json         # 패키지 설정
├── projects/                 # 🧪 테스트 프로젝트들
│   ├── example.com/          # 새로 생성한 Laravel 앱
│   └── test.com/             # 기존 테스트 앱
└── docs/                     # � 문서 및 설정
```

### Path Repository 방식의 장점

1. **실시간 개발**: 패키지 코드 수정 즉시 프로젝트에 반영
2. **디버깅 용이**: 패키지 내부 코드 직접 수정 가능  
3. **버전 관리**: Git으로 패키지와 프로젝트 별도 관리
4. **배포 준비**: 완료 후 쉽게 공개 저장소로 이동 가능

### Composer 설정

**개발용 composer.json**:
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../../packages/sitemanager"
        }
    ],
    "require": {
        "d3141cgit/sitemanager": "dev-main"
    }
}
```

- `"type": "path"`: 로컬 디렉토리를 패키지로 사용
- `"url": "../../packages/sitemanager"`: 상대 경로로 패키지 위치 지정
- `"dev-main"`: 개발 브랜치를 직접 사용

**배포용 composer.json**:
```json
{
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/d3141cgit/sitemanager.git"
        }
    ],
    "require": {
        "d3141cgit/sitemanager": "dev-main"
    }
}
```


## 패키지 구조
```
packages/sitemanager/
├── src/                       # PHP 소스코드
│   ├── SiteManagerServiceProvider.php
│   ├── Console/              # Artisan 명령어
│   ├── Http/Controllers/     # 컨트롤러 (Admin/Auth)
│   ├── Models/               # Eloquent 모델
│   ├── Services/             # 서비스 레이어
│   └── Repositories/         # 리포지토리 패턴
├── resources/                 # 프론트엔드 리소스
│   ├── views/                # Blade 템플릿
│   ├── css/                  # CSS 파일
│   └── js/                   # JavaScript 파일
├── config/                    # 설정 파일
├── database/migrations/       # 마이그레이션
└── routes/                    # 라우트 정의
```

## 📖 사용법

### 통합 설치 명령어

```bash
# 모든 설정을 자동으로 처리하는 통합 설치
php artisan sitemanager:install

# 관리자 계정 생성
php artisan sitemanager:admin
```

### 주요 명령어

```bash
# 관리자 계정 생성 (대화형)
php artisan sitemanager:admin

# 관리자 계정 생성 (옵션 사용)
php artisan sitemanager:admin --name="Admin" --email="admin@test.com" --password="password123"

# 리소스 관리
php artisan resource clear     # 리소스 캐시 정리
php artisan resource build     # 프로덕션 빌드
```

### 접속 주소

- **홈페이지**: `http://yoursite.com/`
- **관리자**: `http://yoursite.com/sitemanager/dashboard`
- **로그인**: `http://yoursite.com/login`

### 리소스 로드

```
{{-- 패키지 CSS/JS 로드 --}}
{!! resource('sitemanager::css/app.css') !!}
{!! resource('sitemanager::js/app.js') !!}
```

## ⚙️ 주요 설정

### 환경 변수 (.env)

```bash
# Member 모델 사용 (필수)
AUTH_MODEL=SiteManager\Models\Member

# 데이터베이스 설정
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 설정 파일

설치 후 생성되는 주요 설정 파일들:
- `config/sitemanager.php` - 메인 설정
- `config/member.php` - 회원 관련 설정
- `config/menu.php` - 메뉴 관련 설정
- `config/permissions.php` - 권한 관련 설정

### 관리자 다크 테마

관리자 패널에 다크 테마를 켤 수 있다. **기본은 꺼짐**이다 — 사이트별 확장 관리자 화면이나
`resources/css/sitemanager.css` 가 흰색을 고정값으로 쓰고 있으면 다크에서 깨지므로, 다크를
고려한 사이트만 켠다. 꺼져 있으면 레이아웃 HTML 이 전혀 바뀌지 않는다.

```env
SITEMANAGER_DARK_MODE=true
SITEMANAGER_THEME_KEY=sitemanager-theme   # localStorage 키
SITEMANAGER_THEME_DEFAULT=light           # 저장값이 없을 때: light | dark | system
```

설정을 퍼블리시했다면 `config/sitemanager.php` 에 최상위 키 `admin_theme` 으로 둔다
(`mergeConfigFrom` 은 최상위 키만 합치므로 `ui` 안에 넣으면 적용되지 않는다).

- 켜면 사이드바 하단에 테마 전환 버튼이 생기고, `<html>` 에 `data-bs-theme`·`data-theme` 이 붙는다.
- Bootstrap 5.3 컴포넌트는 그것만으로 어두워진다. 패키지 화면의 고정 색은
  `css/sitemanager/sitemanager-dark.css` 가 맞춘다(중성 차콜 톤).
- **사이트 프론트의 테마 토글과 연동**하려면 `SITEMANAGER_THEME_KEY` 를 그 사이트가 쓰는
  localStorage 키로 맞춘다(예: GIO `gio-theme`). 관리자에서 바꾸면 프론트도 함께 바뀐다.
- 사이트 확장 화면의 보정은 사이트의 `resources/css/sitemanager.css` 에서
  `[data-bs-theme="dark"] …` 로 덮는다. 이 파일은 패키지 다크 CSS 뒤에 로드된다.

## 🎭 게시판 스킨 시스템

게시판별로 다른 스킨을 적용할 수 있습니다:

### 뷰 우선순위

게시판 `skin` 필드가 `gallery`인 경우:
1. `resources/views/board/gallery/index.blade.php` (프로젝트 스킨)
2. `sitemanager::board.gallery.index` (패키지 스킨)
3. `resources/views/board/index.blade.php` (프로젝트 기본)
4. `sitemanager::board.index` (패키지 기본)

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
composer update d3141cgit/sitemanager
```

### 라이센스
MIT License

### 문의
d3141c@gmail.com

**마지막 업데이트**: 2025년 8월 21일
