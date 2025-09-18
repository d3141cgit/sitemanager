# SiteManager 설치 가이드

Laravel 프로젝트에서 SiteManager 패키지를 설치하여 사용하는 방법입니다.

## 📋 시스템 요구사항

- PHP ^8.1
- Laravel ^10.0|^11.0|^12.0
- MySQL 5.7+ 또는 MariaDB 10.3+
- Composer 2.0+

## 🚀 설치 방법

### 1️⃣ Laravel 프로젝트 생성

```bash
# 새로운 Laravel 프로젝트 생성
composer create-project laravel/laravel my-website
cd my-website
```

### 2️⃣ SiteManager 패키지 설치

#### 방법 1: GitHub 저장소에서 직접 설치 (권장)

```bash
# composer.json에 저장소 정보 추가
composer config repositories.sitemanager vcs https://github.com/d3141cgit/sitemanager

# SiteManager 패키지 설치
composer require d3141cgit/sitemanager:dev-main
```

#### 방법 2: composer.json 수동 편집

`composer.json` 파일을 열어서 다음 내용을 추가하세요:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/d3141cgit/sitemanager"
        }
    ],
    "require": {
        "d3141cgit/sitemanager": "dev-main"
    }
}
```

그 후 설치 실행:

```bash
composer install
```

### 3️⃣ 환경 설정

```bash
# 환경 설정 파일 준비
cp .env.example .env
php artisan key:generate
php artisan storage:link
```

#### 📝 .env 파일 설정

```env
# 데이터베이스 설정
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password

# SiteManager 인증 모델 설정 (필수)
AUTH_MODEL=SiteManager\Models\Member

# 파일 업로드 설정 (선택사항 - S3 사용시)
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=ap-northeast-2
AWS_BUCKET=your-bucket-name
AWS_URL_STYLE=virtual-hosted
```

### 4️⃣ SiteManager 설치 실행

```bash
# 통합 설치 명령어 실행
php artisan sitemanager:install
```

**설치 과정에서 자동으로 처리되는 작업 (순서대로):**
1. ✅ 기존 Laravel 마이그레이션 백업 (`database/migrations.backup/`)
2. ✅ SiteManager 설정 파일 발행
3. ✅ **데이터베이스 마이그레이션 실행** (vendor 디렉토리에서 직접 실행)
4. ✅ **언어 데이터 복원** (테이블 생성 후)
5. ✅ 사이트매니저용 이미지 발행 (`public/images/`)
6. ✅ 홈 라우트 자동 설정 (`routes/web.php` 백업 후 재생성)

> **⚠️ 중요**: 마이그레이션이 완료된 후에 언어 데이터가 복원됩니다. `languages` 테이블이 먼저 생성되어야 하기 때문입니다.

### 5️⃣ 관리자 계정 생성

```bash
# 관리자 계정 생성
php artisan sitemanager:admin
```

**입력 정보:**
- 관리자 이름
- 이메일 주소
- 비밀번호
- 비밀번호 확인

### 6️⃣ 개발 서버 시작

```bash
# Laravel 개발 서버 시작
php artisan serve
```

## 🎯 설치 완료 후 확인

### 📱 **프론트엔드 접속**
- **홈페이지**: http://localhost:8000
- **회원가입**: http://localhost:8000/register
- **로그인**: http://localhost:8000/login

### 🛡️ **관리자 패널 접속**
- **관리자 로그인**: http://localhost:8000/sitemanager/login
- **관리자 대시보드**: http://localhost:8000/sitemanager/dashboard

## 🔧 추가 설정 (선택사항)

###  뷰 템플릿 커스터마이징 (고급 사용자용)

```bash
# SiteManager 뷰 파일 발행 (고급 커스터마이징 시에만 필요)
php artisan vendor:publish --tag=sitemanager-views
```

**⚠️ 주의사항:**
- 기본 뷰로도 충분히 사용 가능합니다
- 발행 후에는 패키지 업데이트 시 수동 머지 필요
- **디자인 커스터마이징이 꼭 필요한 경우에만** 사용하세요

**발행되는 위치**: `resources/views/vendor/sitemanager/`  
**포함 파일들**: 
- 메인 페이지 템플릿
- 회원가입/로그인 페이지
- 게시판 목록/상세 페이지
- 관리자 패널 뷰 (총 52개 파일)

### 📦 리소스 관리

```bash
# CSS/JS 리소스 빌드 (프로덕션)
php artisan resource build

