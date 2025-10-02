# SiteManager 통합 보안 시스템 사용 가이드

## 개요
SiteManager 통합 보안 시스템은 모든 폼에서 일관된 보안 기능을 제공합니다.
- reCAPTCHA v3 (Google invisible captcha)
- Honeypot 필드 (봇 차단)
- 사용자 행동 추적
- Rate Limiting
- 폼 제출 시간 검증

## 사용 방법

### 1. 자동 모드 (권장)

폼에 `data-recaptcha="true"` 속성만 추가하면 자동으로 보안 기능이 적용됩니다.

```blade
{{-- 1. 보안 컴포넌트 포함 --}}
@include('sitemanager::security.form-security', [
    'action' => 'contact_form',
    'tokenField' => 'recaptcha_token',
    'formId' => 'contact-form'
])

{{-- 2. 폼에 data-recaptcha="true" 속성 추가 --}}
<form method="POST" action="/contact" id="contact-form" data-recaptcha="true" data-recaptcha-action="contact_form">
    @csrf
    
    <input type="text" name="name" required>
    <input type="email" name="email" required>
    <textarea name="message" required></textarea>
    
    <button type="submit">Send Message</button>
</form>
```

### 2. 수동 모드

JavaScript로 직접 제어하고 싶은 경우:

```blade
{{-- 1. 보안 컴포넌트 포함 (autoSubmit = false) --}}
@include('sitemanager::security.form-security', [
    'action' => 'contact_form',
    'formId' => 'contact-form',
    'autoSubmit' => false
])

<form method="POST" action="/contact" id="contact-form">
    @csrf
    <!-- 폼 필드들 -->
    <button type="submit">Send Message</button>
</form>

<script>
document.getElementById('contact-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // 보안 검증
    const validation = await window.siteManagerSecurity.validateForm(this, {
        action: 'contact_form'
    });
    
    if (!validation.valid) {
        alert('보안 검증 실패: ' + validation.error);
        return;
    }
    
    // 보안 데이터 추가
    window.siteManagerSecurity.addSecurityData(this, {
        token: validation.token,
        tokenField: 'recaptcha_token'
    });
    
    // 폼 제출
    this.submit();
});
</script>
```

### 3. 단순 include 방식

기존 방식과 호환되도록 단순하게 사용:

```blade
{{-- head 섹션에서 --}}
@include('sitemanager::security.form-security')

{{-- 폼에서 --}}
<form data-recaptcha="true" data-recaptcha-action="my_action">
    @csrf
    <!-- 폼 내용 -->
</form>
```

## 설정

### .env 파일 설정

```env
# reCAPTCHA 설정 (기존과 동일)
RECAPTCHA_SITE_KEY=your_site_key_here
RECAPTCHA_SECRET_KEY=your_secret_key_here
RECAPTCHA_SCORE_THRESHOLD=0.5

# SiteManager 통합 보안 설정
SITEMANAGER_RECAPTCHA_ENABLED=true
SITEMANAGER_HONEYPOT_ENABLED=true
SITEMANAGER_BEHAVIOR_TRACKING_ENABLED=true
```

### 컴포넌트 옵션

| 옵션 | 기본값 | 설명 |
|------|--------|------|
| `action` | 'form_submit' | reCAPTCHA action 이름 |
| `tokenField` | 'recaptcha_token' | 토큰이 저장될 필드명 |
| `formId` | null | 특정 폼 ID (설정시 자동 바인딩) |
| `autoSubmit` | true | 자동 폼 제출 여부 |
| `honeypot` | true | Honeypot 필드 생성 여부 |

## 서버 사이드 검증

SiteManager 통합 보안 서비스 사용:

```php
// Controller에서
use SiteManager\Services\SecurityService;

public function submitForm(Request $request)
{
    $securityService = app(SecurityService::class);
    
    // reCAPTCHA 검증
    $recaptchaToken = $request->input('recaptcha_token');
    if (!$securityService->verifyCaptcha($recaptchaToken, $request->ip(), 'contact_form')) {
        return back()->with('error', 'reCAPTCHA 검증 실패');
    }
    
    // Honeypot 검증
    $honeypotFields = ['website', 'url', 'homepage', 'phone_number', 'company_phone'];
    foreach ($honeypotFields as $field) {
        if (!empty($request->input($field))) {
            return back()->with('error', '잘못된 접근입니다.');
        }
    }
    
    // 사용자 행동 데이터 검증
    $behaviorData = json_decode($request->input('user_behavior'), true);
    if ($behaviorData && $behaviorData['timeSpent'] < 3000) { // 3초 미만
        return back()->with('error', '너무 빠른 제출입니다.');
    }
    
    // 폼 처리...
}
```

## 프로젝트별 적용

### edmkorean.com 프로젝트에 적용

```bash
# 1. 기존 개별 보안 코드 제거
# 2. SiteManager 통합 보안 컴포넌트로 대체
```

```blade
{{-- request-quotation.blade.php --}}
@push('head')
    @include('sitemanager::security.form-security', [
        'action' => 'request_quotation',
        'formId' => 'quotation-form'
    ])
@endpush

<form method="POST" action="{{ route('request-quotation.submit') }}" 
      id="quotation-form" data-recaptcha="true" data-recaptcha-action="request_quotation">
    @csrf
    <!-- 폼 필드들 -->
</form>
```

### 다른 프로젝트에서 사용

```blade
{{-- 단순히 include만 하면 됨 --}}
@include('sitemanager::security.form-security')

<form data-recaptcha="true" data-recaptcha-action="my_form">
    <!-- 폼 내용 -->
</form>
```

## 장점

1. **코드 중복 제거**: 모든 프로젝트에서 동일한 보안 기능 사용
2. **일관된 보안**: 표준화된 보안 정책 적용
3. **쉬운 유지보수**: 한 곳에서 보안 로직 관리
4. **확장성**: 새로운 보안 기능 추가시 모든 프로젝트에 자동 적용
5. **하위 호환성**: 기존 코드와 호환 가능