# SiteManager 권한 시스템 가이드

## 핵심 개념

SiteManager는 **메뉴 기반의 계층적 권한 시스템**을 사용합니다.

- **모든 권한은 메뉴를 통해 설정**됩니다
- **게시판을 비롯해서 권한을 사용하는 모든 모듈은 메뉴에 연결**되어야 권한 시스템이 작동합니다
- **비트마스크 방식**으로 여러 권한을 조합할 수 있습니다

## 권한 계층 구조

```
메뉴 권한 = max(기본권한, 레벨권한, 그룹권한, 관리자권한)
```

1. **기본 권한**: 모든 사용자(비회원 포함)에게 적용
2. **레벨 권한**: 회원 레벨에 따른 권한
3. **그룹 권한**: 특정 그룹 멤버에게 부여
4. **관리자 권한**: 지정된 관리자에게 모든 권한 부여

## 권한 종류 (비트마스크)

| 비트값 | 권한명 | 설명 |
|-------|--------|------|
| 1 | index | 목록 보기 |
| 2 | read | 상세 읽기 |
| 4 | readComments | 댓글 읽기 |
| 8 | writeComments | 댓글 작성 |
| 16 | uploadCommentFiles | 댓글 파일 업로드 |
| 32 | write | 게시글 작성/수정 |
| 64 | uploadFiles | 파일 업로드 |
| 128 | manage | 완전 제어 (삭제, 관리) |

## 메뉴 연결과 권한 확인의 두 가지 방식

SiteManager에서 권한을 확인하는 방법은 **메뉴 연결 방식**에 따라 다릅니다.

### 1. 게시판 방식: menu_id로 연결된 모델

```php
// 게시판은 데이터베이스에 menu_id가 저장되어 있음
$board = Board::find(1);
echo $board->menu_id; // 예: 25

// 모델에 연결된 메뉴를 통해 권한 확인
if (!can('read', $board)) {
    abort(403, '읽기 권한이 없습니다.');
}
```

**특징:**
- 게시판마다 다른 메뉴에 연결 가능
- 각 게시판별로 독립적인 권한 설정
- 데이터베이스에 menu_id 필드로 관리

### 2. 모듈 방식: 라우트로 연결된 메뉴

```php
// 설교나 음악처럼 라우트명으로 메뉴와 연결
$menu = Menu::where('target', 'sermons.sunday')->first();

// 직접 메뉴를 찾아서 권한 확인
if (!can('write', $menu)) {
    abort(403, '작성 권한이 없습니다.');
}
```

**특징:**
- 라우트명(target)으로 메뉴와 연결
- 동적으로 메뉴를 찾아야 함
- 하나의 모듈을 여러 메뉴로 다른 권한 설정 가능

### 3. 다중 메뉴 지원: {menuId?} 파라미터 방식

```php
// 라우트에서 선택적 menuId 파라미터 지원
Route::get('/music/{menuId?}', [MusicController::class, 'index'])
    ->name('music.index');

// Controller에서 동적 메뉴 처리
public function index(Request $request, $menuId = null): View
{
    // 1. menuId가 있으면 해당 메뉴 사용
    if ($menuId) {
        $menu = Menu::where('target', '/music/' . $menuId)->first();
    } else {
        // 2. 기본 메뉴 찾기
        $menu = Menu::where('target', 'music.index')->first();
    }
    
    // 권한 확인
    if ($menu && !can('index', $menu)) {
        abort(403, '접근 권한이 없습니다.');
    }
}
```

**관리자 설정:**
- Menu 1: target = "music.index" (기본 음악 메뉴)
- Menu 2: target = "/music/jazz" (재즈팀 전용 음악 메뉴)
- Menu 3: target = "/music/country" (컨트리팀 전용 음악 메뉴)

**결과:**
- `/music` → Menu 1의 권한 적용
- `/music/jazz` → Menu 2의 권한 적용  
- `/music/country` → Menu 3의 권한 적용

