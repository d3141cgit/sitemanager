/**
 * Board Secret Post JavaScript
 * Handles secret post functionality including password forms and toggles
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeSecretPostForm();
});

/**
 * Initialize secret post form interactions
 */
function initializeSecretPostForm() {
    const removeSecretCheckbox = document.getElementById('remove_secret_password');
    const passwordChangeSection = document.getElementById('password-change-section');
    const secretPasswordInput = document.getElementById('secret_password');

    if (removeSecretCheckbox && passwordChangeSection) {
        removeSecretCheckbox.addEventListener('change', function() {
            if (this.checked) {
                passwordChangeSection.style.display = 'none';
                if (secretPasswordInput) {
                    secretPasswordInput.value = '';
                    secretPasswordInput.required = false;
                }
            } else {
                passwordChangeSection.style.display = 'block';
                if (secretPasswordInput) {
                    secretPasswordInput.required = false; // Only required if user wants to change
                }
            }
        });
    }

    // Password strength indicator (optional)
    if (secretPasswordInput) {
        secretPasswordInput.addEventListener('input', function() {
            showPasswordStrength(this);
        });
    }
}

/**
 * Show password strength indicator
 */
function showPasswordStrength(input) {
    const password = input.value;
    const strengthContainer = document.getElementById('password-strength') || createPasswordStrengthIndicator(input);
    
    if (!password) {
        strengthContainer.style.display = 'none';
        return;
    }

    const strength = calculatePasswordStrength(password);
    strengthContainer.style.display = 'block';
    
    const bar = strengthContainer.querySelector('.strength-bar');
    const text = strengthContainer.querySelector('.strength-text');
    
    if (bar && text) {
        bar.className = `strength-bar ${strength.class}`;
        bar.style.width = strength.percentage + '%';
        text.textContent = strength.text;
    }
}

/**
 * Create password strength indicator
 */
function createPasswordStrengthIndicator(input) {
    const container = document.createElement('div');
    container.id = 'password-strength';
    container.className = 'password-strength-container mt-2';
    container.innerHTML = `
        <div class="progress" style="height: 6px;">
            <div class="strength-bar progress-bar" style="width: 0%"></div>
        </div>
        <small class="strength-text text-muted"></small>
    `;
    
    input.parentNode.appendChild(container);
    return container;
}

/**
 * Calculate password strength
 */
function calculatePasswordStrength(password) {
    let score = 0;
    let feedback = [];

    // Length check
    if (password.length >= 8) score += 25;
    else feedback.push('최소 8자 이상');

    // Uppercase check
    if (/[A-Z]/.test(password)) score += 25;
    else feedback.push('대문자 포함');

    // Lowercase check  
    if (/[a-z]/.test(password)) score += 25;
    else feedback.push('소문자 포함');

    // Number or special char check
    if (/[\d\W]/.test(password)) score += 25;
    else feedback.push('숫자 또는 특수문자 포함');

    let strength = {
        percentage: score,
        class: 'bg-danger',
        text: '약함'
    };

    if (score >= 75) {
        strength.class = 'bg-success';
        strength.text = '강함';
    } else if (score >= 50) {
        strength.class = 'bg-warning';
        strength.text = '보통';
    } else if (score >= 25) {
        strength.class = 'bg-info';
        strength.text = '약간 약함';
    }

    if (feedback.length > 0 && score < 100) {
        strength.text += ` (${feedback.join(', ')})`;
    }

    return strength;
}

/**
 * Validate secret password form before submit
 */
function validateSecretPasswordForm(form) {
    const removeCheckbox = form.querySelector('#remove_secret_password');
    const passwordInput = form.querySelector('#secret_password');
    
    // If removing secret, no validation needed
    if (removeCheckbox && removeCheckbox.checked) {
        return true;
    }
    
    // If setting new password, basic validation
    if (passwordInput && passwordInput.value) {
        const password = passwordInput.value;
        
        if (password.length < 4) {
            alert('비밀번호는 최소 4자 이상이어야 합니다.');
            passwordInput.focus();
            return false;
        }
        
        // Confirm password if this is a new post
        const isNewPost = !document.querySelector('input[name="_method"][value="PUT"]');
        if (isNewPost) {
            const confirmPassword = prompt('비밀번호를 다시 입력해주세요:');
            if (confirmPassword !== password) {
                alert('비밀번호가 일치하지 않습니다.');
                passwordInput.focus();
                return false;
            }
        }
    }
    
    return true;
}

// Add form validation to post forms
document.addEventListener('DOMContentLoaded', function() {
    const postForm = document.querySelector('form[data-board-slug]');
    if (postForm) {
        postForm.addEventListener('submit', function(e) {
            if (!validateSecretPasswordForm(this)) {
                e.preventDefault();
                return false;
            }
        });
    }
});

// Global functions
window.validateSecretPasswordForm = validateSecretPasswordForm;
