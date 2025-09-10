{{-- 수정/삭제 이메일 인증 모달 --}}
<div class="modal fade" id="emailVerificationModal" tabindex="-1" aria-labelledby="emailVerificationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emailVerificationModalLabel">
                    <i class="bi bi-shield-check me-2"></i>
                    본인 인증
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="emailVerificationForm">
                    <div class="alert alert-info" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        <span id="verification-action-text">수정</span>을 위해 작성 시 사용한 이메일로 인증을 받아주세요.
                    </div>
                    
                    <div class="mb-3">
                        <label for="verification_email" class="form-label">
                            <i class="bi bi-envelope me-1"></i>
                            이메일 주소 <span class="text-danger">*</span>
                        </label>
                        <input type="email" 
                               class="form-control" 
                               id="verification_email" 
                               name="email" 
                               placeholder="작성 시 사용한 이메일을 입력하세요"
                               required>
                        <div class="invalid-feedback">유효한 이메일을 입력해주세요.</div>
                    </div>
                    
                    {{-- reCAPTCHA v2 --}}
                    @if(config('sitemanager.security.recaptcha.enabled', false) && config('sitemanager.security.recaptcha.site_key'))
                    <div class="mb-3">
                        <div class="g-recaptcha" 
                             data-sitekey="{{ config('sitemanager.security.recaptcha.site_key') ?? config('services.recaptcha.site_key') }}"
                             id="modal-recaptcha"></div>
                        <div class="invalid-feedback d-block" id="modal-captcha-error" style="display: none !important;">
                            캡챠 인증을 완료해주세요.
                        </div>
                    </div>
                    @endif
                    
                    <div class="alert alert-warning" role="alert">
                        <h6 class="alert-heading">
                            <i class="bi bi-clock me-2"></i>
                            인증 방법
                        </h6>
                        <ul class="mb-0 small">
                            <li>입력한 이메일로 인증 링크가 발송됩니다</li>
                            <li>이메일의 인증 링크를 클릭하면 <span id="verification-action-text2">수정</span> 페이지로 이동합니다</li>
                            <li>인증 링크는 1시간 동안 유효하며 일회용입니다</li>
                        </ul>
                    </div>
                    
                    <input type="hidden" id="verification_type" name="type" value="">
                    <input type="hidden" id="verification_id" name="id" value="">
                    <input type="hidden" id="verification_board_slug" name="board_slug" value="">
                    <input type="hidden" id="verification_action" name="action" value="">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>
                    취소
                </button>
                <button type="button" class="btn btn-dark" id="sendVerificationBtn">
                    <i class="bi bi-envelope me-1"></i>
                    인증 이메일 발송
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let emailVerificationModal;

document.addEventListener('DOMContentLoaded', function() {
    emailVerificationModal = new bootstrap.Modal(document.getElementById('emailVerificationModal'));
    
    // 인증 이메일 발송 버튼 클릭 이벤트
    document.getElementById('sendVerificationBtn').addEventListener('click', function() {
        sendVerificationEmail();
    });
});

/**
 * 이메일 인증 모달 표시
 * @param {string} type - 'post' 또는 'comment'
 * @param {number} id - 게시글 또는 댓글 ID
 * @param {string} boardSlug - 게시판 슬러그
 * @param {string} action - 'edit' 또는 'delete'
 */
function showEmailVerificationModal(type, id, boardSlug, action = 'edit') {
    // 폼 데이터 설정
    document.getElementById('verification_type').value = type;
    document.getElementById('verification_id').value = id;
    document.getElementById('verification_board_slug').value = boardSlug;
    document.getElementById('verification_action').value = action;
    
    // 텍스트 업데이트
    const actionText = action === 'delete' ? '삭제' : '수정';
    document.getElementById('verification-action-text').textContent = actionText;
    document.getElementById('verification-action-text2').textContent = actionText;
    
    // 모달 제목 업데이트
    const modalTitle = document.getElementById('emailVerificationModalLabel');
    modalTitle.innerHTML = `<i class="bi bi-shield-check me-2"></i>${actionText} 인증`;
    
    // 버튼 색상 변경
    const sendBtn = document.getElementById('sendVerificationBtn');
    if (action === 'delete') {
        sendBtn.className = 'btn btn-danger';
        sendBtn.innerHTML = '<i class="bi bi-envelope me-1"></i>삭제 인증 이메일 발송';
    } else {
        sendBtn.className = 'btn btn-dark';
        sendBtn.innerHTML = '<i class="bi bi-envelope me-1"></i>수정 인증 이메일 발송';
    }
    
    // 폼 초기화
    document.getElementById('emailVerificationForm').reset();
    document.getElementById('verification_email').classList.remove('is-invalid');
    
    // reCAPTCHA 리셋
    @if(config('sitemanager.security.recaptcha.enabled', false) && config('sitemanager.security.recaptcha.site_key'))
    if (typeof grecaptcha !== 'undefined') {
        grecaptcha.reset();
    }
    @endif
    
    // 모달 표시
    emailVerificationModal.show();
}

/**
 * 인증 이메일 발송
 */
function sendVerificationEmail() {
    const form = document.getElementById('emailVerificationForm');
    const formData = new FormData(form);
    const emailInput = document.getElementById('verification_email');
    
    // 이메일 검증
    if (!emailInput.value.trim() || !isValidEmail(emailInput.value)) {
        emailInput.classList.add('is-invalid');
        return;
    }
    
    // reCAPTCHA 검증
    @if(config('sitemanager.security.recaptcha.enabled', false) && config('sitemanager.security.recaptcha.site_key'))
    const recaptchaResponse = grecaptcha.getResponse();
    if (!recaptchaResponse) {
        document.getElementById('modal-captcha-error').style.display = 'block';
        return;
    }
    formData.append('g-recaptcha-response', recaptchaResponse);
    @endif
    
    const sendBtn = document.getElementById('sendVerificationBtn');
    const originalText = sendBtn.innerHTML;
    
    // 버튼 비활성화
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>발송 중...';
    
    // AJAX 요청
    fetch('{{ route("board.email.send-edit-verification") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 성공 메시지 표시
            showAlert('success', data.message);
            emailVerificationModal.hide();
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', '이메일 발송 중 오류가 발생했습니다.');
    })
    .finally(() => {
        // 버튼 복원
        sendBtn.disabled = false;
        sendBtn.innerHTML = originalText;
    });
}

/**
 * 이메일 유효성 검사
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * 알림 메시지 표시
 */
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.style.minWidth = '300px';
    
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // 5초 후 자동 제거
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 5000);
}

// 전역 함수로 내보내기 (다른 스크립트에서 사용할 수 있도록)
window.showEmailVerificationModal = showEmailVerificationModal;
</script>

<style>
#emailVerificationModal .modal-content {
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

#emailVerificationModal .modal-header {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    border-bottom: none;
}

#emailVerificationModal .btn-close {
    filter: invert(1);
}

@media (max-width: 576px) {
    #emailVerificationModal .modal-dialog {
        margin: 10px;
    }
}
</style>
