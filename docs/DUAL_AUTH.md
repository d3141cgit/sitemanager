# SiteManager Dual Auth System

## 개요

SiteManager는 프로젝트에 따라 자동으로 적절한 인증 시스템을 선택하는 Dual Auth 시스템을 제공합니다.

- **일반 모드**: 기본 Member 모델만 사용 (hanurichurch 등)
- **Dual Auth 모드**: EdmMember에서 Members로 사용자 이전/연동 (edmuhak2 등)

## 자동 선택 조건

시스템은 다음 조건을 자동으로 확인하여 적절한 서비스를 선택합니다:

1. `ENABLE_EDM_MEMBER_AUTH=true` 설정 여부
2. EdmMember 모델 클래스 존재 여부  
3. EdmMember 데이터베이스 연결 가능 여부

모든 조건이 만족되면 **DualAuthMemberService**를 사용하고, 하나라도 실패하면 **기본 MemberService**를 사용합니다.

## ⚠️ 중요: Dual Auth 모드 제한사항

**Dual Auth 모드에서는 EdmMember에 이미 존재하는 사용자만 Members로 이전할 수 있습니다.**

### 왜 이런 제한이 필요한가?

1. **ID 공간 일관성**: Members에 새로운 사용자를 직접 생성하면 EdmMember와 ID 공간이 어긋남
2. **충돌 방지**: 향후 EdmMember에 같은 ID로 사용자 추가 시 충돌 발생
3. **인증 시스템 일관성**: 두 시스템 간의 데이터 무결성 보장

### 새로운 사용자 생성 워크플로우

```
1. EdmMember에서 먼저 사용자 생성
2. DualAuthMemberService.migrateFromEdmMember()로 Members에 이전
3. 이후 두 시스템에서 동일한 ID로 인증 가능
```

## 사용법

### 1. EdmMember에서 Members로 사용자 이전

```php
use SiteManager\Services\MemberServiceFactory;
use SiteManager\Services\DualAuthMemberService;

class MemberController extends Controller
{
    /**
     * EdmMember 사용자를 Members로 이전
     */
    public function migrateUser(Request $request)
    {
        $memberService = MemberServiceFactory::create();
        
        // Dual Auth 모드 확인
        if ($memberService instanceof DualAuthMemberService) {
            try {
                // EdmMember에 존재하는 사용자를 Members로 이전
                $member = $memberService->migrateFromEdmMember(
                    $request->identifier, // 아이디 또는 이메일
                    ['password' => bcrypt($request->new_password)] // 추가 데이터
                );
                
                return response()->json(['member' => $member]);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 400);
            }
        }
        
        // 일반 모드에서는 기본 생성
        return $this->createRegularMember($request);
    }
    
    /**
     * 이전 가능한 EdmMember 사용자 목록 조회
     */
    public function getAvailableUsers()
    {
        $memberService = MemberServiceFactory::create();
        
        if ($memberService instanceof DualAuthMemberService) {
            $users = $memberService->getAvailableEdmMembers();
            return response()->json(['users' => $users]);
        }
        
        return response()->json(['users' => []]);
    }
}
```

### 2. 일반적인 회원가입 (기존 방식)

```php
class MemberController extends Controller
{
    public function store(Request $request)
    {
        // 팩토리가 자동으로 적절한 서비스 선택
        $memberService = MemberServiceFactory::create();
        
        if ($memberService instanceof DualAuthMemberService) {
            // Dual Auth 모드: EdmMember에 존재하는 사용자만 가능
            throw new \Exception('Dual Auth 모드에서는 EdmMember에서 먼저 사용자를 생성하고 migrateFromEdmMember()를 사용해주세요.');
        }
        
        // 일반 모드: 직접 생성 가능
        $member = $memberService->createMember([
            'username' => $request->username,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'name' => $request->name,
        ]);
        
        return response()->json(['member' => $member]);
    }
}
```

## Dual Auth 모드 동작 방식

### EdmMember → Members 이전 시스템

