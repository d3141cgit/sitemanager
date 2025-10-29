# Excerpt Extraction Logic Documentation

## 개요
게시글의 excerpt(요약) 추출 로직을 `BoardPost` 모델에 중앙화하여 관리합니다.

## 메서드

### 1. `BoardPost::extractExcerpt(string $content, int $length = 200): string`

**정적 메서드** - 어디서든 사용 가능

HTML 컨텐츠에서 깔끔한 텍스트 excerpt를 추출합니다.

#### 처리 과정:
1. HTML 태그 제거 (`strip_tags`)
2. HTML 엔티티 디코딩 (`&nbsp;`, `&amp;` 등 → 일반 문자)
3. 연속된 공백을 하나로 통합
4. 앞뒤 공백 제거
5. 지정된 길이로 자르기
6. 문장이 자연스럽게 끊기도록 처리:
   - 마침표가 중간 이상에 있으면 마침표까지
   - 공백이 중간 이상에 있으면 공백까지 + `...`
   - 적절한 위치가 없으면 그냥 자르고 + `...`

#### 사용 예시:
```php
use SiteManager\Models\BoardPost;

// 정적 메서드로 직접 사용
$excerpt = BoardPost::extractExcerpt($htmlContent, 200);

// 동적 모델 클래스에서 사용
$postClass = BoardPost::forBoard('news');
$excerpt = $postClass::extractExcerpt($post->content, 150);
```

### 2. `$post->generateExcerpt(int $length = 200): string`

**인스턴스 메서드** - 게시글 객체에서 사용

현재 게시글의 excerpt를 반환하거나, 없으면 content로부터 자동 생성합니다.

#### 특징:
- excerpt가 이미 있으면 그대로 반환 (DB 저장 안 함)
- 없으면 `extractExcerpt()` 메서드를 사용하여 생성
- DB에 저장하지 않고 결과만 반환

#### 사용 예시:
```php
$post = BoardPostNews::find(1);

// excerpt가 있으면 반환, 없으면 content로부터 생성
$excerpt = $post->generateExcerpt(200);
```

### 3. `$post->updateExcerptFromContent(int $length = 200): bool`

**인스턴스 메서드** - 게시글 객체에서 사용

현재 게시글의 content로부터 excerpt를 생성하여 DB에 저장합니다.

#### 사용 예시:
```php
$post = BoardPostNews::find(1);
$post->updateExcerptFromContent(200); // excerpt 자동 생성 및 저장
```

## 적용된 곳

### 1. 게시글 작성/수정 (BoardService)

```php
// createPost 메서드
$post->excerpt = !empty($data['excerpt']) 
    ? $data['excerpt']           // 폼에서 입력한 excerpt 사용
    : $post->generateExcerpt();  // 없으면 자동 생성

// updatePost 메서드
if (!empty($data['excerpt'])) {
    $post->excerpt = $data['excerpt'];
} elseif ($post->wasChanged('title') || $post->wasChanged('content')) {
    $post->excerpt = $post->generateExcerpt();  // 내용 변경 시 재생성
}
```

`BoardService`에서 게시글 작성/수정 시 excerpt를 자동으로 처리합니다.

### 2. Excerpt 생성 API (BoardController)

```php
public function generateExcerptFromContent(Request $request)
{
    $content = $request->input('content');
    $length = $request->input('length', 200);
    
    // BoardPost 모델의 extractExcerpt 메서드 사용
    $excerpt = \SiteManager\Models\BoardPost::extractExcerpt($content, $length);
    
    return response()->json(['excerpt' => $excerpt]);
}
```

프론트엔드에서 "Generate" 버튼 클릭 시 사용됩니다.

### 3. 일괄 업데이트 (SiteManagerBoardController)

```php
public function bulkUpdateExcerpts(Board $board)
{
    $postClass = \SiteManager\Models\BoardPost::forBoard($board->slug);
    
    foreach ($posts as $post) {
        $excerpt = $postClass::extractExcerpt($post->content, 200);
        $post->update(['excerpt' => $excerpt]);
    }
}
```

게시판 관리에서 모든 게시글의 excerpt를 일괄 업데이트합니다.

## 메서드 비교

| 메서드 | 타입 | DB 저장 | 반환값 | 용도 |
|--------|------|---------|--------|------|
| `extractExcerpt()` | 정적 | ❌ | string | HTML에서 텍스트 추출 |
| `generateExcerpt()` | 인스턴스 | ❌ | string | excerpt 조회/생성 (읽기) |
| `updateExcerptFromContent()` | 인스턴스 | ✅ | bool | excerpt 생성 및 저장 |

## 장점

1. **중앙 관리**: 로직이 한 곳에 있어 수정이 쉬움
2. **일관성**: 모든 곳에서 동일한 로직 사용
3. **재사용성**: 정적 메서드로 어디서든 사용 가능
4. **유지보수**: 로직 변경 시 한 곳만 수정하면 됨
5. **자동화**: `BoardService`에서 자동으로 처리

## HTML 엔티티 처리

다음 HTML 엔티티들이 자동으로 일반 문자로 변환됩니다:
- `&nbsp;` → 공백
- `&amp;` → `&`
- `&lt;` → `<`
- `&gt;` → `>`
- `&quot;` → `"`
- `&#39;` → `'`

## 주의사항

- `extractExcerpt`는 순수 텍스트만 반환 (HTML 태그 없음)
- 빈 content의 경우 빈 문자열 반환
- 멀티바이트 문자(한글 등) 안전하게 처리 (`mb_*` 함수 사용)
- `generateExcerpt()`는 DB에 저장하지 않고 결과만 반환
- `BoardService`가 게시글 작성/수정 시 자동으로 excerpt 처리
