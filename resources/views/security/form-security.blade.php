{{-- SiteManager 통합 보안 컴포넌트 --}}
{{-- 사용법: @include('sitemanager::security.form-security', ['action' => 'contact_form', 'tokenField' => 'recaptcha_token']) --}}

@php
$siteKey = config('sitemanager.security.recaptcha.site_key') ?? config('services.recaptcha.site_key');
$enabled = config('sitemanager.security.recaptcha.enabled', false) || config('services.recaptcha.enabled', false);
$action = $action ?? 'form_submit';
$tokenField = $tokenField ?? 'recaptcha_token';
$formId = $formId ?? null;
$autoSubmit = $autoSubmit ?? true;
$honeypot = $honeypot ?? true;
// 허니팟 필드는 config 에서 읽음 — 폼의 실제 필드명 (phone 등) 과 충돌하지 않도록.
// Chrome 자동완성이 'phone_number' 같은 hidden 필드도 채워서 정상 사용자가 차단되는 사례
// 때문에 기본값에서 phone_number 를 제외함.
$honeypotFields = config('sitemanager.security.honeypot.fields', ['website', 'url', 'homepage', 'company_phone']);
@endphp

@if($enabled && $siteKey)
    {{-- reCAPTCHA v3 스크립트 (한 번만 로드) --}}
    @once('sitemanager-recaptcha-script')
        <script src="https://www.google.com/recaptcha/api.js?render={{ $siteKey }}"></script>
    @endonce
@endif

{{-- 보안 설정을 JavaScript 에 전달 — reCAPTCHA 활성 여부와 무관하게 honeypot 설정을 노출.
     security.js 의 하드코딩 fallback 대신 config 기반 필드 목록을 사용하기 위함. --}}
<script>
    window.siteManagerSecurityConfig = window.siteManagerSecurityConfig || {
        recaptcha: {
            enabled: {{ $enabled ? 'true' : 'false' }},
            siteKey: '{{ $siteKey ?? '' }}',
            version: 'v3'
        },
        honeypot: {
            enabled: {{ $honeypot ? 'true' : 'false' }},
            fields: @json(array_values($honeypotFields))
        }
    };

    @if($formId)
    // 특정 폼 보안 설정
    document.addEventListener('DOMContentLoaded', function() {
        if (window.siteManagerSecurity) {
            const form = document.getElementById('{{ $formId }}');
            if (form) {
                window.siteManagerSecurity.setupForm(form, {
                    action: '{{ $action }}',
                    tokenField: '{{ $tokenField }}',
                    autoSubmit: {{ $autoSubmit ? 'true' : 'false' }}
                });
            }
        }
    });
    @endif
</script>

@if($honeypot)
    {{-- Honeypot 필드들 (봇 차단용) — config 기반 동적 렌더링.
         display:none 은 Chrome autofill / 비밀번호 매니저가 hidden 필드를 채우는 것을 줄여줌
         (position:-9999px / opacity:0 만으로는 autofill 이 동작함). --}}
    <div style="display: none !important;" aria-hidden="true">
        @foreach($honeypotFields as $hpField)
            <input type="text" name="{{ $hpField }}" tabindex="-1" autocomplete="off">
        @endforeach
    </div>
@endif

{{-- 보안 관련 hidden 필드들 --}}
@if($enabled)
    <input type="hidden" name="{{ $tokenField }}" id="{{ $tokenField }}_{{ $formId ?? 'default' }}">
@endif
<input type="hidden" name="form_token" value="{{ csrf_token() }}">
@php
    $formTimestamp = time();
    $formTimestampSig = hash_hmac('sha256', (string) $formTimestamp, config('app.key'));
@endphp
<input type="hidden" name="form_timestamp" value="{{ $formTimestamp }}">
<input type="hidden" name="form_timestamp_sig" value="{{ $formTimestampSig }}">

{{-- 에러 표시 영역 --}}
<div class="security-error" id="security-error-{{ $formId ?? 'default' }}" style="display: none;">
    <div class="alert alert-danger">
        보안 검증에 실패했습니다. 다시 시도해주세요.
    </div>
</div>