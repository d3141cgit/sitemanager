@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', '비밀번호 설정')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-check-fill text-success" style="font-size: 3rem;"></i>
                        <h2 class="card-title text-success mt-3">✅ 이메일 인증 완료</h2>
                        <p class="text-muted">수정/삭제용 비밀번호를 설정해주세요</p>
                    </div>
                    
                    <form id="passwordSetupForm" action="{{ route('board.email.setup-password') }}" method="POST">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        
                        <div class="alert alert-info" role="alert">
                            <h6 class="alert-heading">
                                <i class="bi bi-info-circle me-2"></i>
                                비밀번호 설정
                            </h6>
                            <p class="mb-0">향후 이 {{ $tokenData['type'] === 'post' ? '게시글' : '댓글' }}을 수정하거나 삭제할 때 사용할 비밀번호를 설정해주세요.</p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="bi bi-key-fill me-1"></i>
                                비밀번호 <span class="text-danger">*</span>
                            </label>
                            <input type="password" 
                                   class="form-control @error('password') is-invalid @enderror" 
                                   id="password" 
                                   name="password" 
                                   placeholder="4-20자 비밀번호"
                                   minlength="4"
                                   maxlength="20"
                                   required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                4자 이상 20자 이하로 입력해주세요
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">
                                <i class="bi bi-key-fill me-1"></i>
                                비밀번호 확인 <span class="text-danger">*</span>
                            </label>
                            <input type="password" 
                                   class="form-control @error('password_confirmation') is-invalid @enderror" 
                                   id="password_confirmation" 
                                   name="password_confirmation" 
                                   placeholder="비밀번호 재입력"
                                   minlength="4"
                                   maxlength="20"
                                   required>
                            @error('password_confirmation')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="alert alert-warning" role="alert">
                            <h6 class="alert-heading">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                주의사항
                            </h6>
                            <ul class="mb-0 small">
                                <li>설정한 비밀번호는 분실 시 복구가 불가능합니다</li>
                                <li>비밀번호를 잊어버리시면 수정/삭제가 불가능합니다</li>
                                <li>비밀번호는 안전한 곳에 보관해주세요</li>
                            </ul>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary" id="setupBtn">
                                <i class="bi bi-check-circle me-1"></i>
                                비밀번호 설정 완료
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="skipPassword()">
                                <i class="bi bi-skip-end me-1"></i>
                                나중에 설정하기
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('passwordSetupForm');
    const password = document.getElementById('password');
    const passwordConfirmation = document.getElementById('password_confirmation');
    const setupBtn = document.getElementById('setupBtn');
    
    // 비밀번호 확인 검증
    function validatePasswordMatch() {
        if (password.value && passwordConfirmation.value) {
            if (password.value !== passwordConfirmation.value) {
                passwordConfirmation.setCustomValidity('비밀번호가 일치하지 않습니다.');
                passwordConfirmation.classList.add('is-invalid');
            } else {
                passwordConfirmation.setCustomValidity('');
                passwordConfirmation.classList.remove('is-invalid');
            }
        }
    }
    
    password.addEventListener('input', validatePasswordMatch);
    passwordConfirmation.addEventListener('input', validatePasswordMatch);
    
    // 폼 제출 처리
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        
        setupBtn.disabled = true;
        setupBtn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>설정 중...';
        
        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                token: '{{ $token }}',
                password: password.value,
                password_confirmation: passwordConfirmation.value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 서버에서 제공한 리다이렉트 URL 사용 (상대 경로 fallback)
                const redirectUrl = data.redirect_url || '/board/email/setup-complete';
                window.location.href = redirectUrl;
            } else {
                throw new Error(data.message || '비밀번호 설정에 실패했습니다.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(error.message || '비밀번호 설정 중 오류가 발생했습니다.');
            
            setupBtn.disabled = false;
            setupBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>비밀번호 설정 완료';
        });
    });
});

function skipPassword() {
    if (confirm('비밀번호를 설정하지 않으면 나중에 수정/삭제가 어려울 수 있습니다.\n정말 건너뛰시겠습니까?')) {
        // 상대 경로로 이동하여 도메인 리다이렉트 문제 방지
        window.location.href = '/board/email/setup-complete';
    }
}
</script>
@endsection
