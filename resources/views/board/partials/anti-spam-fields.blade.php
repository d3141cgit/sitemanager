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
<input type="hidden" name="form_timestamp" id="form_timestamp">

<script>
// 폼 로드 시간 기록
document.addEventListener('DOMContentLoaded', function() {
    const timestampField = document.getElementById('form_timestamp');
    if (timestampField) {
        timestampField.value = Date.now();
    }
});

// 추가 봇 탐지 - 마우스 움직임 기록
let mouseMovements = 0;
let keystrokes = 0;

document.addEventListener('mousemove', function() {
    mouseMovements++;
});

document.addEventListener('keydown', function() {
    keystrokes++;
});

// 폼 제출 시 검증
document.addEventListener('submit', function(e) {
    const form = e.target;
    
    // 허니팟 필드 검증
    const honeypotFields = ['website', 'url', 'homepage', 'phone_number', 'email_confirm', 'company', 'address'];
    for (let field of honeypotFields) {
        const input = form.querySelector(`input[name="${field}"]`);
        if (input && input.value.trim() !== '') {
            e.preventDefault();
            console.warn('Honeypot field filled:', field);
            return false;
        }
    }
    
    // 체크박스 트랩 검증
    const subscribeCheckbox = form.querySelector('input[name="subscribe_newsletter"]');
    if (subscribeCheckbox && subscribeCheckbox.checked) {
        e.preventDefault();
        console.warn('Honeypot checkbox checked');
        return false;
    }
    
    // 텍스트에어리어 트랩 검증
    const messageBackup = form.querySelector('textarea[name="message_backup"]');
    if (messageBackup && messageBackup.value.trim() !== '') {
        e.preventDefault();
        console.warn('Honeypot textarea filled');
        return false;
    }
    
    // 최소 상호작용 검증
    if (mouseMovements < 3 && keystrokes < 5) {
        console.warn('Insufficient user interaction detected');
        // 경고만 하고 제출은 허용 (너무 엄격하면 정상 사용자도 차단될 수 있음)
    }
    
    // 사용자 행동 데이터 추가
    const behaviorData = {
        mouse_movements: mouseMovements,
        keystrokes: keystrokes,
        form_focus_time: Date.now() - (parseInt(document.getElementById('form_timestamp').value) || Date.now())
    };
    
    // 행동 데이터를 숨겨진 필드로 추가
    const behaviorField = document.createElement('input');
    behaviorField.type = 'hidden';
    behaviorField.name = 'user_behavior';
    behaviorField.value = JSON.stringify(behaviorData);
    form.appendChild(behaviorField);
});
</script>

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
