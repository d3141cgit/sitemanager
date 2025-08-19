/**
 * 일반 사용자 페이지 JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('User page initialized');
    
    // 사용자 기능 초기화
    initUserFeatures();
    initProfileFeatures();
    initAnimations();
});

/**
 * 사용자 기능 초기화
 */
function initUserFeatures() {
    // 프로필 사진 미리보기
    const profileImageInput = document.querySelector('#profileImage');
    if (profileImageInput) {
        profileImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.querySelector('.profile-avatar');
                    if (preview) {
                        preview.src = e.target.result;
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // 비밀번호 확인
    const passwordField = document.querySelector('#password');
    const confirmPasswordField = document.querySelector('#password_confirmation');
    
    if (passwordField && confirmPasswordField) {
        confirmPasswordField.addEventListener('input', function() {
            if (this.value !== passwordField.value) {
                this.setCustomValidity('비밀번호가 일치하지 않습니다.');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
            }
        });
    }
    
    // 폼 제출 시 로딩 상태
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.classList.contains('user-form')) {
            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>처리 중...';
            }
        }
    });
}

/**
 * 프로필 관련 기능
 */
function initProfileFeatures() {
    // 프로필 편집 토글
    const editToggleBtn = document.querySelector('#editToggle');
    const profileForm = document.querySelector('#profileForm');
    const profileDisplay = document.querySelector('#profileDisplay');
    
    if (editToggleBtn && profileForm && profileDisplay) {
        editToggleBtn.addEventListener('click', function() {
            const isEditing = profileForm.style.display !== 'none';
            
            if (isEditing) {
                // 편집 모드 종료
                profileForm.style.display = 'none';
                profileDisplay.style.display = 'block';
                this.innerHTML = '<i class="fas fa-edit me-1"></i>프로필 수정';
            } else {
                // 편집 모드 시작
                profileForm.style.display = 'block';
                profileDisplay.style.display = 'none';
                this.innerHTML = '<i class="fas fa-times me-1"></i>수정 취소';
            }
        });
    }
    
    // 계정 삭제 확인
    const deleteAccountBtn = document.querySelector('#deleteAccountBtn');
    if (deleteAccountBtn) {
        deleteAccountBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showDeleteAccountModal();
        });
    }
}

/**
 * 애니메이션 초기화
 */
function initAnimations() {
    // 스크롤 애니메이션
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
            }
        });
    }, observerOptions);
    
    // 애니메이션 대상 요소들
    document.querySelectorAll('.user-card, .stats-widget, .info-card').forEach(el => {
        observer.observe(el);
    });
    
    // 카운터 애니메이션
    animateCounters();
}

/**
 * 숫자 카운터 애니메이션
 */
function animateCounters() {
    const counters = document.querySelectorAll('.stats-number');
    
    counters.forEach(counter => {
        const target = parseInt(counter.textContent);
        const increment = target / 50; // 50프레임으로 나누어 애니메이션
        let current = 0;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                counter.textContent = target;
                clearInterval(timer);
            } else {
                counter.textContent = Math.floor(current);
            }
        }, 20);
    });
}

/**
 * 계정 삭제 모달 표시
 */
function showDeleteAccountModal() {
    const modalHtml = `
        <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            계정 삭제 확인
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <i class="fas fa-user-times text-danger" style="font-size: 3rem;"></i>
                        </div>
                        <p class="text-center mb-3">
                            <strong>정말로 계정을 삭제하시겠습니까?</strong>
                        </p>
                        <div class="alert alert-warning">
                            <ul class="mb-0">
                                <li>모든 개인정보가 영구적으로 삭제됩니다</li>
                                <li>그룹 멤버십이 해제됩니다</li>
                                <li>이 작업은 되돌릴 수 없습니다</li>
                            </ul>
                        </div>
                        <form id="deleteAccountForm" action="${document.querySelector('#deleteAccountBtn').href}" method="POST">
                            <input type="hidden" name="_method" value="DELETE">
                            <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').getAttribute('content')}">
                            
                            <div class="mb-3">
                                <label for="deletePassword" class="form-label">비밀번호 확인</label>
                                <input type="password" class="form-control" id="deletePassword" name="password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="deleteConfirmation" class="form-label">
                                    확인을 위해 "회원탈퇴"를 입력해주세요
                                </label>
                                <input type="text" class="form-control" id="deleteConfirmation" name="confirmation" required>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                        <button type="button" class="btn btn-danger" onclick="confirmDeleteAccount()">
                            <i class="fas fa-trash me-1"></i>계정 삭제
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // 기존 모달 제거
    const existingModal = document.querySelector('#deleteAccountModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // 새 모달 추가
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // 모달 표시
    const modal = new bootstrap.Modal(document.querySelector('#deleteAccountModal'));
    modal.show();
}

/**
 * 계정 삭제 최종 확인
 */
function confirmDeleteAccount() {
    const form = document.querySelector('#deleteAccountForm');
    const password = document.querySelector('#deletePassword').value;
    const confirmation = document.querySelector('#deleteConfirmation').value;
    
    if (!password) {
        alert('비밀번호를 입력해주세요.');
        return;
    }
    
    if (confirmation !== '회원탈퇴') {
        alert('정확히 "회원탈퇴"를 입력해주세요.');
        return;
    }
    
    if (confirm('정말로 계정을 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) {
        form.submit();
    }
}

/**
 * 알림 표시
 */
function showUserNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-danger' : 
                      type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px; border-radius: 1rem;" role="alert">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // 5초 후 자동 제거
    setTimeout(() => {
        const alert = document.querySelector('.alert.position-fixed:last-of-type');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

/**
 * 프로필 이미지 업로드
 */
function uploadProfileImage(file) {
    const formData = new FormData();
    formData.append('profile_image', file);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
    
    fetch('/user/profile/image', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showUserNotification('프로필 이미지가 업데이트되었습니다.', 'success');
        } else {
            showUserNotification('이미지 업로드에 실패했습니다.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showUserNotification('오류가 발생했습니다.', 'error');
    });
}

/**
 * 폼 유효성 검사
 */
function validateUserForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    // 이메일 형식 검사
    const emailFields = form.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (field.value && !emailRegex.test(field.value)) {
            field.classList.add('is-invalid');
            isValid = false;
        }
    });
    
    // 전화번호 형식 검사
    const phoneFields = form.querySelectorAll('input[name="phone"]');
    phoneFields.forEach(field => {
        const phoneRegex = /^[\d\-\s\+\(\)]+$/;
        if (field.value && !phoneRegex.test(field.value)) {
            field.classList.add('is-invalid');
            isValid = false;
        }
    });
    
    return isValid;
}

// 전역 함수로 노출
window.UserPage = {
    showUserNotification,
    uploadProfileImage,
    validateUserForm,
    confirmDeleteAccount
};