## 권한 시스템 적용 가이드 (초보 개발자용)

### Step 1: 메뉴 연결 방식 결정

새 모듈 개발 시 먼저 **메뉴 연결 방식**을 결정해야 합니다.

#### 방식 A: 데이터 기반 연결 (게시판 방식)
```php
// 테이블에 menu_id 컬럼 추가
Schema::table('my_posts', function (Blueprint $table) {
    $table->unsignedBigInteger('menu_id')->nullable();
});

// Model에서 메뉴 관계 정의
public function menu()
{
    return $this->belongsTo(Menu::class);
}
```

#### 방식 B: 라우트 기반 연결 (모듈 방식)
```php
// 라우트 정의
Route::get('/my-module', [MyController::class, 'index'])
    ->name('my-module.index');

// 관리자에서 메뉴 생성 시 target에 'my-module.index' 입력
```

#### 방식 C: 다중 메뉴 지원
```php
// 라우트에 선택적 menuId 추가
Route::get('/my-module/{menuId?}', [MyController::class, 'index'])
    ->name('my-module.index');
```

### Step 2: Controller에서 메뉴 찾기 구현

```php
class MyController extends Controller
{
    /**
     * 현재 요청에 해당하는 메뉴 찾기
     */
    private function getMenu($menuId = null): ?Menu
    {
        // 방식 C: menuId 파라미터 우선 (문자열 식별자 사용)
        if ($menuId) {
            return Menu::where('target', '/my-module/' . $menuId)->first();
        }
        
        // 방식 B: 라우트명으로 기본 메뉴 찾기
        return Menu::where('target', 'my-module.index')->first();
    }
    
    public function index($menuId = null): View
    {
        $menu = $this->getMenu($menuId);
        
        // 권한 확인
        if ($menu && !can('index', $menu)) {
            abort(403, '목록 보기 권한이 없습니다.');
        }
        
        // 권한 변수 계산
        $canWrite = $menu ? can('write', $menu) : false;
        $canManage = $menu ? can('manage', $menu) : false;
        
        return view('my-module.index', compact('canWrite', 'canManage'));
    }
}
```

### Step 3: Model에서 권한 메서드 구현

```php
class MyPost extends Model
{
    /**
     * 이 포스트의 메뉴 찾기
     */
    public function getMenu(): ?Menu
    {
        // 방식 A: 직접 연결된 메뉴
        if ($this->menu_id) {
            return $this->menu;
        }
        
        // 방식 B: 기본 메뉴
        return Menu::where('target', 'my-module.index')->first();
    }
    
    /**
     * 수정 권한 확인
     */
    public function canEdit(): bool
    {
        $menu = $this->getMenu();
        if (!$menu) return false;
        
        $user = Auth::user();
        $canManage = can('manage', $menu);
        $canWrite = can('write', $menu);
        $isAuthor = $user && $this->user_id === $user->id;
        
        return $canManage || ($isAuthor && $canWrite);
    }
}
```

### Step 4: 관리자에서 메뉴 설정

1. **사이트매니저 관리자** → **메뉴 관리**
2. **새 메뉴 추가**
3. **타입**: Route 선택
4. **Target 설정**:
   - 기본: `my-module.index`
   - 다중: `/my-module/admin` (관리자 전용), `/my-module/premium` (프리미엄 사용자 전용)
5. **권한 설정**: 기본, 레벨, 그룹, 관리자 권한 구성

### Step 5: 권한별 기능 제한

```php
// 목록 보기
if ($menu && !can('index', $menu)) {
    abort(403);
}

// 상세 보기
if ($menu && !can('read', $menu)) {
    abort(403);
}

// 작성/수정
if ($menu && !can('write', $menu)) {
    abort(403);
}

// 삭제/관리
if ($menu && !can('manage', $menu)) {
    abort(403);
}
```

## 사용법

### 1. Controller에서 권한 확인

