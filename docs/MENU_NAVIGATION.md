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
