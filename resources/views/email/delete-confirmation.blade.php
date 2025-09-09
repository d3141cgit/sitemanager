@extends('sitemanager::layouts.app')

@section('title', '삭제 확인')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        삭제 확인
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning" role="alert">
                        <h5 class="alert-heading">
                            <i class="bi bi-shield-exclamation me-2"></i>
                            이메일 인증이 완료되었습니다
                        </h5>
                        <p>정말로 다음 내용을 삭제하시겠습니까?</p>
                        <hr>
                        <p class="mb-0">
                            <strong>
                                @if($tokenData['type'] === 'post')
                                    게시글을 삭제합니다
                                @else
                                    댓글을 삭제합니다
                                @endif
                            </strong>
                        </p>
                    </div>
                    
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <strong>주의:</strong> 삭제된 내용은 복구할 수 없습니다.
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <button type="button" class="btn btn-outline-secondary" onclick="cancelDelete()">
                            <i class="bi bi-x-lg me-1"></i>
                            취소
                        </button>
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                            <i class="bi bi-trash me-1"></i>
                            삭제하기
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const token = '{{ $tokenData['token'] ?? '' }}';

function cancelDelete() {
    if (window.opener) {
        window.close();
    } else {
        window.history.back();
    }
}

function confirmDelete() {
    if (!confirm('정말로 삭제하시겠습니까?')) {
        return;
    }
    
    // 삭제 요청
    fetch('{{ route("board.email.confirm-delete") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            token: token,
            confirm: true
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            if (data.redirect) {
                if (window.opener) {
                    window.opener.location.href = data.redirect;
                    window.close();
                } else {
                    window.location.href = data.redirect;
                }
            }
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('삭제 처리 중 오류가 발생했습니다.');
    });
}
</script>
@endsection