```php
// 메뉴 연결된 모델의 권한 확인
if (!can('read', $board)) {
    abort(403, '읽기 권한이 없습니다.');
}

// 직접 메뉴 권한 확인
$menu = Menu::find($menuId);
if (!can('write', $menu)) {
    abort(403, '작성 권한이 없습니다.');
}
```

### 2. Blade 템플릿에서 사용

```php
// Controller에서 권한 변수 전달
$canWrite = $menu ? can('write', $menu) : false;
$canManage = $menu ? can('manage', $menu) : false;

// Blade에서 사용
@if($canWrite)
    <a href="{{ route('sermons.create') }}">새 설교 등록</a>
@endif

@if($canManage)
    <button class="btn-danger">삭제</button>
@endif
```

### 3. Model에서 실시간 권한 계산

```php
// BoardPost.php에서
public function canEdit(): bool
{
    $board = $this->getBoard();
    if (!$board || !$board->menu_id) return false;
    
    $user = Auth::user();
    $canManage = can('manage', $board);
    $canWrite = can('write', $board);
    $isAuthor = $user && $this->member_id && $this->member_id === $user->id;
    
    return $canManage || ($isAuthor && $canWrite);
}

// Blade에서 사용
@if($post->canEdit())
    <button>수정</button>
@endif
```

## 실무 구현 패턴 (참고용)

### Controller 구현 패턴

```php
class SermonController extends Controller
{
    public function index(Request $request): View
    {
        // 1. 메뉴 찾기
        $menu = Menu::where('target', 'sermons.sunday')->first();
        
        // 2. 권한 확인
        if ($menu && !can('index', $menu)) {
            abort(403, '목록 보기 권한이 없습니다.');
        }
        
        // 3. 권한 변수 계산
        $canWrite = $menu ? can('write', $menu) : false;
        $canManage = $menu ? can('manage', $menu) : false;
        
        // 4. 뷰에 전달
        return view('sermons.index', compact('sermons', 'canWrite', 'canManage'));
    }
    
    public function create(): View
    {
        $menu = Menu::where('target', 'sermons.sunday')->first();
        
        if ($menu && !can('write', $menu)) {
            abort(403, '등록 권한이 없습니다.');
        }
        
        return view('sermons.form');
    }
    
    public function destroy($id): RedirectResponse
    {
        $sermon = Sermon::findOrFail($id);
        $menu = $sermon->getMenu(); // Model에서 메뉴 찾기 구현
        
        if ($menu && !can('manage', $menu)) {
            abort(403, '삭제 권한이 없습니다.');
        }
        
        $sermon->delete();
        return redirect()->back();
    }
}
```

### Model에서 메뉴 연결

```php
class Sermon extends Model
{
    public function getMenu(): ?Menu
    {
        // 카테고리별로 다른 메뉴 반환
        if ($this->category === '주일설교') {
            return Menu::where('target', 'sermons.sunday')->first();
        } else {
            return Menu::where('target', 'sermons.special')->first();
        }
    }
    
    public function canEdit(): bool
    {
        $menu = $this->getMenu();
        return $menu ? can('write', $menu) : false;
    }
    
    public function canDelete(): bool
    {
        $menu = $this->getMenu();
        return $menu ? can('manage', $menu) : false;
    }
}
```

### 라우트에서 메뉴 연결

```php
// 기본 방식: 라우트명으로 메뉴 연결
Route::get('/sermons/sunday', [SermonController::class, 'sunday'])->name('sermons.sunday');
Route::get('/music', [MusicController::class, 'index'])->name('music.index');

// 다중 메뉴 방식: 의미있는 식별자로 다양한 권한 지원
Route::get('/music/{team?}', [MusicController::class, 'index'])->name('music.index');
// 예: /music/jazz, /music/country, /music/worship 등
```

### 권한 체크 필수 지점

#### Controller 메서드별 권한

- **index()**: `can('index', $menu)` - 목록 보기
- **show()**: `can('read', $menu)` - 상세 보기  
- **create()**: `can('write', $menu)` - 작성 폼
- **store()**: `can('write', $menu)` - 저장
- **edit()**: `can('write', $menu)` - 수정 폼
- **update()**: `can('write', $menu)` - 업데이트
- **destroy()**: `can('manage', $menu)` - 삭제