# 리소스 캐시 정리
php artisan resource clear
```

## 📁 프로젝트 구조

설치 후 주요 파일들의 위치:

```
my-website/
├── config/
│   ├── sitemanager.php          # SiteManager 기본 설정
│   ├── menu.php                 # 메뉴 설정
│   └── board.php                # 게시판 설정
├── database/
│   └── migrations.backup/       # 기존 Laravel 마이그레이션 백업
├── public/
│   └── images/                  # 사이트매니저용 이미지
├── routes/
│   ├── web.php                  # 새로 생성된 라우트
│   └── web.php.backup          # 기존 라우트 백업
└── resources/
    └── views/
        └── vendor/
            └── sitemanager/     # SiteManager 뷰 (발행시)
```

## 🚨 문제 해결

### 📦 패키지 설치 오류

#### "Package not found" 오류 해결

```bash
# 저장소 정보가 없는 경우
composer config repositories.sitemanager vcs https://github.com/d3141cgit/sitemanager
composer require d3141cgit/sitemanager:dev-main

# 또는 composer.json에 직접 추가
```

```json
{
    "repositories": [
        {
            "type": "vcs", 
            "url": "https://github.com/d3141cgit/sitemanager"
        }
    ]
}
```

#### GitHub 접근 권한 오류

```bash
# GitHub Personal Access Token 설정 (private 저장소인 경우)
composer config github-oauth.github.com YOUR_GITHUB_TOKEN

# 또는 SSH 키 사용
composer config repositories.sitemanager vcs git@github.com:d3141cgit/sitemanager.git
```

#### Composer 캐시 문제

```bash
# Composer 캐시 정리
composer clear-cache

# 저장소 재설정
composer config --unset repositories.sitemanager
composer config repositories.sitemanager vcs https://github.com/d3141cgit/sitemanager
```

### ❌ 설치 중 오류 발생

#### "Table 'languages' doesn't exist" 오류

```bash
# 언어 테이블 생성 전에 언어 데이터 복원을 시도한 경우
# 마이그레이션을 먼저 실행하세요

# 1. 마이그레이션만 실행
php artisan migrate --force

# 2. 언어 데이터 수동 복원
php artisan sitemanager:restore-languages

# 3. 또는 전체 재설치
php artisan sitemanager:install --force
```

#### 설치 프로세스 단계별 실행

```bash
# 설치가 중간에 실패한 경우 단계별로 실행 가능

# 1단계: 설정 파일 발행
php artisan vendor:publish --tag=sitemanager-config

# 2단계: 마이그레이션 실행  
php artisan migrate --force

# 3단계: 언어 데이터 복원
php artisan sitemanager:restore-languages

# 4단계: 이미지 발행
php artisan vendor:publish --tag=sitemanager-images

# 5단계: 라우트 설정
php artisan sitemanager:setup-routes
```

#### 강제 설치 옵션

```bash
# 강제 설치 (프로덕션 환경에서)
php artisan sitemanager:install --force

