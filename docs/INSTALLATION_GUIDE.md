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

## �📞 지원

설치나 사용 중 문제가 발생하면:

1. **문서 확인**: `packages/sitemanager/docs/` 디렉토리의 상세 가이드
2. **로그 확인**: `storage/logs/laravel.log`
3. **권한 확인**: 파일/폴더 권한 설정
4. **환경 확인**: PHP, Laravel, MySQL 버전

---
