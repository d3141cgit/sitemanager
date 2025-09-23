# 게시판 시스템 EdmMember 호환성 점검 및 개선 사항

## 🔍 발견된 문제점들

### 1. **BoardController의 Auth 사용 문제**

**문제:** `Auth::user()`, `Auth::id()`, `Auth::check()` 등이 기본 가드만 사용
**영향:** EdmMember로 로그인한 사용자가 게시판에 접근할 때 인식되지 않음

**발견된 위치:**
```php
// BoardController.php
if ($post->isSecret() && !$post->canAccess(Auth::id())) // line 241
$user = Auth::user(); // line 1069
if (Auth::check() && Auth::id() === $post->member_id) // line 1034
```

### 2. **BoardService의 Auth 사용 문제**

**문제:** 게시글 작성, 권한 체크 시 기본 가드만 확인
**영향:** EdmMember 사용자가 게시글 작성, 수정, 삭제 불가

**발견된 위치:**
```php
// BoardService.php
if (Auth::check()) {
    $postData['member_id'] = Auth::id();
    $postData['author_name'] = Auth::user()->name;
    $postData['author_email'] = Auth::user()->email;
}
```

### 3. **BoardPost 모델의 권한 체크 문제**

**문제:** `canEdit()`, `canDelete()` 메서드에서 기본 가드만 확인
**영향:** EdmMember 사용자가 자신의 게시글도 수정/삭제 불가

### 4. **뷰 파일의 Auth 사용 문제**

**문제:** 댓글, 작성자 폼에서 기본 가드만 확인
**영향:** EdmMember 사용자의 UI가 비로그인 상태로 표시

---

## 🛠️ 해결 방안

### 1. **멀티 가드 지원 Helper 함수 추가**

```php
// app/Helpers/AuthHelper.php 또는 SiteManager 패키지 내부에 추가

if (!function_exists('current_user')) {
    function current_user()
    {
        // 우선순위: web 가드 -> customer 가드
        if (Auth::guard('web')->check()) {
            return Auth::guard('web')->user();
        }
        
        if (Auth::guard('customer')->check()) {
            return Auth::guard('customer')->user();
        }
        
        return null;
    }
}

if (!function_exists('current_user_id')) {
    function current_user_id()
    {
        $user = current_user();
        return $user ? $user->getId() : null;
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in()
    {
        return Auth::guard('web')->check() || Auth::guard('customer')->check();
    }
}
```

### 2. **BoardController 수정**

```php
// 기존 Auth::user() 대신 current_user() 사용
public function show(Board $board, $id, Request $request): View
{
    // 비밀글 접근 권한 확인
    if ($post->isSecret() && !$post->canAccess(current_user_id())) {
        // ...
    }
    
    // 기타 모든 Auth:: 호출을 멀티가드 지원 함수로 변경
}

// 첨부파일 삭제 권한 체크
public function deleteAttachment($attachment_id)
{
    $user = current_user();
    $canDelete = false;

    if ($user) {
        // 작성자 본인인지 확인 (Member와 EdmMember 모두 지원)
        if ($post && $post->member_id === $user->getId()) {
            $canDelete = true;
        }
        // 게시판 관리 권한 (PermissionService가 자동으로 EdmMember 지원)
        elseif ($board && $board->menu_id && can('manage', $board)) {
            $canDelete = true;
        }
    }
    
    // ...
}
```

### 3. **BoardService 수정**

```php
// 게시글 작성 시 멀티가드 지원
public function createPost(Board $board, array $data): array
{
    $postData = [
        // ... 기타 필드들
    ];

    // 로그인 사용자와 익명 사용자 구분 처리 (멀티가드 지원)
    if (is_logged_in()) {
        $user = current_user();
        $postData['member_id'] = $user->getId();
        $postData['author_name'] = $user->name ?? $user->mm_name ?? '사용자';
        $postData['author_email'] = $user->email ?? $user->mm_email ?? null;
    } else {
        // 익명 사용자 처리
        $postData['member_id'] = null;
        $postData['author_name'] = $data['author_name'] ?? '익명';
        // ...
    }
    
    // ...
}

// 댓글 수 계산 시 멀티가드 지원
public function getPostCommentsCount(Board $board, int $postId): int
{
    $commentModelClass = BoardComment::forBoard($board->slug);
    $currentUserId = current_user_id();
    
    return $commentModelClass::where(function($query) use ($currentUserId) {
            $query->where('status', 'approved');
            
            // 로그인한 사용자의 경우 본인 댓글도 포함
            if ($currentUserId) {
                $query->orWhere('member_id', $currentUserId);
            }
        })
        ->forPost($postId)
        ->count();
}
```

### 4. **BoardPost 모델 수정**