# 마이그레이션 수동 실행
php artisan migrate --force
```

### 🔄 설치 초기화

```bash
# 1. 백업에서 원본 파일 복원
mv database/migrations.backup/* database/migrations/
mv routes/web.php.backup routes/web.php

# 2. SiteManager 테이블 삭제 (주의!)
php artisan migrate:rollback --step=50

# 3. 재설치
php artisan sitemanager:install
```

### 🔑 인증 모델 오류

**.env 파일 확인:**
```env
# 이 설정이 필수입니다
AUTH_MODEL=SiteManager\Models\Member
```

**config/auth.php 확인:**
```php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => env('AUTH_MODEL', App\Models\User::class),
    ],
],
```

### 📂 파일 업로드 오류

```bash
# 스토리지 링크 재생성
php artisan storage:link

# 권한 설정 (Linux/Mac)
chmod -R 775 storage/
chmod -R 775 public/storage/
```

## 🎨 커스터마이징

### � 뷰 템플릿 커스터마이징

```bash
# 뷰 템플릿 발행
php artisan vendor:publish --tag=sitemanager-views

# 이후 resources/views/vendor/sitemanager/ 에서 편집
```

### 🔧 설정 변경

**config/sitemanager.php:**
```php
return [
    'admin_prefix' => 'admin',        // 관리자 URL 접두사
    'pagination' => 15,               // 페이지당 항목 수
    'upload_max_size' => 10240,       // 업로드 최대 크기 (KB)
    'allowed_extensions' => [         // 허용 확장자
        'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'
    ],
];
```

### 📋 게시판 추가

**config/board.php:**
```php
return [
    'boards' => [
        'notice' => [
            'name' => '공지사항',
            'slug' => 'notice',
            'description' => '중요한 공지사항을 확인하세요',
        ],
        'qna' => [
            'name' => 'Q&A',
            'slug' => 'qna', 
            'description' => '궁금한 것을 물어보세요',
        ],
        // 새 게시판 추가
        'gallery' => [
            'name' => '갤러리',
            'slug' => 'gallery',
            'description' => '사진과 영상을 공유하세요',
        ],
    ],
];
```

## 🚀 프로덕션 배포

### 1️⃣ 환경 설정

```bash
# 프로덕션 환경 변수
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

### 2️⃣ 최적화

```bash
# 설정 캐시
php artisan config:cache

# 라우트 캐시
php artisan route:cache

# 뷰 캐시
php artisan view:cache

# 리소스 빌드
php artisan resource build
```

### 3️⃣ 웹서버 설정

**Apache .htaccess** (이미 Laravel에 포함)

**Nginx 설정:**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/public;
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## � Packagist 등록 (개발자용)

SiteManager 패키지를 Packagist에 등록하면 더 쉽게 설치할 수 있습니다:

### 1️⃣ Packagist 등록 과정

1. **https://packagist.org** 에서 계정 생성
2. **"Submit"** 버튼 클릭
3. **GitHub 저장소 URL 입력**: `https://github.com/d3141cgit/sitemanager`
4. **Auto-update** 설정으로 GitHub과 연동

### 2️⃣ composer.json 최적화

패키지 루트의 `composer.json` 파일 확인:

```json
{
    "name": "d3141cgit/sitemanager",
    "description": "Laravel CMS Package for Content Management",
    "type": "laravel-package",
    "license": "MIT",
    "keywords": ["laravel", "cms", "content-management", "sitemanager"],
    "authors": [
        {
            "name": "d3141cgit",
            "email": "your-email@example.com"
        }
    ],
    "require": {
        "php": "^8.1",
        "laravel/framework": "^10.0|^11.0"
    },
    "autoload": {
        "psr-4": {
            "SiteManager\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "SiteManager\\SiteManagerServiceProvider"
            ]
        }
    }
}
```

### 3️⃣ 릴리즈 태그 생성

```bash
# 안정된 버전 태그 생성
git tag v1.0.0
git push origin v1.0.0

# Packagist에서 자동으로 감지하여 버전 업데이트
```

### 4️⃣ Packagist 등록 후 설치

등록 완료 후에는 간단하게 설치 가능:

```bash
# 저장소 정보 없이 바로 설치 가능
composer require d3141cgit/sitemanager

# 특정 버전 설치
composer require d3141cgit/sitemanager:^1.0
```

---

## �📞 지원

설치나 사용 중 문제가 발생하면:

1. **문서 확인**: `packages/sitemanager/docs/` 디렉토리의 상세 가이드
2. **로그 확인**: `storage/logs/laravel.log`
3. **권한 확인**: 파일/폴더 권한 설정
4. **환경 확인**: PHP, Laravel, MySQL 버전

---

이제 SiteManager가 설치된 Laravel 프로젝트에서 웹사이트 개발을 시작할 수 있습니다! 🎉