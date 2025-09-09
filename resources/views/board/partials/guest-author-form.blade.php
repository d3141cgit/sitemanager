{{-- 익명 사용자용 작성자 정보 입력 폼 --}}
<div class="guest-author-form mb-3" style="display: none;">
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="bi bi-person me-2"></i>
                작성자 정보
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="author_name" class="form-label">
                            <i class="bi bi-person-fill me-1"></i>
                            이름 <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="author_name" 
                               name="author_name" 
                               placeholder="작성자 이름을 입력하세요"
                               maxlength="100"
                               required>
                        <div class="invalid-feedback">이름을 입력해주세요.</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="author_email" class="form-label">
                            <i class="bi bi-envelope-fill me-1"></i>
                            이메일 <span class="text-danger">*</span>
                        </label>
                        <input type="email" 
                               class="form-control" 
                               id="author_email" 
                               name="author_email" 
                               placeholder="your@email.com"
                               maxlength="255"
                               required>
                        <div class="invalid-feedback">유효한 이메일을 입력해주세요.</div>
                        <small class="form-text text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            수정/삭제 시 인증에 사용됩니다
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info" role="alert">
                <h6 class="alert-heading">
                    <i class="bi bi-shield-check me-2"></i>
                    이메일 인증 안내
                </h6>
                <p class="mb-2">작성 완료 후 입력하신 이메일로 인증 링크가 발송됩니다.</p>
                <ul class="mb-0 small">
                    <li>이메일 인증을 완료해야 게시글/댓글이 공개됩니다</li>
                    <li>수정/삭제 시에도 동일한 이메일로 인증을 받으실 수 있습니다</li>
                    <li>인증 링크는 24시간 동안 유효합니다</li>
                </ul>
            </div>
            
            {{-- reCAPTCHA v2 --}}
            @if(config('sitemanager.security.recaptcha.enabled', false) && config('sitemanager.security.recaptcha.site_key'))
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
            
            {{-- 스팸 방지 필드들 --}}
            @include('sitemanager::board.partials.anti-spam-fields')
        </div>
    </div>
</div>

{{-- 로그인한 사용자용 안내 --}}
<div class="member-author-info mb-3" style="display: none;">
    <div class="alert alert-success" role="alert">
        <i class="bi bi-person-check me-2"></i>
        <strong>{{ Auth::user()->name ?? '' }}</strong>님으로 작성됩니다.
    </div>
</div>

<script>
// reCAPTCHA 스크립트 로드
@if(config('sitemanager.security.recaptcha.enabled', false) && config('sitemanager.security.recaptcha.site_key'))
if (!document.querySelector('script[src*="recaptcha"]')) {
    const script = document.createElement('script');
    script.src = 'https://www.google.com/recaptcha/api.js';
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
}

window.onCaptchaSuccess = function(token) {
    document.getElementById('captcha-error').style.display = 'none';
};

window.onCaptchaExpired = function() {
    document.getElementById('captcha-error').style.display = 'block';
    document.getElementById('captcha-error').textContent = '캡챠가 만료되었습니다. 다시 인증해주세요.';
};
@endif

// 작성자 정보 폼 표시/숨김 처리
document.addEventListener('DOMContentLoaded', function() {
    updateAuthorFormVisibility();
});

function updateAuthorFormVisibility() {
    const isLoggedIn = {{ Auth::check() ? 'true' : 'false' }};
    const guestForm = document.querySelector('.guest-author-form');
    const memberInfo = document.querySelector('.member-author-info');
    
    if (isLoggedIn) {
        if (guestForm) guestForm.style.display = 'none';
        if (memberInfo) memberInfo.style.display = 'block';
    } else {
        if (guestForm) guestForm.style.display = 'block';
        if (memberInfo) memberInfo.style.display = 'none';
    }
}

// 폼 검증
function validateGuestAuthorForm() {
    const isLoggedIn = {{ Auth::check() ? 'true' : 'false' }};
    
    if (isLoggedIn) {
        return true; // 로그인한 사용자는 검증 불필요
    }
    
    const nameInput = document.getElementById('author_name');
    const emailInput = document.getElementById('author_email');
    let isValid = true;
    
    // 이름 검증
    if (!nameInput.value.trim()) {
        nameInput.classList.add('is-invalid');
        isValid = false;
    } else {
        nameInput.classList.remove('is-invalid');
    }
    
    // 이메일 검증
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailInput.value.trim() || !emailRegex.test(emailInput.value)) {
        emailInput.classList.add('is-invalid');
        isValid = false;
    } else {
        emailInput.classList.remove('is-invalid');
    }
    
    // reCAPTCHA 검증
    @if(config('sitemanager.security.recaptcha.enabled', false) && config('sitemanager.security.recaptcha.site_key'))
    const recaptchaResponse = grecaptcha.getResponse();
    if (!recaptchaResponse) {
        document.getElementById('captcha-error').style.display = 'block';
        document.getElementById('captcha-error').textContent = '캡챠 인증을 완료해주세요.';
        isValid = false;
    }
    @endif
    
    return isValid;
}

// 폼 제출 전 검증
document.addEventListener('submit', function(e) {
    if (e.target.closest('form')) {
        if (!validateGuestAuthorForm()) {
            e.preventDefault();
            return false;
        }
    }
});
</script>

<style>
.guest-author-form .card {
    border-left: 4px solid #007bff;
}

.guest-author-form .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

@media (max-width: 768px) {
    .guest-author-form .row > .col-md-6 {
        margin-bottom: 1rem;
    }
}
</style>
