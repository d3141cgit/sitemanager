# Slug Generation Logic Documentation

## 개요
게시글의 slug 생성 로직을 `BoardPost` 모델에 중앙화하여 관리합니다.

## 메서드

### 1. `BoardPost::extractSlug(string $title, ?string $boardSlug = null, ?int $excludeId = null): string`

**정적 메서드** - 어디서든 사용 가능

제목에서 SEO 친화적인 slug를 생성합니다.

#### 처리 과정:
1. 제목을 소문자로 변환
2. 특수문자 제거 (한글, 영문, 숫자, 공백, 하이픈만 허용)
3. 공백을 하이픈으로 변환
4. 연속된 하이픈을 하나로 통합
5. 앞뒤 하이픈 제거
6. 빈 문자열이면 시간 기반으로 생성 (`post-{timestamp}`)
7. 중복 체크 (boardSlug 제공 시):
   - 같은 slug가 있으면 번호 추가 (`slug-1`, `slug-2` ...)
   - excludeId는 수정 시 자신을 제외하기 위함

#### 매개변수:
- `$title` (required): 게시글 제목
- `$boardSlug` (optional): 게시판 slug (중복 체크용)
- `$excludeId` (optional): 제외할 게시글 ID (수정 시)

#### 사용 예시:
```php
use SiteManager\Models\BoardPost;

// 1. 단순 slug 생성 (중복 체크 없음)
$slug = BoardPost::extractSlug('안녕하세요 Hello World');
// 결과: '안녕하세요-hello-world'

// 2. 중복 체크를 포함한 생성 (새 게시글)
$slug = BoardPost::extractSlug('제목', 'news');
// 결과: '제목' 또는 '제목-1' (중복 시)

// 3. 수정 시 (자신 제외)
$slug = BoardPost::extractSlug('제목', 'news', 123);
// 결과: '제목' (자신(ID=123)은 제외하고 체크)
```

### 2. `$post->generateSlug(): string`

**인스턴스 메서드** - 게시글 객체에서 사용

현재 게시글의 slug를 반환하거나, 없으면 title로부터 자동 생성합니다.

#### 특징:
- slug가 이미 있으면 그대로 반환
- 없으면 `extractSlug()` 메서드를 사용하여 생성
- 게시판 slug와 게시글 ID를 자동으로 전달 (중복 체크)

#### 사용 예시:
```php
$post = BoardPostNews::find(1);

// slug가 있으면 반환, 없으면 title로부터 생성
$slug = $post->generateSlug();
```

## 적용된 곳

### 1. 게시글 작성/수정 (BoardService)

```php
// createPost 메서드
$post->slug = !empty($data['slug']) 
    ? $data['slug']           // 폼에서 입력한 slug 사용
    : $post->generateSlug();  // 없으면 자동 생성

// updatePost 메서드
if (!empty($data['slug'])) {
    $post->slug = $data['slug'];
} elseif ($post->wasChanged('title')) {
    $post->slug = $post->generateSlug();  // 제목 변경 시 재생성
}
```

`BoardService`에서 게시글 작성/수정 시 slug를 자동으로 처리합니다.

### 2. Slug 생성 API (BoardController)

```php
public function generateSlugFromTitle(Request $request, string $boardSlug)
{
    $title = $request->input('title');
    $postId = $request->input('post_id');  // 수정 시
    
    // BoardPost 모델의 extractSlug 메서드 사용
    $generatedSlug = \SiteManager\Models\BoardPost::extractSlug(
        $title, 
        $boardSlug, 
        $postId
    );
    
    return response()->json(['slug' => $generatedSlug]);
}
```

프론트엔드에서 "Generate" 버튼 클릭 시 사용됩니다.

### 3. 일괄 업데이트 (SiteManagerBoardController)

```php
public function bulkUpdateSlugs(Board $board)
{
    $postClass = \SiteManager\Models\BoardPost::forBoard($board->slug);
    
    foreach ($posts as $post) {
        $slug = $postClass::extractSlug($post->title, $board->slug, $post->id);
        $post->update(['slug' => $slug]);
    }
}
```

게시판 관리에서 모든 게시글의 slug를 일괄 업데이트합니다.

## 특징

### 한글 지원
한글을 그대로 slug에 포함할 수 있습니다:
```php
'안녕하세요' → '안녕하세요'
'Hello 월드' → 'hello-월드'
```

### 특수문자 처리
다음 문자만 허용됩니다:
- 한글 (`\p{L}`)
- 영문 (`\p{L}`)
- 숫자 (`\p{N}`)
- 하이픈 (`-`)

나머지는 모두 제거됩니다:
```php
'Hello, World!' → 'hello-world'
'가격: 1,000원' → '가격-1000원'
```

### 중복 처리
같은 게시판에 동일한 slug가 있으면 번호를 추가합니다:
```php
'hello-world'      // 첫 번째
'hello-world-1'    // 두 번째
'hello-world-2'    // 세 번째
```

### 빈 제목 처리
제목이 비어있거나 특수문자만 있는 경우:
```php
'' → 'post-1730198400'  // 타임스탬프 기반
'!!!' → 'post-1730198400'
```

## 메서드 비교

| 메서드 | 타입 | 중복 체크 | 반환값 | 용도 |
|--------|------|-----------|--------|------|
| `extractSlug()` | 정적 | ✅ (optional) | string | 제목에서 slug 추출 |
| `generateSlug()` | 인스턴스 | ✅ (자동) | string | slug 조회/생성 (읽기) |

## 관리자 기능

### 게시판 관리 페이지
각 게시판마다 "Bulk Update Slugs" 버튼 제공:
- 해당 게시판의 모든 게시글 slug 재생성
- 제목 기반으로 자동 생성
- 중복 시 자동으로 번호 추가

## 장점

1. **중앙 관리**: 로직이 한 곳에 있어 수정이 쉬움
2. **일관성**: 모든 곳에서 동일한 로직 사용
3. **재사용성**: 정적 메서드로 어디서든 사용 가능
4. **한글 지원**: 한글 slug 완벽 지원
5. **중복 방지**: 자동 중복 체크 및 번호 추가
6. **자동화**: `BoardService`에서 자동으로 처리
7. **일괄 처리**: 관리자가 모든 게시글 slug 한번에 업데이트 가능

## 주의사항

- slug는 URL에 사용되므로 변경 시 기존 링크가 끊어질 수 있음
- 검색 엔진 최적화(SEO)를 위해 slug는 가급적 변경하지 않는 것이 좋음
- 멀티바이트 문자(한글 등) 안전하게 처리 (`mb_*` 함수 사용)
- `generateSlug()`는 DB에 저장하지 않고 결과만 반환
- `BoardService`가 게시글 작성/수정 시 자동으로 slug 처리
