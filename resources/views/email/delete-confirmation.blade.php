@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', 'Delete Confirmation')

@push('head')
    {!! resource('sitemanager::css/email-verification.css') !!}
@endpush

@section('content')
<div class="email-verification-container">
    <div class="email-verification-card">
        <div class="icon text-danger">
            <i class="bi bi-exclamation-triangle"></i>
        </div>
        
        <h1>Delete Confirmation</h1>
        
        <div class="alert alert-success">
            <strong><i class="bi bi-shield-check me-2"></i>Email verification completed</strong>
            <p class="mb-0">Are you sure you want to delete the following content?</p>
        </div>
        
        <div class="alert alert-info">
            <strong>
                @if($tokenData['type'] === 'post')
                    You are about to delete a post
                @else
                    You are about to delete a comment
                @endif
            </strong>
        </div>
        
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>
            <strong>Warning:</strong> Deleted content cannot be recovered.
        </div>
        
        <div>
            <button type="button" class="btn btn-secondary" onclick="cancelDelete()">
                <i class="bi bi-x-lg me-1"></i>
                Cancel
            </button>
            <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                <i class="bi bi-trash me-1"></i>
                Delete
            </button>
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
