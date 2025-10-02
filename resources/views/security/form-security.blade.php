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
@endphp

@if($enabled && $siteKey)
    {{-- reCAPTCHA v3 스크립트 (한 번만 로드) --}}
    @once('sitemanager-recaptcha-script')
        <script src="https://www.google.com/recaptcha/api.js?render={{ $siteKey }}"></script>
    @endonce
    
    {{-- 보안 설정을 JavaScript에 전달 --}}
    <script>
        window.siteManagerSecurityConfig = window.siteManagerSecurityConfig || {
            recaptcha: {
                enabled: {{ $enabled ? 'true' : 'false' }},
                siteKey: '{{ $siteKey }}',
                version: 'v3'
            },
            honeypot: {
                enabled: {{ $honeypot ? 'true' : 'false' }},
                fields: ['website', 'url', 'homepage', 'phone_number', 'company_phone']
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
@endif

@if($honeypot)
    {{-- Honeypot 필드들 (봇 차단용) --}}
    <div style="position: absolute; left: -9999px; opacity: 0;" aria-hidden="true">
        <input type="text" name="website" tabindex="-1" autocomplete="off">
        <input type="text" name="url" tabindex="-1" autocomplete="off">
        <input type="text" name="homepage" tabindex="-1" autocomplete="off">
        <input type="text" name="phone_number" tabindex="-1" autocomplete="off">
        <input type="text" name="company_phone" tabindex="-1" autocomplete="off">
    </div>
@endif

{{-- 보안 관련 hidden 필드들 --}}
@if($enabled)
    <input type="hidden" name="{{ $tokenField }}" id="{{ $tokenField }}_{{ $formId ?? 'default' }}">
@endif
<input type="hidden" name="form_token" value="{{ csrf_token() }}">
<input type="hidden" name="form_timestamp" value="{{ time() }}">

{{-- 에러 표시 영역 --}}
<div class="security-error" id="security-error-{{ $formId ?? 'default' }}" style="display: none;">
    <div class="alert alert-danger">
        보안 검증에 실패했습니다. 다시 시도해주세요.
    </div>
</div>