{{-- SiteManager 통합 보안 시스템 - reCAPTCHA v3 컴포넌트 --}}
@php
$siteKey = config('sitemanager.security.recaptcha.site_key') ?? config('services.recaptcha.site_key');
$enabled = config('sitemanager.security.recaptcha.enabled', false) || config('services.recaptcha.enabled', false);
$action = $action ?? 'form_submit';
$tokenField = $tokenField ?? 'recaptcha_token';
$formSelector = $formSelector ?? 'form[data-recaptcha="true"]';
@endphp

@if($enabled && $siteKey)
    {{-- reCAPTCHA v3 스크립트 로딩 (중복 방지) --}}
    @once('recaptcha-script')
        <script src="https://www.google.com/recaptcha/api.js?render={{ $siteKey }}"></script>
    @endonce
    
    {{-- 통합 보안 JavaScript 라이브러리 --}}
    @once('sitemanager-security')
        <script>
            /**
             * SiteManager 통합 보안 시스템
             * reCAPTCHA v3, Honeypot, Rate Limiting 등 모든 보안 기능 통합
             */
            window.SiteManagerSecurity = class {
                constructor() {
                    this.siteKey = '{{ $siteKey }}';
                    this.isReady = false;
                    this.init();
                }
                
                init() {
                    if (typeof grecaptcha !== 'undefined') {
                        grecaptcha.ready(() => {
                            this.isReady = true;
                            console.log('SiteManager Security: reCAPTCHA v3 ready');
                        });
                    }
                }
                
                /**
                 * reCAPTCHA v3 토큰 생성
                 */
                async getToken(action = 'form_submit') {
                    if (!this.isReady || !this.siteKey) {
                        console.warn('SiteManager Security: reCAPTCHA not ready');
                        return null;
                    }
                    
                    try {
                        return await grecaptcha.execute(this.siteKey, { action });
                    } catch (error) {
                        console.error('SiteManager Security: reCAPTCHA error', error);
                        return null;
                    }
                }
                
                /**
                 * 폼에 보안 기능 적용
                 */
                async secureForm(form, options = {}) {
                    const {
                        action = 'form_submit',
                        tokenField = 'recaptcha_token',
                        requireToken = true,
                        honeypot = true
                    } = options;
                    
                    // reCAPTCHA 토큰 생성 및 추가
                    if (requireToken) {
                        const token = await this.getToken(action);
                        if (token) {
                            this.addTokenToForm(form, tokenField, token);
                        }
                    }
                    
                    // Honeypot 검증
                    if (honeypot && !this.validateHoneypot(form)) {
                        console.warn('SiteManager Security: Honeypot validation failed');
                        return false;
                    }
                    
                    return true;
                }
                
                /**
                 * 폼에 토큰 추가
                 */
                addTokenToForm(form, fieldName, token) {
                    // 기존 토큰 필드 제거
                    const existing = form.querySelector(`input[name="${fieldName}"]`);
                    if (existing) existing.remove();
                    
                    // 새 토큰 필드 추가
                    const tokenField = document.createElement('input');
                    tokenField.type = 'hidden';
                    tokenField.name = fieldName;
                    tokenField.value = token;
                    form.appendChild(tokenField);
                    
                    console.log(`SiteManager Security: Token added (${fieldName})`);
                }
                
                /**
                 * Honeypot 검증
                 */
                validateHoneypot(form) {
                    const honeypotFields = ['website', 'url', 'homepage', 'phone_number', 'company_phone'];
                    
                    for (const fieldName of honeypotFields) {
                        const field = form.querySelector(`input[name="${fieldName}"]`);
                        if (field && field.value.trim() !== '') {
                            return false;
                        }
                    }
                    
                    return true;
                }
                
                /**
                 * 자동 폼 보안 설정
                 */
                autoSecureForms(selector = 'form[data-recaptcha="true"]', options = {}) {
                    const forms = document.querySelectorAll(selector);
                    
                    forms.forEach(form => {
                        form.addEventListener('submit', async (e) => {
                            e.preventDefault();
                            
                            const formOptions = {
                                action: form.dataset.recaptchaAction || options.action || 'form_submit',
                                tokenField: form.dataset.tokenField || options.tokenField || 'recaptcha_token',
                                ...options
                            };
                            
                            const isSecure = await this.secureForm(form, formOptions);
                            
                            if (isSecure) {
                                // 원래 제출 방식으로 진행
                                form.removeEventListener('submit', arguments.callee);
                                form.submit();
                            } else {
                                alert('보안 검증에 실패했습니다. 다시 시도해주세요.');
                            }
                        });
                    });
                }
            };
            
            // 전역 인스턴스 생성
            window.siteManagerSecurity = new window.SiteManagerSecurity();
            
            // DOM 로드 후 자동 보안 설정
            document.addEventListener('DOMContentLoaded', function() {
                window.siteManagerSecurity.autoSecureForms();
            });
        </script>
    @endonce
    
    {{-- 개별 폼 설정 --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 특정 폼에 보안 설정 적용
            const targetForms = document.querySelectorAll('{{ $formSelector }}');
            
            if (targetForms.length > 0 && window.siteManagerSecurity) {
                window.siteManagerSecurity.autoSecureForms('{{ $formSelector }}', {
                    action: '{{ $action }}',
                    tokenField: '{{ $tokenField }}'
                });
            }
        });
    </script>
@endif
