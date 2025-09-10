{{-- 스팸 방지 숨겨진 필드들 (허니팟) --}}
@if(config('sitemanager.security.honeypot.enabled', true))
<div style="position: absolute; left: -9999px; opacity: 0; pointer-events: none;" aria-hidden="true">
    {{-- 봇이 채울 가능성이 높은 필드들 --}}
    <input type="text" name="website" tabindex="-1" autocomplete="off" placeholder="Website">
    <input type="url" name="url" tabindex="-1" autocomplete="off" placeholder="URL">
    <input type="text" name="homepage" tabindex="-1" autocomplete="off" placeholder="Homepage">
    <input type="tel" name="phone_number" tabindex="-1" autocomplete="off" placeholder="Phone">
    
    {{-- 추가 트랩 필드들 --}}
    <input type="email" name="email_confirm" tabindex="-1" autocomplete="off" placeholder="Confirm Email">
    <input type="text" name="company" tabindex="-1" autocomplete="off" placeholder="Company">
    <input type="text" name="address" tabindex="-1" autocomplete="off" placeholder="Address">
    
    {{-- 체크박스 트랩 --}}
    <label>
        <input type="checkbox" name="subscribe_newsletter" tabindex="-1">
        Subscribe to newsletter
    </label>
    
    {{-- 텍스트에어리어 트랩 --}}
    <textarea name="message_backup" tabindex="-1" autocomplete="off" placeholder="Additional message"></textarea>
</div>

{{-- 폼 토큰 (제출 시간 검증용) --}}
@php
$formToken = app(\SiteManager\Services\EmailVerificationService::class)->generateFormToken();
@endphp
<input type="hidden" name="form_token" value="{{ $formToken }}">

{{-- 타임스탬프 (JavaScript로 설정) --}}
<input type="hidden" name="form_timestamp" class="form-timestamp">

{{-- Anti-spam 설정을 위한 데이터 속성 --}}
<div class="anti-spam-config" 
     data-enabled="{{ config('sitemanager.security.honeypot.enabled', true) ? 'true' : 'false' }}"
     style="display: none;">
</div>

<style>
/* 추가 CSS 트랩 - 봇이 이 스타일을 무시할 가능성이 높음 */
input[name="website"],
input[name="url"],
input[name="homepage"],
input[name="phone_number"],
input[name="email_confirm"],
input[name="company"],
input[name="address"],
input[name="subscribe_newsletter"],
textarea[name="message_backup"] {
    display: none !important;
    visibility: hidden !important;
    position: absolute !important;
    left: -9999px !important;
    top: -9999px !important;
    width: 0 !important;
    height: 0 !important;
    opacity: 0 !important;
    z-index: -1 !important;
}
</style>
@endif
