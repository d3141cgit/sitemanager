{{-- reCAPTCHA v3 지원 컴포넌트 --}}
@php
$version = config('sitemanager.security.recaptcha.version', 'v2');
$siteKey = config('sitemanager.security.recaptcha.site_key') ?? config('services.recaptcha.site_key');
$enabled = config('sitemanager.security.recaptcha.enabled', false);
@endphp

@if($enabled && $siteKey)
    @if($version === 'v3')
        {{-- reCAPTCHA v3 --}}
        <script src="https://www.google.com/recaptcha/api.js?render={{ $siteKey }}"></script>
        <script>
            // reCAPTCHA v3 토큰 생성
            function getCaptchaToken(action = 'submit') {
                return new Promise((resolve, reject) => {
                    grecaptcha.ready(function() {
                        grecaptcha.execute('{{ $siteKey }}', {action: action})
                            .then(function(token) {
                                resolve(token);
                            })
                            .catch(function(error) {
                                reject(error);
                            });
                    });
                });
            }
            
            // 폼 제출 시 자동으로 토큰 추가
            document.addEventListener('DOMContentLoaded', function() {
                const forms = document.querySelectorAll('form[data-recaptcha="true"]');
                
                forms.forEach(function(form) {
                    form.addEventListener('submit', async function(e) {
                        e.preventDefault();
                        
                        try {
                            const action = form.dataset.recaptchaAction || 'submit';
                            const token = await getCaptchaToken(action);
                            
                            // 기존 토큰 필드 제거
                            const existingTokenField = form.querySelector('input[name="g-recaptcha-response"]');
                            if (existingTokenField) {
                                existingTokenField.remove();
                            }
                            
                            // 새 토큰 필드 추가
                            const tokenField = document.createElement('input');
                            tokenField.type = 'hidden';
                            tokenField.name = 'g-recaptcha-response';
                            tokenField.value = token;
                            form.appendChild(tokenField);
                            
                            // 폼 제출
                            form.submit();
                            
                        } catch (error) {
                            console.error('reCAPTCHA v3 error:', error);
                            alert('보안 인증에 실패했습니다. 다시 시도해주세요.');
                        }
                    });
                });
            });
        </script>
    @else
        {{-- reCAPTCHA v2 --}}
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        <div class="g-recaptcha" 
             data-sitekey="{{ $siteKey }}"
             data-callback="onCaptchaSuccess"
             data-expired-callback="onCaptchaExpired"></div>
        
        <script>
            window.onCaptchaSuccess = function(token) {
                // v2 성공 콜백
                const errorDiv = document.getElementById('captcha-error');
                if (errorDiv) {
                    errorDiv.style.display = 'none';
                }
            };
            
            window.onCaptchaExpired = function() {
                // v2 만료 콜백
                const errorDiv = document.getElementById('captcha-error');
                if (errorDiv) {
                    errorDiv.style.display = 'block';
                    errorDiv.textContent = '캡챠가 만료되었습니다. 다시 인증해주세요.';
                }
            };
        </script>
    @endif
    
    <div class="invalid-feedback d-block" id="captcha-error" style="display: none !important;">
        캡챠 인증을 완료해주세요.
    </div>
@endif
