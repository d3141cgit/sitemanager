@extends('sitemanager::layouts.app')

@section('title', 'Welcome!')
@section('meta_description', 'Welcome to Site Manager')
@section('meta_keywords', 'Site Manager')

@section('content')
<!-- Default Welcome Content -->
<div class="welcome-section">
    <div class="container">
        <div class="welcome-header">
            <h1 class="welcome-title">
                <i class="bi bi-grid-3x3-gap-fill"></i>
                SiteManager Package
            </h1>
            <p class="welcome-subtitle">Laravel용 사이트 관리 패키지</p>
        </div>

        <div class="row g-4">
            <!-- 기능 소개 -->
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h3>관리자 시스템</h3>
                    <p>Admin Dashboard, 회원 관리, 권한 시스템 등 기본적인 관리 기능 포함</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-chat-square-text"></i>
                    </div>
                    <h3>게시판 시스템</h3>
                    <p>다중 게시판, 댓글, 파일 업로드 등 일반적인 게시판 기능 구현</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <h3>회원 관리</h3>
                    <p>그룹 관리, 권한 시스템, 프로필 관리 등 회원 운영에 필요한 기본 기능</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-menu-button-wide"></i>
                    </div>
                    <h3>메뉴 관리</h3>
                    <p>계층형 메뉴 구조로 사이트 네비게이션을 체계적으로 구성 가능</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-code-square"></i>
                    </div>
                    <h3>개발자 친화적</h3>
                    <p>Repository Pattern, Service Layer 등 현대적인 아키텍처 적용</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-puzzle"></i>
                    </div>
                    <h3>패키지 시스템</h3>
                    <p>Laravel 패키지로 개발되어 여러 프로젝트에서 재사용 가능</p>
                </div>
            </div>
        </div>

        <!-- 시작하기 섹션 -->
        <div class="getting-started">
            <h2>시작하기</h2>
            <div class="row g-4">
                <div class="col-12">
                    <div class="info-card">
                        <h4><i class="bi bi-terminal"></i> 설치 방법</h4>
                        
                        <!-- 설치 방법 탭 -->
                        <ul class="nav nav-tabs mb-3" id="installTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="vendor-tab" data-bs-toggle="tab" data-bs-target="#vendor" type="button" role="tab">
                                    <i class="bi bi-cloud-download"></i> Vendor 설치 (Production)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="package-tab" data-bs-toggle="tab" data-bs-target="#package" type="button" role="tab">
                                    <i class="bi bi-code-slash"></i> Package 개발 (Development)
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="installTabContent">
                            <!-- Vendor 설치 방법 -->
                            <div class="tab-pane fade show active" id="vendor" role="tabpanel">
                                <p class="text-muted mb-3">
                                    <i class="bi bi-info-circle"></i> 
                                    프로덕션 환경이나 일반적인 Laravel 프로젝트에서 사용하는 방법
                                </p>
                                <div class="code-block">
                                    <code># 1. 패키지 설치
composer require d3141c/sitemanager:dev-main

# 2. 설정 파일 발행
php artisan vendor:publish --provider="SiteManager\SiteManagerServiceProvider"

# 3. 데이터베이스 마이그레이션
php artisan migrate

# 4. 관리자 계정 생성
php artisan sitemanager:admin

# 5. Storage 심볼릭 링크 생성
php artisan storage:link

# 6. 서버 실행
php artisan serve</code>
                                </div>
                            </div>
                            
                            <!-- Package 개발 방법 -->
                            <div class="tab-pane fade" id="package" role="tabpanel">
                                <p class="text-muted mb-3">
                                    <i class="bi bi-info-circle"></i> 
                                    패키지 개발이나 기여를 위한 로컬 개발 환경 설정
                                </p>
                                <div class="code-block">
                                    <code># 1. SiteManager 저장소 클론
git clone https://github.com/d3141c/sitemanager.git
cd sitemanager

# 2. 패키지 의존성 설치
composer install

# 3. 새 Laravel 프로젝트 생성 (또는 기존 프로젝트 사용)
cd projects
composer create-project laravel/laravel example.com
cd example.com

# 4. composer.json에 로컬 패키지 경로 추가
# composer.json의 repositories 섹션에 추가:
# "repositories": [
#     {
#         "type": "path",
#         "url": "../../packages/sitemanager"
#     }
# ]

# 5. 로컬 패키지 설치
composer require d3141c/sitemanager:dev-main

# 6. 환경 설정 파일 복사 및 수정
cp .env.example .env
# .env 파일에서 데이터베이스 설정

# 7. 애플리케이션 키 생성
php artisan key:generate

# 8. 설정 파일 발행
php artisan vendor:publish --provider="SiteManager\SiteManagerServiceProvider"

# 9. 데이터베이스 마이그레이션
php artisan migrate

# 10. 관리자 계정 생성
php artisan sitemanager:admin

# 11. Storage 심볼릭 링크 생성
php artisan storage:link

# 12. 서버 실행
php artisan serve</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="info-card">
                        <h4><i class="bi bi-gear"></i> 시스템 요구사항</h4>
                        <ul class="requirement-list">
                            <li><i class="bi bi-check-circle"></i> PHP ^8.1</li>
                            <li><i class="bi bi-check-circle"></i> Laravel ^10.0|^11.0|^12.0</li>
                            <li><i class="bi bi-check-circle"></i> MySQL</li>
                            <li><i class="bi bi-check-circle"></i> Composer</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="info-card">
                        <h4><i class="bi bi-folder-plus"></i> 개발 환경 구조</h4>
                        <div class="code-block">
                            <code>sitemanager/
├── packages/sitemanager/     # 📦 패키지 소스코드
│   ├── src/                  # PHP 클래스들
│   ├── resources/            # 뷰, CSS, JS
│   └── composer.json         # 패키지 설정
├── projects/                 # 🧪 테스트 프로젝트들
│   ├── example.com/          # 새로 생성한 Laravel 앱
│   └── hanurichurch.org/     # 기존 테스트 앱
└── docs/                     # 📚 문서 및 설정</code>
                        </div>
                        <p class="text-muted mt-2">
                            <small>
                                <i class="bi bi-lightbulb"></i> 
                                패키지 수정 시 실시간으로 테스트 프로젝트에 반영됩니다
                            </small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection