# Menu Navigation Components

SiteManager 패키지의 메뉴 테이블을 기반으로 한 동적 네비게이션 컴포넌트입니다.

## 기능

- **자동 Breadcrumb 생성**: 현재 라우트에 맞는 브레드크럼을 메뉴 구조에서 자동 생성
- **동적 Menu Tabs**: 현재 메뉴의 형제 메뉴들을 탭으로 표시
- **권한 기반 표시**: 사용자 권한에 따라 메뉴 항목을 필터링
- **반응형 디자인**: 모바일 친화적인 디자인

## 자동 데이터 바인딩

`NavigationComposer`가 모든 뷰에 다음 데이터를 자동으로 제공합니다:

```php
// 뷰에서 사용 가능한 변수들
$navigationMenus  // 전체 네비게이션 트리
$flatMenus        // 평면 메뉴 리스트
$currentMenu      // 현재 페이지의 메뉴
$breadcrumb       // 브레드크럼 배열
$menuTabs         // 메뉴 탭 배열
```

## Controller에서 추가 네비게이션 데이터 전달

기본 index 페이지 외에 create, edit, show 등의 뷰에서는 **현재 메뉴 컨텍스트 유지**와 **브레드크럼 확장**을 위해 추가 변수를 전달해야 합니다.

### 필수 전달 변수

#### 1. `currentMenuId` - 현재 메뉴 ID 유지

NavigationComposer가 올바른 메뉴 컨텍스트를 유지하도록 현재 메뉴 ID를 명시적으로 전달합니다.

```php
// BoardController의 경우
return view('board.show', [
    'currentMenuId' => $board->menu_id,  // 게시판의 메뉴 ID
    // ... 기타 데이터
]);

// SermonController의 경우  
public function create(): View
{
    $menu = Menu::where('target', 'sermons.sunday')->first();
    
    return view('sermons.form', [
        'currentMenuId' => $menu?->id,  // 설교 메뉴 ID
        // ... 기타 데이터
    ]);
}

// MusicController의 경우
public function show(Song $song): View
{
    $menu = $song->getMenu();
    
    return view('music.show', [
        'currentMenuId' => $menu?->id,  // 음악 메뉴 ID
        // ... 기타 데이터  
    ]);
}
```

#### 2. `additionalBreadcrumb` - 브레드크럼 확장

현재 페이지의 구체적인 정보를 브레드크럼에 추가합니다.

```php
// 게시글 상세보기
return view('board.show', [
    'additionalBreadcrumb' => [
        'title' => $post->title,        // 게시글 제목
        'url' => null                   // 현재 페이지이므로 링크 없음
    ]
]);

// 설교 등록 폼
return view('sermons.form', [
    'additionalBreadcrumb' => [
        'title' => '새 설교 등록',
        'url' => null
    ]
]);

// 설교 수정 폼
return view('sermons.form', [
    'additionalBreadcrumb' => [
        'title' => '설교 수정 - ' . $sermon->title,
        'url' => null
    ]
]);

// 앨범 상세보기
return view('music.albums.show', [
    'additionalBreadcrumb' => [
        'title' => $album->title,
        'url' => null
    ]
]);
```

### 실제 Controller 구현 예시

```php
class SermonController extends Controller
{
    public function show($slug)
    {
        $sermon = Sermon::where('slug', $slug)->firstOrFail();
        $menu = $sermon->getMenu();
        
        return view('sermons.show', compact('sermon') + [
            'currentMenuId' => $menu?->id,
            'additionalBreadcrumb' => [
                'title' => $sermon->title,
                'url' => null
            ]
        ]);
    }
    
    public function edit($slug)
    {
        $sermon = Sermon::where('slug', $slug)->firstOrFail();
        $menu = $sermon->getMenu();
        
        return view('sermons.form', compact('sermon') + [
            'currentMenuId' => $menu?->id,
            'additionalBreadcrumb' => [
                'title' => '설교 수정 - ' . $sermon->title,
                'url' => null
            ]
        ]);
    }
}

class MusicAlbumController extends Controller
{
    public function show(MusicAlbum $album): View
    {
        $menu = $album->getMenu();
        $songs = $album->songs()->byTrack()->get();
        
        return view('music.albums.show', [
            'album' => $album,
            'songs' => $songs,
            'currentMenuId' => $menu?->id,
            'additionalBreadcrumb' => [
                'title' => $album->title,
                'url' => null
            ]
        ]);
    }
}
```

### 브레드크럼 결과 예시

위와 같이 설정하면 다음과 같은 브레드크럼이 생성됩니다:

