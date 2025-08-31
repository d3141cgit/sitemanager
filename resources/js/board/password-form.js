/**
 * Board Password Form JavaScript
 * Handles secret post password verification form
 */

document.addEventListener('DOMContentLoaded', function() {
    initializePasswordForm();
});

/**
 * Initialize password form functionality
 */
function initializePasswordForm() {
    const passwordInput = document.getElementById('password');
    const passwordForm = document.getElementById('passwordForm');

    // Focus on password input
    if (passwordInput) {
        passwordInput.focus();
        
        // Handle Enter key press
        passwordInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (passwordForm) {
                    passwordForm.submit();
                }
            }
        });
        
        // Clear any previous error styling on input
        passwordInput.addEventListener('input', function() {
            this.classList.remove('border-red-500');
            const errorMsg = this.parentNode.querySelector('.text-red-600');
            if (errorMsg && !errorMsg.textContent.includes('{{ $message }}')) {
                errorMsg.style.display = 'none';
            }
        });
    }

    // Add focus and shake animation for errors
    if (passwordInput && document.querySelector('.text-red-600')) {
        passwordInput.classList.add('border-red-500');
        shakeElement(passwordInput.parentNode);
        passwordInput.focus();
        passwordInput.select();
    }
}

/**
 * Shake animation for error feedback
 */
function shakeElement(element) {
    element.style.animation = 'shake 0.5s';
    setTimeout(() => {
        element.style.animation = '';
    }, 500);
}

/**
 * Add CSS animations
 */
function addPasswordFormStyles() {
    if (document.getElementById('password-form-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'password-form-styles';
    style.textContent = `
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .password-form-container {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .password-input:focus {
            outline: none;
            ring: 2px;
            ring-color: rgb(59 130 246);
            border-color: transparent;
        }
        
        .password-hint {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }
        
        .border-red-500 {
            border-color: #ef4444 !important;
        }
    `;
    document.head.appendChild(style);
}

// Initialize styles
addPasswordFormStyles();
