@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', 'Password Setup')

@push('head')
    {!! resource('sitemanager::css/email-verification.css') !!}
@endpush

@section('content')
<div class="email-verification-container">
    <div class="email-verification-card">
        <div class="icon text-success">
            <i class="bi bi-shield-check-fill"></i>
        </div>
        
        <h1>Email Verified!</h1>
        <p class="lead">Please set up a password for editing/deleting your content</p>
        
        <form id="passwordSetupForm" action="{{ route('board.email.setup-password') }}" method="POST">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            
            <div class="alert alert-info">
                <strong>Password Setup</strong>
                <p class="mb-0">Set a password to edit or delete this {{ $tokenData['type'] === 'post' ? 'post' : 'comment' }} in the future.</p>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">
                    <i class="bi bi-key-fill me-1"></i>
                    Password <span class="text-danger">*</span>
                </label>
                <input type="password" 
                       class="form-control @error('password') is-invalid @enderror" 
                       id="password" 
                       name="password" 
                       placeholder="4-20 characters"
                       minlength="4"
                       maxlength="20"
                       required>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Enter 4-20 characters
                </small>
            </div>
            
            <div class="form-group">
                <label for="password_confirmation" class="form-label">
                    <i class="bi bi-key-fill me-1"></i>
                    Confirm Password <span class="text-danger">*</span>
                </label>
                <input type="password" 
                       class="form-control @error('password_confirmation') is-invalid @enderror" 
                       id="password_confirmation" 
                       name="password_confirmation" 
                       placeholder="Re-enter password"
                       minlength="4"
                       maxlength="20"
                       required>
                @error('password_confirmation')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="alert alert-info">
                <strong><i class="bi bi-exclamation-triangle me-2"></i>Important Notes</strong>
                <ul class="mb-0">
                    <li>This password cannot be recovered if lost</li>
                    <li>You won't be able to edit/delete without it</li>
                    <li>Please store it in a safe place</li>
                </ul>
            </div>
            
            <div>
                <button type="submit" class="btn btn-dark" id="setupBtn">
                    <i class="bi bi-check-circle me-1"></i>
                    Complete Password Setup
                </button>
                <button type="button" class="btn btn-secondary" onclick="skipPassword()">
                    <i class="bi bi-skip-end me-1"></i>
                    Set Up Later
                </button>
            </div>
        </form>
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