```
// 설교 상세보기
Home > 설교 > 주일설교 > "하나님의 사랑에 대하여"

// 설교 등록 폼  
Home > 설교 > 주일설교 > 새 설교 등록

// 앨범 상세보기
Home > 음악 > "2024 Christmas Album"

// 게시글 상세보기
Home > 공지사항 > "2024년 교회 행사 안내"
```

### 추가 옵션 변수

필요에 따라 더 많은 컨텍스트 정보를 전달할 수 있습니다:

```php
return view('board.show', [
    'currentMenuId' => $board->menu_id,
    'currentSkin' => $board->skin ?? 'default',      // 스킨 정보
    'layoutPath' => $this->getLayoutPath(),          // 레이아웃 경로
    'additionalBreadcrumb' => [
        'title' => $post->title,
        'url' => null
    ]
]);
```

## 사용법

### 1. 기본 사용 (권장)

```blade
{{-- 문서 페이지에서 간단히 include --}}
@include('partials.document-navigation')
```

### 2. 개별 컴포넌트 사용

```blade
{{-- 브레드크럼만 사용 --}}
<x-sitemanager::menu-breadcrumb :breadcrumb="$breadcrumb" />

{{-- 메뉴 탭만 사용 --}}
<x-sitemanager::menu-tabs :tabs="$menuTabs" variant="pills" />

{{-- 통합 네비게이션 --}}
<x-sitemanager::menu-navigation 
    :breadcrumb="$breadcrumb" 
    :tabs="$menuTabs" 
    variant="underline" />
```

### 3. 커스터마이징

```blade
{{-- 탭 스타일 변경 --}}
<x-sitemanager::menu-tabs :tabs="$menuTabs" variant="pills" alignment="center" />

{{-- 브레드크럼 구분자 변경 --}}
<x-sitemanager::menu-breadcrumb :breadcrumb="$breadcrumb" separator=">" />

{{-- 조건부 표시 --}}
<x-sitemanager::menu-navigation 
    :breadcrumb="$breadcrumb" 
    :tabs="$menuTabs" 
    :show-breadcrumb="true"
    :show-tabs="count($menuTabs) > 1" />
```

## 컴포넌트 옵션

### MenuBreadcrumb

- `breadcrumb`: 브레드크럼 배열
- `separator`: 구분자 (기본값: '/')
- `show-home`: 홈 링크 표시 여부 (기본값: true)

### MenuTabs

- `tabs`: 탭 배열
- `variant`: 스타일 ('tabs', 'pills', 'underline')
- `alignment`: 정렬 ('left', 'center', 'right')

### MenuNavigation

- `breadcrumb`: 브레드크럼 배열
- `tabs`: 탭 배열
- `show-breadcrumb`: 브레드크럼 표시 여부
- `show-tabs`: 탭 표시 여부
- `variant`: 탭 스타일
- `separator`: 브레드크럼 구분자

## 메뉴 매칭 로직

NavigationComposer는 다음 순서로 현재 메뉴를 찾습니다:

1. **정확한 라우트명 매칭**: `menu.type = 'route'`이고 `menu.target = Route::currentRouteName()`
2. **URL 매칭**: `menu.type = 'url'`이고 현재 URL과 일치
3. **패턴 매칭**: URL 패턴 기반 상위 경로 매칭

## 개발자 구현 가이드

### 새 모듈 개발 시 체크리스트

새로운 Controller를 개발할 때 네비게이션이 올바르게 작동하도록 다음 단계를 따르세요:

#### 1. 기본 index 메서드

```php
public function index(Request $request): View
{
    // 메뉴 찾기 (권한 시스템과 연동)
    $menu = Menu::where('target', 'my-module.index')->first();
    
    // 기본 데이터만 전달 (NavigationComposer가 자동 처리)
    return view('my-module.index', compact('data'));
}
```

#### 2. 상세/생성/수정 메서드

```php
public function show($id): View
{
    $item = MyModel::findOrFail($id);
    $menu = $item->getMenu(); // 또는 고정 메뉴
    
    return view('my-module.show', compact('item') + [
        'currentMenuId' => $menu?->id,           // 필수
        'additionalBreadcrumb' => [              // 필수
            'title' => $item->title,
            'url' => null
        ]
    ]);
}

public function create(): View
{
    $menu = Menu::where('target', 'my-module.index')->first();
    
    return view('my-module.form', [
        'currentMenuId' => $menu?->id,
        'additionalBreadcrumb' => [
            'title' => '새 항목 등록',
            'url' => null
        ]
    ]);
}

public function edit($id): View
{
    $item = MyModel::findOrFail($id);
    $menu = $item->getMenu();
    
    return view('my-module.form', compact('item') + [
        'currentMenuId' => $menu?->id,
        'additionalBreadcrumb' => [
            'title' => '수정 - ' . $item->title,
            'url' => null
        ]
    ]);
}
```

