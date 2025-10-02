/**
 * SiteManager 통합 보안 시스템
 * reCAPTCHA v3, Honeypot, Rate Limiting, 사용자 행동 추적 등 모든 보안 기능 통합
 * 
 * 사용법:
 * 1. 자동 모드: form에 data-recaptcha="true" 속성 추가
 * 2. 수동 모드: siteManagerSecurity.setupForm(form, options) 호출
 */

class SiteManagerSecurity {
    constructor() {
        this.siteKey = null;
        this.isRecaptchaReady = false;
        this.config = window.siteManagerSecurityConfig || {};
        this.behaviorData = {};
        
        this.init();
    }
    
    /**
     * 초기화
     */
    init() {
        this.siteKey = this.config.recaptcha?.siteKey;
        
        if (this.config.recaptcha?.enabled && this.siteKey) {
            this.initRecaptcha();
        }
        
        this.initBehaviorTracking();
        this.autoSetupForms();
    }
    
    /**
     * reCAPTCHA v3 초기화
     */
    initRecaptcha() {
        if (typeof grecaptcha !== 'undefined') {
            grecaptcha.ready(() => {
                this.isRecaptchaReady = true;
                console.log('SiteManager Security: reCAPTCHA v3 ready');
            });
        } else {
            // grecaptcha가 아직 로드되지 않은 경우 재시도
            setTimeout(() => this.initRecaptcha(), 100);
        }
    }
    
    /**
     * reCAPTCHA v3 토큰 생성
     */
    async getRecaptchaToken(action = 'form_submit') {
        if (!this.isRecaptchaReady || !this.siteKey) {
            console.warn('SiteManager Security: reCAPTCHA not ready');
            return null;
        }
        
        try {
            const token = await grecaptcha.execute(this.siteKey, { action });
            console.log('SiteManager Security: reCAPTCHA token generated');
            return token;
        } catch (error) {
            console.error('SiteManager Security: reCAPTCHA error', error);
            return null;
        }
    }
    
    /**
     * 사용자 행동 추적 초기화
     */
    initBehaviorTracking() {
        this.behaviorData = {
            mouseMovements: 0,
            keystrokes: 0,
            scrolls: 0,
            clicks: 0,
            focusEvents: 0,
            startTime: Date.now()
        };
        
        // 이벤트 리스너 등록
        document.addEventListener('mousemove', () => this.behaviorData.mouseMovements++);
        document.addEventListener('keydown', () => this.behaviorData.keystrokes++);
        document.addEventListener('scroll', () => this.behaviorData.scrolls++);
        document.addEventListener('click', () => this.behaviorData.clicks++);
        document.addEventListener('focus', () => this.behaviorData.focusEvents++, true);
    }
    
    /**
     * 사용자 행동 데이터 가져오기
     */
    getBehaviorData() {
        const now = Date.now();
        return {
            ...this.behaviorData,
            timeSpent: now - this.behaviorData.startTime,
            timestamp: now
        };
    }
    