```php
// 권한 체크 메서드들을 멀티가드 지원으로 수정
public function canEdit(): bool
{
    $board = $this->getBoard();
    if (!$board || !$board->menu_id) return false;
    
    $user = current_user();
    $canManage = can('manage', $board);
    $canWrite = can('write', $board);
    $isAuthor = $user && $this->member_id && $this->member_id === $user->getId();
    
    return $canManage || ($isAuthor && $canWrite);
}

public function canDelete(): bool
{
    $board = $this->getBoard();
    if (!$board || !$board->menu_id) return false;
    
    $user = current_user();
    $canManage = can('manage', $board);
    $canWrite = can('write', $board);
    $isAuthor = $user && $this->member_id && $this->member_id === $user->getId();
    
    return $canManage || ($isAuthor && $canWrite);
}

// 비밀글 접근 권한도 수정
public function canAccess(?int $userId = null): bool
{
    if (!$this->isSecret()) {
        return true;
    }

    // 사용자 ID가 전달되지 않은 경우 현재 사용자 사용
    $userId = $userId ?? current_user_id();

    // 작성자는 항상 접근 가능
    if ($userId && $this->member_id === $userId) {
        return true;
    }

    // 관리자는 항상 접근 가능
    $user = current_user();
    if ($user && ($user->isAdmin() ?? false)) {
        return true;
    }

    // 세션에서 비밀번호 확인 여부 체크
    return $this->isPasswordVerified();
}
```

### 5. **뷰 파일 수정**

```blade
{{-- resources/views/board/partials/guest-author-form.blade.php --}}

{{-- 로그인 상태를 위한 데이터 속성 (멀티가드 지원) --}}
<div class="auth-config" 
     data-logged-in="{{ is_logged_in() ? 'true' : 'false' }}"
     data-user-name="{{ current_user()?->name ?? current_user()?->mm_name ?? '' }}"
     style="display: none;">
</div>
```

```blade
{{-- resources/views/board/partials/comment.blade.php --}}

@if($comment->canEdit())
    <li>
        @if(!is_logged_in() && $comment->email_verified_at)
            {{-- 비회원 댓글 수정 --}}
            <a class="dropdown-item" href="javascript:void(0)" onclick="requestEmailVerification({{ $comment->id }}, 'edit')">
                <i class="bi bi-pencil"></i> Edit
                <small class="text-muted d-block">Email/Password required</small>
            </a>
        @elseif(is_logged_in())
            {{-- 회원 댓글 수정 (Member/EdmMember 모두 지원) --}}
            <a class="dropdown-item" href="javascript:void(0)" onclick="editComment({{ $comment->id }})">
                <i class="bi bi-pencil"></i> Edit
            </a>
        @endif
    </li>
@endif
```

### 6. **member_id 필드 호환성 확보**

EdmMember의 `getId()` 메서드가 `mm_uid`를 반환하므로, 게시판의 `member_id` 필드에는 EdmMember의 `mm_uid` 값이 저장됩니다. 이는 정상적인 동작이지만, 조회 시 주의가 필요합니다.

```php
// BoardPost에서 작성자 정보 조회 (Member와 EdmMember 혼재 가능)
public function author()
{
    // member_id가 있는 경우, Member 테이블에서 먼저 찾기
    if ($this->member_id) {
        $member = \SiteManager\Models\Member::find($this->member_id);
        if ($member) {
            return $member;
        }
        
        // Member에 없으면 EdmMember에서 찾기
        $edmMember = \SiteManager\Models\EdmMember::where('mm_uid', $this->member_id)->first();
        if ($edmMember) {
            return $edmMember;
        }
    }
    
    return null;
}
```

---

## 🔧 구현 우선순위

### 1단계: Helper 함수 추가
- `current_user()`, `current_user_id()`, `is_logged_in()` 함수 구현
- SiteManager 패키지에 추가하여 전역에서 사용 가능하도록

### 2단계: Core 수정
- BoardController, BoardService의 주요 메서드 수정
- BoardPost 모델의 권한 체크 메서드 수정

### 3단계: UI 수정
- 뷰 파일들의 Auth 호출을 멀티가드 지원 함수로 변경
- JavaScript에서도 멀티가드 상태 인식하도록 수정

### 4단계: 테스트
- Member 사용자로 게시판 기능 테스트
- EdmMember 사용자로 게시판 기능 테스트
- 비로그인 사용자 게시판 기능 테스트

---

## ⚠️ 주의사항

1. **데이터 무결성**: 기존 게시글의 `member_id`는 Members 테이블의 ID이므로, EdmMember 사용자가 해당 게시글을 수정할 수 없습니다. 이는 정상적인 동작입니다.

2. **권한 시스템**: PermissionService는 이미 EdmMember를 지원하므로, `can()` 함수는 그대로 사용 가능합니다.

3. **세션 관리**: Member와 EdmMember는 서로 다른 가드를 사용하므로, 동시 로그인은 가능하지만 권한 체크 시 우선순위를 명확히 해야 합니다.

4. **성능**: `current_user()` 함수는 매번 두 가드를 체크하므로, 필요에 따라 캐싱 추가 고려

이러한 수정을 통해 게시판 시스템이 EdmMember와 완전히 호환되도록 할 수 있습니다.