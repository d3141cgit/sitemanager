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

```bash
# SiteManager 패키지 설치
composer require d3141cgit/sitemanager:dev-main
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

**설치 과정에서 자동으로 처리되는 작업:**
- ✅ 기존 Laravel 마이그레이션 백업 (`database/migrations.backup/`)
- ✅ SiteManager 설정 파일 발행
- ✅ 데이터베이스 마이그레이션 실행 (vendor 디렉토리에서 직접 실행)
- ✅ 언어 데이터 복원
- ✅ 사이트매니저용 이미지 발행 (`public/images/`)
- ✅ 홈 라우트 자동 설정 (`routes/web.php` 백업 후 재생성)

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

###  뷰 템플릿 커스터마이징

```bash
# SiteManager 뷰 파일 발행 (프론트엔드 커스터마이징)
php artisan vendor:publish --tag=sitemanager-views
```

**발행되는 위치**: `resources/views/vendor/sitemanager/`  
**포함 파일들**: 
- 메인 페이지 템플릿
- 회원가입/로그인 페이지
- 게시판 목록/상세 페이지
- 관리자 패널 뷰

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

### ❌ 설치 중 오류 발생

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

## 📞 지원

설치나 사용 중 문제가 발생하면:

1. **문서 확인**: `packages/sitemanager/docs/` 디렉토리의 상세 가이드
2. **로그 확인**: `storage/logs/laravel.log`
3. **권한 확인**: 파일/폴더 권한 설정
4. **환경 확인**: PHP, Laravel, MySQL 버전

---

이제 SiteManager가 설치된 Laravel 프로젝트에서 웹사이트 개발을 시작할 수 있습니다! 🎉