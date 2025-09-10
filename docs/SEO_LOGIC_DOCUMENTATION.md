# SiteManager SEO 로직 문서

## 개요

SiteManager의 SEO 시스템은 다단계 우선순위 방식으로 작동하며, 메뉴 기반의 SEO 정보와 페이지별 SEO 정보를 조합하여 최적의 SEO 메타데이터를 생성합니다.

## SEO 데이터 처리 우선순위

### 1. 최우선: 뷰 레벨에서 직접 지정한 SEO 데이터
```php
// 컨트롤러에서 직접 seoData 배열을 전달하는 경우
return view('page', [
    'seoData' => [
        'title' => 'Custom Title',
        'description' => 'Custom Description',
        // ...
    ]
]);
```

### 2. 차순위: Blade 템플릿의 @section 디렉티브
```blade
@section('title', 'Page Title')
@section('meta_description', 'Page Description')
@section('meta_keywords', 'Page Keywords')
```

### 3. 기본값: NavigationComposer에서 생성된 SEO 데이터
메뉴 정보를 기반으로 자동 생성되는 SEO 데이터

## 아키텍처 구조

### 1. NavigationComposer (핵심 SEO 엔진)
**파일 위치**: `packages/sitemanager/src/Http/View/Composers/NavigationComposer.php`

#### 주요 기능:
- 모든 뷰에 자동으로 적용되는 글로벌 View Composer
- 메뉴 기반 SEO 데이터 자동 생성
- 브레드크럼 및 JSON-LD 구조화 데이터 생성

#### SEO 데이터 생성 로직:
```php
private function buildSeoData($currentMenu, $breadcrumb)
{
    // 1. 기본 SEO 데이터 초기화
    $seoData = [
        'title' => null,
        'description' => null,
        'keywords' => null,
        'og_title' => null,
        'og_description' => null,
        'og_image' => null,
        'og_url' => null,
        'canonical_url' => null,
        'breadcrumb_json_ld' => null,
    ];

    if ($currentMenu) {
        // 2. 메뉴 정보 기반 제목 구성
        $siteName = config_get('SITE_NAME');
        $pageTitle = $currentMenu->title;
        
        // 3. 브레드크럼 기반 계층 구조 반영
        $categoryTitles = [];
        if ($breadcrumb && count($breadcrumb) > 1) {
            for ($i = 1; $i < count($breadcrumb) - 1; $i++) {
                $categoryTitles[] = $breadcrumb[$i]['title'];
            }
        }
        
        // 4. 최종 제목 구성: 페이지명 | 카테고리 | 사이트명
        if (!empty($categoryTitles)) {
            $seoData['title'] = $pageTitle . ' | ' . implode(' - ', $categoryTitles) . ' | ' . $siteName;
        } else {
            $seoData['title'] = $pageTitle . ' | ' . $siteName;
        }
        
        // 5. 설명, 키워드, Open Graph 등 설정
        // ...
    }
    
    return $seoData;
}
```

### 2. 레이아웃 템플릿 (SEO 메타태그 출력)
**파일 위치**: `packages/sitemanager/resources/views/layouts/app.blade.php`

#### 우선순위 처리 로직:
```php
<!-- Title 우선순위: seoData > @yield > 기본값 -->
<title>{{ $seoData['title'] ?? '@yield("title", config_get("SITE_NAME"))' }}</title>

<!-- Description 우선순위: seoData > @yield > 기본값 -->
<meta name="description" content="{{ $seoData['description'] ?? '@yield("meta_description", config_get("SITE_DESCRIPTION"))' }}">

<!-- Keywords 우선순위: seoData > @yield > 기본값 -->
<meta name="keywords" content="{{ $seoData['keywords'] ?? '@yield("meta_keywords", config_get("SITE_KEYWORDS"))' }}">
```

### 3. SEO 컴포넌트 (추가 메타태그)
**파일 위치**: `packages/sitemanager/resources/views/components/seo.blade.php`

#### 기능:
- Article 타입 메타태그 (게시글용)
- JSON-LD 구조화 데이터 출력
- Open Graph 확장 메타태그

## 페이지별 SEO 처리 방식

### 1. 게시판 목록 페이지
**컨트롤러**: `BoardController@index`

```php
private function buildBoardSeoData($board, $request)
{
    $siteName = config_get('SITE_NAME');
    $menu = $board->menu; // 연결된 메뉴 정보
    
    // 검색/카테고리 필터 반영
    $searchTerm = $request->get('search');
    $category = $request->get('category');
    
    // 동적 제목 생성
    $title = $board->name;
    if ($searchTerm) {
        $title = "'{$searchTerm}' 검색결과 | " . $board->name;
    } elseif ($category) {
        $title = $category . ' | ' . $board->name;
    }
    $title .= ' | ' . $siteName;
    
    // 메뉴 설명 우선 사용, 없으면 자동 생성
    $description = $board->description;
    if (!$description && $menu && $menu->description) {
        $description = $menu->description;
    }
    
    return [
        'title' => $title,
        'description' => $description,
        'keywords' => implode(', ', array_unique($keywords)),
        'og_title' => $searchTerm ? "'{$searchTerm}' 검색결과" : $board->name,
        // ...
    ];
}
```