    /**
     * Honeypot 검증
     */
    validateHoneypot(form) {
        const honeypotFields = this.config.honeypot?.fields || 
                               ['website', 'url', 'homepage', 'phone_number', 'company_phone'];
        
        for (const fieldName of honeypotFields) {
            const field = form.querySelector(`input[name="${fieldName}"]`);
            if (field && field.value.trim() !== '') {
                console.warn('SiteManager Security: Honeypot triggered', fieldName);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 폼 제출 시간 검증
     */
    validateSubmissionTime(form) {
        const timestampField = form.querySelector('input[name="form_timestamp"]');
        if (!timestampField) return true;
        
        const formTimestamp = parseInt(timestampField.value) * 1000;
        const currentTime = Date.now();
        const timeDiff = (currentTime - formTimestamp) / 1000;
        
        // 최소 3초, 최대 30분
        if (timeDiff < 3) {
            console.warn('SiteManager Security: Form submitted too quickly', timeDiff);
            return false;
        }
        
        if (timeDiff > 1800) {
            console.warn('SiteManager Security: Form expired', timeDiff);
            return false;
        }
        
        return true;
    }
    
    /**
     * 폼에 토큰 및 보안 데이터 추가
     */
    addSecurityData(form, options = {}) {
        const {
            tokenField = 'recaptcha_token',
            behaviorField = 'user_behavior'
        } = options;
        
        // reCAPTCHA 토큰 추가
        const tokenInput = form.querySelector(`input[name="${tokenField}"]`);
        if (tokenInput && options.token) {
            tokenInput.value = options.token;
        }
        
        // 사용자 행동 데이터 추가
        let behaviorInput = form.querySelector(`input[name="${behaviorField}"]`);
        if (!behaviorInput) {
            behaviorInput = document.createElement('input');
            behaviorInput.type = 'hidden';
            behaviorInput.name = behaviorField;
            form.appendChild(behaviorInput);
        }
        behaviorInput.value = JSON.stringify(this.getBehaviorData());
    }
    
    /**
     * 에러 표시
     */
    showError(form, message = '보안 검증에 실패했습니다. 다시 시도해주세요.') {
        const formId = form.id || 'default';
        const errorDiv = document.getElementById(`security-error-${formId}`);
        
        if (errorDiv) {
            errorDiv.querySelector('.alert').textContent = message;
            errorDiv.style.display = 'block';
        } else {
            alert(message);
        }
        
        // 3초 후 에러 숨기기
        setTimeout(() => {
            if (errorDiv) errorDiv.style.display = 'none';
        }, 3000);
    }
    
    /**
     * 개별 폼에 보안 기능 설정
     */
    setupForm(form, options = {}) {
        const {
            action = 'form_submit',
            tokenField = 'recaptcha_token',
            autoSubmit = true,
            validateHoneypot = true,
            validateTiming = true
        } = options;
        
        if (form.hasAttribute('data-security-setup')) {
            return; // 이미 설정됨
        }
        
        form.setAttribute('data-security-setup', 'true');
        
        form.addEventListener('submit', async (event) => {
            if (!autoSubmit) return; // 자동 제출 비활성화시 검증만 수행
            
            event.preventDefault();
            
            try {
                // 1. Honeypot 검증
                if (validateHoneypot && !this.validateHoneypot(form)) {
                    this.showError(form, '잘못된 접근입니다.');
                    return;
                }
                
                // 2. 제출 시간 검증
                if (validateTiming && !this.validateSubmissionTime(form)) {
                    this.showError(form, '폼 제출 시간이 유효하지 않습니다.');
                    return;
                }
                
                // 3. reCAPTCHA 토큰 생성
                let token = null;
                if (this.config.recaptcha?.enabled) {
                    token = await this.getRecaptchaToken(action);
                    if (!token) {
                        this.showError(form, 'reCAPTCHA 검증에 실패했습니다.');
                        return;
                    }
                }
                
                // 4. 보안 데이터 추가
                this.addSecurityData(form, { token, tokenField });
                
                // 5. 폼 제출
                console.log('SiteManager Security: Form validation passed');
                
                // 이벤트 리스너 제거 후 제출
                form.removeEventListener('submit', arguments.callee);
                form.submit();
                
            } catch (error) {
                console.error('SiteManager Security: Form validation error', error);
                this.showError(form);
            }
        });
    }
    
    /**
     * 자동으로 모든 보안 폼 설정
     */
    autoSetupForms() {
        document.addEventListener('DOMContentLoaded', () => {
            // data-recaptcha="true" 속성을 가진 모든 폼에 보안 설정 적용
            const forms = document.querySelectorAll('form[data-recaptcha="true"]');
            
            forms.forEach(form => {
                const action = form.dataset.recaptchaAction || 'form_submit';
                const tokenField = form.dataset.tokenField || 'recaptcha_token';
                
                this.setupForm(form, { action, tokenField });
            });
        });
    }
    
    /**
     * 수동으로 reCAPTCHA 토큰 생성 (외부 호출용)
     */
    async generateToken(action = 'form_submit') {
        return await this.getRecaptchaToken(action);
    }
    
    /**
     * 폼 검증만 수행 (제출하지 않음)
     */
    async validateForm(form, options = {}) {
        const { action = 'form_submit' } = options;
        
        // Honeypot 검증
        if (!this.validateHoneypot(form)) {
            return { valid: false, error: 'honeypot' };
        }
        
        // 제출 시간 검증
        if (!this.validateSubmissionTime(form)) {
            return { valid: false, error: 'timing' };
        }
        
        // reCAPTCHA 토큰 생성
        let token = null;
        if (this.config.recaptcha?.enabled) {
            token = await this.getRecaptchaToken(action);
            if (!token) {
                return { valid: false, error: 'recaptcha' };
            }
        }
        
        return { valid: true, token, behaviorData: this.getBehaviorData() };
    }
}

// 전역 인스턴스 생성
window.SiteManagerSecurity = SiteManagerSecurity;
window.siteManagerSecurity = new SiteManagerSecurity();

// 편의 함수들
window.getSiteManagerSecurityToken = (action) => window.siteManagerSecurity.generateToken(action);
window.validateSiteManagerForm = (form, options) => window.siteManagerSecurity.validateForm(form, options);