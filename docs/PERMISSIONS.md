# SiteManager 권한 시스템 가이드

## 개요

SiteManager는 메뉴 기반의 계층적 권한 시스템을 사용합니다. 권한은 메뉴 단위로 설정되며, 각 기능별로 세분화된 권한을 제공합니다.

## 권한 구조

### 1. 메뉴 기반 권한 계층

```
Menu (메뉴)
├── Basic Permissions (기본 권한)
├── Level Permissions (레벨 권한)  
├── Group Permissions (그룹 권한)
└── Admin Permissions (관리자 권한)
```

### 2. 권한 계산 방식

최종 권한 = `max(basic, level, group, admin)` (비트마스크 OR 연산)

- **Basic**: 모든 사용자에게 적용되는 기본 권한
- **Level**: 사용자 레벨에 따른 권한
- **Group**: 사용자가 속한 그룹의 권한
- **Admin**: 관리자 권한 (일반적으로 모든 권한)

## 게시판 권한 종류

### 메뉴 권한 (비트마스크)

| 권한 | 비트값 | 설명 |
|-----|--------|------|
| `index` | 1 | 목록 보기 |
| `read` | 2 | 게시글 읽기 |
| `readComments` | 4 | 댓글 읽기 |
| `writeComments` | 8 | 댓글 작성 |
| `uploadCommentFiles` | 16 | 댓글 파일 업로드 |
| `write` | 32 | 게시글 작성 |
| `uploadFiles` | 64 | 파일 업로드 |
| `manage` | 128 | 게시글 관리 (완전 제어) |
| `manageComments` | 128 | 댓글 관리 (완전 제어) |

### 별칭 권한

PermissionHelper에서 제공하는 편의용 별칭들:

| 별칭 | 실제 비트값 | 설명 |
|-----|-----------|------|
| `view` | 1 | `index`와 동일 |
| `create` | 8 | `writeComments`와 동일 |
| `update` | 8 | `writeComments`와 동일 |
| `delete` | 128 | `manageComments`와 동일 |
| `reply` | 8 | `writeComments`와 동일 |

### 게시글 권한

```php
// BoardController::calculatePostPermissions()
[
    'canEdit' => bool,        // 수정 권한: 메뉴 관리 권한 OR (작성자 && 글 작성 권한)
    'canDelete' => bool,      // 삭제 권한: 메뉴 관리 권한 OR (작성자 && 글 작성 권한)
    'canWriteComments' => bool, // 댓글 작성: 게시판 설정 허용 && 메뉴 권한
    'canUploadFiles' => bool,   // 파일 업로드: 게시판 설정 허용 && 메뉴 권한
]
```

### 댓글 권한

```php
// BoardService::calculateCommentPermissions()
[
    'canEdit' => bool,     // 수정 권한: 댓글 관리 권한 OR 작성자 본인
    'canDelete' => bool,   // 삭제 권한: 댓글 관리 권한 OR 작성자 본인
    'canReply' => bool,    // 답글 권한: 댓글 작성 권한
    'canManage' => bool,   // 관리 권한: 댓글 관리 권한 (승인/거부)
]
```

## 사용법

### 1. 메뉴 권한 확인

```php
// Helper 함수 사용
if (can('read', $board)) {
    // 게시글 읽기 가능
}

// 직접 PermissionService 사용
$permissionService = app(\SiteManager\Services\PermissionService::class);
$permissions = $permissionService->getMenuPermissions($menuId, $user);
```

### 2. 게시글 권한 확인

```php
// Controller에서 계산된 권한 사용
$permissions = $this->calculatePostPermissions($board, $post);

// Blade에서 사용
@if($canEdit)
    <button>수정</button>
@endif
```

### 3. 댓글 권한 확인

```php
// Service에서 자동으로 계산되어 추가됨
$comments = $this->boardService->getPostComments($board, $post->id);

// Blade에서 사용
@if($comment->permissions['canEdit'])
    <button>수정</button>
@endif
```

## 보안 고려사항

### 1. 작성자 검증

```php
// ✅ 안전한 검증
$isAuthor = $user && $post->member_id && $post->member_id === $user->id;

// ❌ 취약한 검증 (member_id가 null일 때 문제)
$isAuthor = $user && $post->member_id === $user->id;
```

### 2. 메뉴 연결 확인

```php
if ($board->menu_id) {
    // 메뉴에 연결된 게시판: 메뉴 권한 시스템 사용
    $canRead = can('read', $board);
} else {
    // 메뉴에 연결되지 않은 게시판: 권한 없음
    $canRead = false;
}
```

## 권한 확장

### 1. 새로운 권한 추가

`config/permissions.php`에 새 권한 비트 추가:

```php
'board_permissions' => [
    'download' => 128,  // 새로운 권한
],
```

### 2. 권한 체크 헬퍼 확장

```php
// Helper 함수에 새 권한 추가
function can(string $permission, $board): bool
{
    // 새로운 권한 처리 로직
}
```

### 3. Controller/Service 확장

```php
// BoardController에 새 권한 계산 추가
private function calculatePostPermissions($board, $post): array
{
    // ...
    $canDownload = can('download', $board);
    
    return [
        // ...
        'canDownload' => $canDownload,
    ];
}
```

## 디버깅

### 1. 권한 디버깅

```php
// 사용자의 메뉴 권한 확인
$permissionService = app(\SiteManager\Services\PermissionService::class);
$permissions = $permissionService->getMenuPermissions($menuId, $user);
dd($permissions);

// 게시판 권한 확인
$boardPermissions = $this->calculatePostPermissions($board, $post);
dd($boardPermissions);
```

### 2. 로깅

```php
use Illuminate\Support\Facades\Log;

Log::info('Permission check', [
    'user_id' => $user?->id,
    'menu_id' => $board->menu_id,
    'permission' => 'read',
    'result' => can('read', $board)
]);
```

## 모범 사례

### 1. Controller에서 권한 계산

- ✅ 권한 로직을 Controller/Service에서 처리
- ✅ Blade 템플릿에서는 계산된 권한 변수만 사용
- ❌ Blade에서 직접 권한 계산 금지

### 2. 일관된 보안 검증

- ✅ 모든 작성자 검증에서 `member_id` null 체크
- ✅ 메뉴 연결 여부 확인
- ✅ 게시판 설정과 메뉴 권한 모두 확인

### 3. 성능 최적화

- ✅ 권한은 한 번 계산하여 재사용
- ✅ 필요한 권한만 계산
- ❌ 반복적인 권한 계산 방지

## 트러블슈팅

### 1. 권한이 제대로 작동하지 않을 때

1. 메뉴 ID 확인: `$board->menu_id`
2. 사용자 정보 확인: `auth()->user()`
3. 권한 설정 확인: 관리자 페이지의 메뉴 권한 설정
4. 캐시 클리어: `php artisan cache:clear`

### 2. 작성자 권한 문제

1. `member_id` 값 확인
2. 로그인 상태 확인
3. 작성자 검증 로직 확인

### 3. 게시판별 설정 문제

1. 게시판 설정 확인: `$board->getSetting()`
2. 메뉴 연결 확인: `$board->menu_id`
3. 권한 비트마스크 값 확인

---

**참고:** 이 문서는 SiteManager v1.0 기준으로 작성되었습니다. 버전 업데이트 시 권한 시스템이 변경될 수 있습니다.