### 2. 게시글 상세 페이지
**컨트롤러**: `BoardController@show`

```php
private function buildPostSeoData($board, $post, $attachments)
{
    $siteName = config_get('SITE_NAME');
    
    // 게시글 기반 제목: 게시글명 | 게시판명 | 사이트명
    $title = $post->title . ' | ' . $board->name . ' | ' . $siteName;
    
    // 본문에서 설명 추출
    $description = $post->excerpt;
    if (!$description) {
        $plainText = strip_tags($post->content);
        $description = Str::limit($plainText, 160);
    }
    
    // Article 타입 Open Graph
    return [
        'title' => $title,
        'description' => $description,
        'og_type' => 'article',
        'article_author' => $post->author_name ?: $post->member?->name,
        'article_published_time' => $post->created_at->toISOString(),
        // ...
    ];
}
```

## 현재 @section('title') 미적용 문제 분석

### 문제점
현재 `password-form.blade.php`에서 `@section('title', 'Private Post - ' . $post->title)`가 적용되지 않는 이유:

1. **NavigationComposer 우선순위**: NavigationComposer에서 생성된 `$seoData['title']`가 존재하면 `@yield('title')`가 무시됨
2. **게시판 컨트롤러**: BoardController에서 이미 seoData를 생성하여 뷰에 전달하고 있음

### 해결 방안

#### 방법 1: 컨트롤러에서 seoData 직접 설정 (권장)
```php
// BoardController@verifyPassword 메서드에서
public function verifyPassword($boardSlug, $postSlug)
{
    // ... 기존 로직
    
    $seoData = [
        'title' => 'Private Post - ' . $post->title . ' | ' . $board->name . ' | ' . config_get('SITE_NAME'),
        'description' => 'This is a password-protected post.',
        'og_title' => 'Private Post - ' . $post->title,
        // ...
    ];
    
    return view('board.password-form', compact('board', 'post', 'seoData'));
}
```

#### 방법 2: 레이아웃 템플릿 우선순위 수정
```php
<!-- app.blade.php에서 @yield 우선순위를 높임 -->
<title>@yield('title', $seoData['title'] ?? config_get('SITE_NAME'))</title>
```

#### 방법 3: NavigationComposer에서 조건부 처리
```php
// NavigationComposer에서 특정 조건에서는 seoData 생성 제외
if (!isset($viewData['prioritize_view_sections'])) {
    $seoData = $existingSeoData ?: $this->buildSeoData($currentMenu, $breadcrumb);
}
```

## 권장 구현 방식

### 1. 컨트롤러 레벨에서 seoData 완전 제어
페이지별로 특별한 SEO 요구사항이 있는 경우 컨트롤러에서 완전한 seoData 배열을 생성하여 전달

### 2. Blade 템플릿의 @section은 보조적 역할
간단한 제목 변경이나 기본적인 메타데이터 설정에만 사용

### 3. NavigationComposer는 폴백 역할
명시적인 SEO 데이터가 없는 경우에만 메뉴 기반 자동 생성

## 구조화 데이터 (JSON-LD) 지원

### 브레드크럼 구조화 데이터
```php
private function generateBreadcrumbJsonLd($breadcrumb)
{
    if (empty($breadcrumb)) return null;
    
    $itemList = [];
    foreach ($breadcrumb as $index => $item) {
        $itemList[] = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $item['title'],
            'item' => $item['url'] ? url($item['url']) : null
        ];
    }
    
    return [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $itemList
    ];
}
```

### Article 구조화 데이터 (게시글)
```php
'json_ld' => [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $post->title,
    'author' => [
        '@type' => 'Person',
        'name' => $post->author_name
    ],
    'datePublished' => $post->created_at->toISOString(),
    'image' => $post->featured_image_url,
    // ...
]
```

## 성능 최적화

1. **메뉴 데이터 캐싱**: NavigationComposer에서 메뉴 데이터를 적절히 캐싱
2. **조건부 SEO 생성**: 이미 seoData가 있는 경우 추가 처리 스킵
3. **이미지 URL 최적화**: 이미지 URL 생성 시 불필요한 연산 최소화

## 설정 옵션

### config/sitemanager.php
```php
'seo' => [
    'auto_generate' => true,           // 자동 SEO 생성 활성화
    'include_breadcrumb_jsonld' => true, // 브레드크럼 JSON-LD 포함
    'default_og_image' => 'images/logo.svg', // 기본 OG 이미지
    'title_separator' => ' | ',        // 제목 구분자
    'max_description_length' => 160,   // 설명 최대 길이
],
```

이 문서는 SiteManager의 SEO 로직을 완전히 이해하고 올바르게 커스터마이징할 수 있도록 작성되었습니다.
