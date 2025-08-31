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
        else feedback.push('At least 8 characters');

        // Uppercase check
        if (/[A-Z]/.test(password)) score += 25;
        else feedback.push('Include uppercase letter');

        // Lowercase check  
        if (/[a-z]/.test(password)) score += 25;
        else feedback.push('Include lowercase letter');

        // Number or special char check
        if (/[\d\W]/.test(password)) score += 25;
        else feedback.push('Include number or special character');

        let strength = {
            percentage: score,
            class: 'bg-danger',
            text: 'Weak'
        };

        if (score >= 75) {
            strength.class = 'bg-success';
            strength.text = 'Strong';
        } else if (score >= 50) {
            strength.class = 'bg-warning';
            strength.text = 'Medium';
        } else if (score >= 25) {
            strength.class = 'bg-info';
            strength.text = 'Slightly weak';
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
            alert('Password must be at least 4 characters.');
            passwordInput.focus();
            return false;
        }
        
        // Confirm password if this is a new post
        const isNewPost = !document.querySelector('input[name="_method"][value="PUT"]');
        if (isNewPost) {
            const confirmPassword = prompt('Please re-enter your password:');
            if (confirmPassword !== password) {
                alert('Passwords do not match.');
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