#### 3. Model에서 메뉴 연결 구현

```php
class MyModel extends Model
{
    public function getMenu(): ?Menu
    {
        // 방법 1: 고정 메뉴 (단순한 경우)
        return Menu::where('target', 'my-module.index')->first();
        
        // 방법 2: 카테고리별 다른 메뉴 (복잡한 경우)
        if ($this->category === 'special') {
            return Menu::where('target', 'my-module.special')->first();
        }
        return Menu::where('target', 'my-module.index')->first();
    }
}
```

### 트러블슈팅

#### 문제: 브레드크럼이 표시되지 않음

**원인**: `currentMenuId`를 전달하지 않아서 NavigationComposer가 올바른 메뉴를 찾지 못함

**해결**:
```php
// ❌ 잘못된 방식
return view('my-module.show', compact('item'));

// ✅ 올바른 방식  
return view('my-module.show', compact('item') + [
    'currentMenuId' => $menu?->id
]);
```

#### 문제: 메뉴 탭이 올바르게 활성화되지 않음

**원인**: 라우트명과 메뉴의 target이 일치하지 않음

**해결**: 메뉴 관리에서 target을 정확한 라우트명으로 설정
```
라우트명: my-module.index
메뉴 target: my-module.index (정확히 일치해야 함)
```

#### 문제: 하위 페이지에서 상위 메뉴가 활성화되지 않음

**원인**: `additionalBreadcrumb`만 설정하고 `currentMenuId`를 빠뜨림

**해결**: 두 변수 모두 설정
```php
return view('my-module.show', [
    'currentMenuId' => $menu?->id,        // 상위 메뉴 활성화
    'additionalBreadcrumb' => [           // 현재 페이지 브레드크럼
        'title' => $item->title,
        'url' => null
    ]
]);
```

## 스타일 커스터마이징

각 컴포넌트는 내장 CSS를 포함하고 있으며, 다음 CSS 변수로 커스터마이징 가능합니다:

```css
:root {
    --menu-primary-color: #08c2b7;
    --menu-text-color: #6c757d;
    --menu-border-color: #dee2e6;
}
```

## 데이터 구조

### Breadcrumb 배열

```php
[
    [
        'title' => 'Home',
        'url' => '/',
        'is_current' => false
    ],
    [
        'title' => 'About Us',
        'url' => '/about',
        'is_current' => false
    ],
    [
        'title' => 'EDM Korean Global Campus',
        'url' => null,
        'is_current' => true,
        'menu_id' => 123
    ]
]
```

### MenuTabs 배열

```php
[
    [
        'title' => 'EDM Korean',
        'url' => '/about/edm-korean',
        'is_current' => false,
        'menu_id' => 121,
        'icon' => 'bi bi-info-circle'
    ],
    [
        'title' => 'EDM Korean Global Campus',
        'url' => '/about/edm-korean-global-campus',
        'is_current' => true,
        'menu_id' => 122,
        'icon' => null
    ]
]
```

## 예제

기존 하드코딩된 네비게이션을:

```blade
{{-- 기존 방식 --}}
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Home</a></li>
        <li class="breadcrumb-item"><a href="/about">About Us</a></li>
        <li class="breadcrumb-item active">EDM Korean Global Campus</li>
    </ol>
</nav>

<ul class="nav nav-tabs">
    <li class="nav-item">
        <a class="nav-link" href="/about/edm-korean">EDM Korean</a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="/about/edm-korean-global-campus">EDM Korean Global Campus</a>
    </li>
</ul>
```

이렇게 간단히 변경:

```blade
{{-- 새로운 방식 --}}
@include('partials.document-navigation')
```

## 메뉴 테이블 설정

컴포넌트가 올바르게 작동하려면 `menus` 테이블에 다음과 같이 메뉴가 설정되어야 합니다:

```sql
-- About Us (부모 메뉴)
INSERT INTO menus (title, type, target, section, parent_id, _lft, _rgt, depth) 
VALUES ('About Us', 'url', '/about', 1, NULL, 1, 12, 0);

-- 자식 메뉴들
INSERT INTO menus (title, type, target, section, parent_id, _lft, _rgt, depth) 
VALUES 
('EDM Korean', 'url', '/about/edm-korean', 1, 1, 2, 3, 1),
('EDM Korean Global Campus', 'url', '/about/edm-korean-global-campus', 1, 1, 4, 5, 1),
('Vision & Mission', 'url', '/about/edm-korean-vision', 1, 1, 6, 7, 1);
```
