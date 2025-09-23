{{-- 익명 사용자용 작성자 정보 입력 폼 --}}

<div class="guest-author-form">
    <div class="form-group">
        <input type="text" class="form-control form-control-sm" id="author_name" name="author_name" placeholder="Name" maxlength="100" required>
        <input type="email" class="form-control form-control-sm" id="author_email" name="author_email" placeholder="your@email.com" maxlength="255" required>
        <span class="email-verification-info-btn" title="Click for email verification details">
            <i class="bi bi-info-circle"></i>
        </span>
    </div>

    {{-- 숨겨진 상세 정보 --}}
    <div class="email-verification-details" style="display: none;">
        <div class="alert alert-info mt-2" role="alert">
            <h6 class="alert-heading">
                <i class="bi bi-shield-check me-2"></i>
                Email Verification Notice
            </h6>
            <p class="mb-2">After submission, a verification link will be sent to your email address.</p>
            <ul class="mb-0 small">
                <li>You must complete email verification for your post/comment to be published.</li>
                <li>You can set a password for editing/deleting during the verification process.</li>
                <li>The verification link is valid for 24 hours.</li>
            </ul>
        </div>
    </div>
</div>

{{-- reCAPTCHA (현재 비활성화됨 - 키 타입 불일치로 인해) --}}
@if(config('sitemanager.security.recaptcha.enabled', false) && config('sitemanager.security.recaptcha.site_key'))
    @if(config('sitemanager.security.recaptcha.version') === 'v3')
        {{-- reCAPTCHA v3 (invisible) --}}
        <input type="hidden" name="recaptcha_token" id="recaptcha_token">
    @else
        {{-- reCAPTCHA v2 (checkbox) --}}
        <div class="mb-3">
            <div class="g-recaptcha" 
                    data-sitekey="{{ config('sitemanager.security.recaptcha.site_key') ?? config('services.recaptcha.site_key') }}"
                    data-callback="onCaptchaSuccess"
                    data-expired-callback="onCaptchaExpired"></div>
            <div class="invalid-feedback d-block" id="captcha-error" style="display: none !important;">
                캡챠 인증을 완료해주세요.
            </div>
        </div>
    @endif
@endif

{{-- 스팸 방지 필드들 --}}
@include('sitemanager::board.partials.anti-spam-fields')

{{-- reCAPTCHA 설정을 위한 데이터 속성 --}}
@if(config('sitemanager.security.recaptcha.enabled', false) && config('sitemanager.security.recaptcha.site_key'))
<div class="recaptcha-config" 
     data-enabled="true"
     data-version="{{ config('sitemanager.security.recaptcha.version', 'v2') }}"
     data-site-key="{{ config('sitemanager.security.recaptcha.site_key') ?? config('services.recaptcha.site_key') }}"
     style="display: none;">
</div>
@endif

{{-- 로그인 상태를 위한 데이터 속성 --}}
<div class="auth-config" 
     data-logged-in="{{ is_logged_in() ? 'true' : 'false' }}"
     data-user-name="{{ current_user()?->name ?? '' }}"
     style="display: none;">
</div>