#### 실제 구현 예시

```php
public function show($slug)
{
    $sermon = Sermon::where('slug', $slug)->firstOrFail();
    $menu = $sermon->getMenu();
    
    // 권한 체크
    if ($menu && !can('read', $menu)) {
        abort(403, '설교를 읽을 권한이 없습니다.');
    }
    
    // 권한 정보 전달
    $canEdit = $menu ? can('write', $menu) : false;
    $canManage = $menu ? can('manage', $menu) : false;
    
    return view('sermons.show', compact('sermon', 'canEdit', 'canManage'));
}
```

## 모범 사례

### ✅ 올바른 구현

```php
// Controller에서 권한 계산
$canWrite = $menu ? can('write', $menu) : false;

// Blade에서 계산된 변수 사용  
@if($canWrite)
    <button>수정</button>
@endif

// Model에서 실시간 권한 계산
public function canEdit(): bool {
    return can('write', $this->getMenu());
}
```

### ❌ 피해야 할 구현

```php
// Blade에서 직접 권한 계산 (성능 저하)
@if(can('write', $sermon->getMenu()))
    <button>수정</button>
@endif

// 메뉴 권한 없이 복잡한 기능 구현 (권한 시스템 우회)
if (Auth::user() && Auth::user()->id === $post->author_id) {
    // 메뉴 권한을 무시하고 작성자만 체크하는 방식
}
```

### 💡 **간단한 권한 체크도 유용한 경우**

SiteManager는 메뉴 기반 권한 외에도 **기본적인 회원 레벨 권한**을 제공합니다:

```php
// Member 모델의 기본 권한 헬퍼 (메뉴와 무관)
if (Auth::user()?->isAdmin()) {
    // 시스템 관리자 전용 기능
}

if (Auth::user()?->isStaff()) {
    // 스태프 전용 기능
}
```

**적합한 사용 케이스:**
- **레이아웃/헤더**: 관리자 메뉴 표시 여부
- **전역 기능**: 시스템 설정, 로그 보기 등
- **간단한 구분**: 복잡한 권한 없이 관리자/일반사용자만 구분

**예시:**
```php
// 레이아웃에서 관리자 메뉴 표시
@if(Auth::user()?->isAdmin())
    <a href="/admin">관리자 패널</a>
@endif

// Controller에서 간단한 관리자 체크
public function systemSettings()
{
    if (!Auth::user()?->isAdmin()) {
        abort(403, '관리자만 접근 가능합니다.');
    }
    // 시스템 설정 로직
}
```

## 디버깅

### 권한 문제 해결 체크리스트

1. **메뉴 연결 확인**: 해당 기능이 메뉴에 연결되어 있는가?
2. **라우트명 확인**: 메뉴의 target이 라우트명과 일치하는가?
3. **권한 설정 확인**: 관리자 페이지에서 메뉴 권한이 설정되어 있는가?
4. **로그인 상태 확인**: 사용자가 올바르게 로그인되어 있는가?

### 디버깅 코드

```php
// 현재 사용자의 메뉴 권한 확인
$permissionService = app(\SiteManager\Services\PermissionService::class);
$userPermission = $permissionService->checkMenuPermission($menu, auth()->user());
dd($userPermission); // 권한 비트값 확인

// 권한 체크 결과 확인
dd([
    'menu_id' => $menu?->id,
    'user_id' => auth()->id(),
    'can_read' => can('read', $menu),
    'can_write' => can('write', $menu),
    'can_manage' => can('manage', $menu)
]);
```

---

**요약**: SiteManager 권한 시스템은 메뉴 중심으로 작동합니다. 새 기능 개발 시 메뉴 연결 → 권한 체크 → 권한 변수 전달 → Blade 사용 순서로 구현하면 됩니다.