Dual Auth 모드에서는 안전한 사용자 이전을 위해 다음과 같이 동작합니다:

1. **기존 사용자 확인**: EdmMember에서 아이디/이메일로 사용자 찾기
2. **중복 검사**: 해당 mm_uid가 이미 Members에 있는지 확인
3. **데이터 이전**: EdmMember의 mm_uid를 Members.id로 사용하여 생성
4. **추가 데이터**: 필요시 새로운 패스워드나 추가 필드 설정

### 예시 시나리오

```php
// EdmMember 테이블 상태
// mm_uid=1234, mm_id='user123', mm_email='user@test.com', mm_name='홍길동'

// 이전 실행
$member = $memberService->migrateFromEdmMember('user123', [
    'password' => bcrypt('new_password'),
    'status' => 'active'
]);

// 결과: Members 테이블에 id=1234로 사용자 생성
// 동일한 ID로 두 시스템에서 인증 가능
```

### 사용 가능한 메서드

```php
// 단일 사용자 이전
$member = $memberService->migrateFromEdmMember('user@email.com');

// 추가 데이터와 함께 이전
$member = $memberService->migrateFromEdmMember('username', [
    'password' => bcrypt('new_password'),
    'role' => 'user'
]);

// 이전 가능한 사용자 목록 조회
$availableUsers = $memberService->getAvailableEdmMembers();
```

## 환경 설정

### .env 파일

```bash
# Dual Auth 활성화
ENABLE_EDM_MEMBER_AUTH=true

# EdmMember 데이터베이스 설정
EDM_MEMBER_DB_CONNECTION=mysql
EDM_MEMBER_DB_HOST=127.0.0.1
EDM_MEMBER_DB_PORT=3306
EDM_MEMBER_DB_DATABASE=edm_member
EDM_MEMBER_DB_USERNAME=root
EDM_MEMBER_DB_PASSWORD=password
```

### config/sitemanager.php

```php
return [
    'enable_edm_member_auth' => env('ENABLE_EDM_MEMBER_AUTH', false),
    // ... 다른 설정들
];
```

## 프로젝트별 적용

### edmuhak2 (Dual Auth 사용)
- `ENABLE_EDM_MEMBER_AUTH=true` 설정
- EdmMember 모델과 데이터베이스 연결 필요
- 자동으로 DualAuthMemberService 사용
- **새로운 사용자는 EdmMember에서 먼저 생성 후 이전**

### hanurichurch (일반 모드)
- `ENABLE_EDM_MEMBER_AUTH=false` 설정
- 또는 EdmMember 연결 불가
- 자동으로 기본 MemberService 사용
- **일반적인 회원가입 방식 사용**

## 장점

1. **데이터 무결성**: EdmMember를 단일 진실 소스로 사용
2. **안전한 ID 관리**: ID 충돌 완전 방지
3. **자동 감지**: 개발자가 수동으로 서비스를 선택할 필요 없음
4. **안전한 Fallback**: EdmMember 연결 실패 시 자동으로 기본 모드로 전환
5. **프로젝트별 유연성**: 같은 코드로 다른 프로젝트에서 다른 모드 사용

## 주의사항

- **Dual Auth 모드에서는 새로운 사용자 직접 생성 불가**
- EdmMember에 먼저 사용자를 생성한 후 `migrateFromEdmMember()` 사용
- EdmMember 데이터베이스 연결이 필수
- 기존 Members 데이터는 영향받지 않음

## 마이그레이션 가이드

### 기존 시스템에서 Dual Auth로 전환

1. **환경 설정**
   ```bash
   ENABLE_EDM_MEMBER_AUTH=true
   ```

2. **기존 Members 사용자 확인**
   ```php
   $availableUsers = $memberService->getAvailableEdmMembers();
   ```

3. **필요한 사용자 이전**
   ```php
   foreach ($availableUsers as $edmUser) {
       $memberService->migrateFromEdmMember($edmUser->mm_id);
   }
   ```