# SiteManager EdmMember 가이드

SiteManager 패키지의 EdmMember 통합 시스템에 대한 가이드입니다. 개념부터 구현, 트러블슈팅까지 모든 정보를 포함합니다.

## 📚 목차

1. [핵심 개념](#핵심-개념)
2. [빠른 시작](#빠른-시작)
3. [상세 구현](#상세-구현)
4. [실무 패턴](#실무-패턴)
5. [트러블슈팅](#트러블슈팅)
6. [FAQ](#faq)

---

## 핵심 개념

### EdmMember 통합이란?

SiteManager가 기존 EDM 시스템의 `edm_member` 데이터베이스를 직접 사용할 수 있도록 하는 기능입니다.

**핵심 장점:**
- 기존 회원 데이터를 그대로 활용 (마이그레이션 불필요)
- SiteManager의 모든 기능을 EdmMember와 함께 사용
- 관리자(Members)와 고객(EdmMember) 분리된 인증 시스템

### 아키텍처

```
┌─────────────────────────────────────────────────────┐
│                SiteManager Application              │
├─────────────────────┬───────────────────────────────┤
│   관리자 영역        │        고객 영역              │
│   Guard: web        │        Guard: customer       │
│   Model: Member     │        Model: EdmMember       │
│   DB: mysql         │        DB: edm_member         │
│   Table: members    │        Table: sys_member      │
└─────────────────────┴───────────────────────────────┘
```

---

## 빠른 시작

### 1. 환경 설정

```env
# .env 파일

# EdmMember 고객 인증 시스템 활성화
ENABLE_EDM_MEMBER_AUTH=true

# 인증 가드 설정
AUTH_MODEL=SiteManager\Models\Member
ADMIN_GUARD=web
CUSTOMER_GUARD=customer

# EdmMember 데이터베이스 연결
EDM_MEMBER_DB_HOST=127.0.0.1
EDM_MEMBER_DB_PORT=3306
EDM_MEMBER_DB_DATABASE=edm_member
EDM_MEMBER_DB_USERNAME=root
EDM_MEMBER_DB_PASSWORD=your_password
```

### 2. 데이터베이스 연결 설정

```php
// config/database.php

'connections' => [
    'mysql' => [
        // 기본 SiteManager 연결
    ],
    
    'edm_member' => [
        'driver' => 'mysql',
        'host' => env('EDM_MEMBER_DB_HOST', '127.0.0.1'),
        'port' => env('EDM_MEMBER_DB_PORT', '3306'),
        'database' => env('EDM_MEMBER_DB_DATABASE', 'edm_member'),
        'username' => env('EDM_MEMBER_DB_USERNAME', 'root'),
        'password' => env('EDM_MEMBER_DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => false,
        'engine' => null,
    ],
],
```

### 3. 설정 적용

```bash
php artisan config:cache
```

### 4. 테스트

```bash
php artisan tinker
> DB::connection('edm_member')->getPdo()  # 연결 테스트
> \SiteManager\Models\EdmMember::first()  # 모델 테스트
```

---

## 상세 구현

### EdmMember 모델 사용법

```php
use SiteManager\Models\EdmMember;

$user = EdmMember::find(1);

// SiteManager 호환 메서드들
$user->getId();           // mm_uid 반환
$user->getLevel();        // SiteManager 레벨로 변환
$user->isAdmin();         // mm_level >= 250
$user->isStaff();         // mm_table === 'member_staff'
$user->isClient();        // mm_table === 'member_client'

// 비밀번호 검증 (EdmMember 해싱 방식)
$user->isEqualPassword($password);
```

### 고객용 로그인 Controller

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerLoginController extends Controller
{
    public function showLoginForm()
    {
        return view('customer.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'mm_id' => 'required|string',
            'password' => 'required|string',
        ]);

        if (Auth::guard('customer')->attempt($request->only('mm_id', 'password'))) {
            return redirect()->intended('/customer/dashboard');
        }

        return back()->withErrors(['mm_id' => '로그인 정보가 올바르지 않습니다.']);
    }

    public function logout()
    {
        Auth::guard('customer')->logout();
        return redirect('/customer/login');
    }
}
```

### 고객용 미들웨어

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CustomerMiddleware
{
    public function handle($request, Closure $next, $permission = null)
    {
        if (!Auth::guard('customer')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            return redirect()->route('customer.login');
        }

        $user = Auth::guard('customer')->user();

        // 권한 체크
        if ($permission && !$this->hasPermission($user, $permission)) {
            abort(403, '접근 권한이 없습니다.');
        }

        return $next($request);
    }

    private function hasPermission($user, $permission)
    {
        switch ($permission) {
            case 'admin': return $user->isAdmin();
            case 'staff': return $user->isStaff();
            default: return true;
        }
    }
}
```

### 라우트 설정

```php
// routes/web.php

// 고객 인증 라우트
Route::prefix('customer')->name('customer.')->group(function () {
    Route::get('login', [CustomerLoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [CustomerLoginController::class, 'login']);
    
    Route::middleware('customer')->group(function () {
        Route::get('dashboard', [CustomerController::class, 'dashboard'])->name('dashboard');
        Route::get('profile', [CustomerController::class, 'profile'])->name('profile');
        Route::post('logout', [CustomerLoginController::class, 'logout'])->name('logout');
        
        // 권한별 라우트
        Route::middleware('customer:admin')->group(function () {
            Route::get('admin', [CustomerController::class, 'admin'])->name('admin');
        });
    });
});

// 관리자 영역은 기존 SiteManager 그대로 사용
Route::prefix('sitemanager')->middleware('auth:web')->group(function () {
    // SiteManager 기본 기능들
});
```

### 뷰에서 사용

```blade
{{-- layouts/app.blade.php --}}

{{-- 고객 로그인 상태 --}}
@auth('customer')
    <div class="dropdown">
        <button class="btn dropdown-toggle" data-bs-toggle="dropdown">
            {{ Auth::guard('customer')->user()->mm_name ?? Auth::guard('customer')->user()->mm_id }}님
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="{{ route('customer.dashboard') }}">대시보드</a></li>
            <li><a class="dropdown-item" href="{{ route('customer.profile') }}">프로필</a></li>
            
            @if(Auth::guard('customer')->user()->isAdmin())
                <li><a class="dropdown-item" href="{{ route('customer.admin') }}">관리 메뉴</a></li>
            @endif
            
            <li><hr class="dropdown-divider"></li>
            <li>
                <form action="{{ route('customer.logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button class="dropdown-item">로그아웃</button>
                </form>
            </li>
        </ul>
    </div>
@else
    <a href="{{ route('customer.login') }}" class="btn btn-outline-primary">로그인</a>
@endauth

{{-- 관리자 로그인 상태 --}}
@auth('web')
    <a href="/sitemanager" class="btn btn-secondary">관리자</a>
@endauth
```

---

## 실무 패턴

### 1. Controller 패턴 (권장)

```php
class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('customer');
    }

    public function dashboard()
    {
        $user = Auth::guard('customer')->user();
        
        $data = $this->getUserDashboardData($user);
        
        return view('customer.dashboard', compact('user', 'data'));
    }

    private function getUserDashboardData($user)
    {
        if ($user->isStaff()) {
            return [
                'type' => 'staff',
                'assigned_tasks' => $this->getStaffTasks($user),
            ];
        } elseif ($user->isClient()) {
            return [
                'type' => 'client',
                'services' => $this->getClientServices($user),
            ];
        }
        
        return ['type' => 'general'];
    }
}
```

### 2. 헬퍼 함수

```php
// app/Helpers/CustomerHelper.php

if (!function_exists('current_customer')) {
    function current_customer()
    {
        return Auth::guard('customer')->user();
    }
}

if (!function_exists('customer_can')) {
    function customer_can($permission)
    {
        $user = current_customer();
        if (!$user) return false;
        
        switch ($permission) {
            case 'admin': return $user->isAdmin();
            case 'staff': return $user->isStaff();
            default: return false;
        }
    }
}
```

### 3. 캐싱 전략

```php
// 사용자 데이터 캐싱
$userData = Cache::remember("customer_data_{$user->getId()}", 1800, function () use ($user) {
    return [
        'id' => $user->getId(),
        'name' => $user->mm_name,
        'level' => $user->getLevel(),
        'permissions' => [
            'is_admin' => $user->isAdmin(),
            'is_staff' => $user->isStaff(),
        ],
    ];
});
```

### 4. API 지원

```php
// API 컨트롤러에서
class ApiController extends Controller
{
    public function user(Request $request)
    {
        $user = $request->user('customer'); // Sanctum 등 사용시
        
        return response()->json([
            'id' => $user->getId(),
            'name' => $user->mm_name,
            'permissions' => [
                'admin' => $user->isAdmin(),
                'staff' => $user->isStaff(),
            ],
        ]);
    }
}
```

---

## 트러블슈팅

### 자주 발생하는 문제들

#### 1. Guard [customer] is not defined

**증상:** `InvalidArgumentException: Auth guard [customer] is not defined`

**원인:** EdmMember 인증 설정이 제대로 등록되지 않음

**해결:**
```bash
# 설정 캐시 재생성
php artisan config:clear
php artisan config:cache

# 설정 확인
php artisan config:show auth.guards.customer
```

#### 2. EdmMember 로그인 실패

**증상:** 올바른 아이디/비밀번호인데 로그인 안됨

**진단:**
```php
// 테스트 라우트로 확인
Route::get('/test-edm-login', function () {
    $user = \SiteManager\Models\EdmMember::where('mm_id', 'test_user')->first();
    $password = 'test_password';
    
    return [
        'user_exists' => $user ? true : false,
        'password_hash' => $user ? $user->mm_password : null,
        'test_hash' => hash('sha256', md5($password)),
        'password_match' => $user ? $user->isEqualPassword($password) : false,
    ];
});
```

**해결:**
- EdmMember는 SHA256(MD5()) 해싱 사용
- `isEqualPassword()` 메서드가 올바르게 구현되었는지 확인

#### 3. 데이터베이스 연결 오류

**증상:** `Connection refused` 또는 `Access denied`

**진단:**
```bash
# 직접 연결 테스트
mysql -h 127.0.0.1 -u root -p edm_member

# Laravel에서 테스트
php artisan tinker
> DB::connection('edm_member')->getPdo()
```

**해결:**
- .env 파일의 EDM_MEMBER_DB_* 설정 확인
- MySQL 서비스 실행 상태 확인
- 사용자 권한 확인

#### 4. 권한 체크 오류

**증상:** 권한이 있는 사용자도 접근 거부

**진단:**
```php
// 권한 디버깅
$user = Auth::guard('customer')->user();
dd([
    'user_id' => $user->getId(),
    'mm_level' => $user->mm_level,
    'mm_table' => $user->mm_table,
    'is_admin' => $user->isAdmin(),
    'is_staff' => $user->isStaff(),
]);
```

### 성능 최적화

#### 1. 데이터베이스 인덱스

```sql
-- 로그인 성능 향상
CREATE INDEX idx_sys_member_mm_id ON sys_member(mm_id);
CREATE INDEX idx_sys_member_login ON sys_member(mm_id, mm_password);

-- 레벨별 조회 성능
CREATE INDEX idx_sys_member_level ON sys_member(mm_level);
```

#### 2. 연결 풀링 설정

```php
// config/database.php
'edm_member' => [
    // ... 기본 설정
    'options' => [
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_TIMEOUT => 30,
    ],
],
```

#### 3. 쿼리 최적화

```php
// N+1 문제 방지
$users = EdmMember::with(['staff', 'client'])->get();

// 페이지네이션 사용
$users = EdmMember::paginate(20);
```

### 보안 고려사항

#### 1. 세션 보안

```php
// 세션 하이재킹 방지
public function handle($request, Closure $next)
{
    if (Auth::guard('customer')->check()) {
        $currentIp = $request->ip();
        $sessionIp = session('customer_ip');
        
        if ($sessionIp && $sessionIp !== $currentIp) {
            Auth::guard('customer')->logout();
            return redirect()->route('customer.login')
                ->withErrors(['security' => '보안상의 이유로 다시 로그인해주세요.']);
        }
        
        session(['customer_ip' => $currentIp]);
    }
    
    return $next($request);
}
```

#### 2. 비밀번호 업그레이드

```php
// 로그인시 자동으로 Laravel 해싱으로 업그레이드
public function login(Request $request)
{
    $credentials = $request->only('mm_id', 'password');
    
    if (Auth::guard('customer')->attempt($credentials)) {
        $user = Auth::guard('customer')->user();
        
        // 기존 해싱 방식이면 업그레이드
        if ($user->needsPasswordUpgrade()) {
            $user->upgradePassword($credentials['password']);
        }
        
        return redirect()->intended('/customer/dashboard');
    }
    
    return back()->withErrors(['mm_id' => '로그인 정보가 올바르지 않습니다.']);
}
```

---

## FAQ

### Q1. 기존 SiteManager 기능이 영향을 받나요?
**A:** 아니요. EdmMember는 고객용 별도 인증으로 동작하며, 관리자는 기존 Members 테이블을 그대로 사용합니다.

### Q2. 여러 프로젝트에서 같은 edm_member DB를 공유할 수 있나요?
**A:** 네, 가능합니다. 여러 프로젝트가 같은 EDM_MEMBER_DB_* 설정을 사용하면 됩니다.

### Q3. EdmMember 사용자도 SiteManager 메뉴에 접근할 수 있나요?
**A:** PermissionService가 자동으로 EdmMember를 지원하므로 권한에 따라 접근 가능합니다.

### Q4. 비밀번호 해싱 방식이 다른데 문제없나요?
**A:** EdmUserProvider가 EdmMember의 SHA256(MD5()) 방식을 지원하므로 문제없습니다.

### Q5. 기존 EDM 프로젝트에서 바로 적용할 수 있나요?
**A:** 네, .env 설정만 추가하면 기존 데이터를 그대로 사용할 수 있습니다.

---

## 응급 상황 체크리스트

문제 발생시 순서대로 확인:

1. **환경 변수**: `ENABLE_EDM_MEMBER_AUTH=true` 설정 확인
2. **설정 캐시**: `php artisan config:cache` 실행
3. **DB 연결**: `DB::connection('edm_member')->getPdo()` 테스트
4. **모델 로드**: `\SiteManager\Models\EdmMember::first()` 테스트
5. **가드 등록**: `config('auth.guards.customer')` 확인
6. **로그 확인**: `storage/logs/laravel.log` 에러 메시지 확인

---

**개발팀 공유용 요약:**
- **목적**: 기존 EDM 회원 데이터를 SiteManager에서 그대로 활용
- **구조**: 관리자(Members) + 고객(EdmMember) 이중 인증
- **핵심 설정**: `ENABLE_EDM_MEMBER_AUTH=true`
- **주요 파일**: EdmMember 모델, EdmUserProvider, CustomerController
- **호환성**: 기존 SiteManager 기능 완전 보존