@extends('sitemanager::layouts.app')

@section('title', '이메일 인증 완료')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                    </div>
                    
                    <h2 class="card-title text-success mb-3">✅ 이메일 인증 완료</h2>
                    
                    <div class="alert alert-success" role="alert">
                        <h5>인증이 성공적으로 완료되었습니다!</h5>
                        <p class="mb-0">작성하신 내용이 정상적으로 게시됩니다.</p>
                    </div>
                    
                    <div class="mb-4">
                        <p class="text-muted">
                            이제 해당 게시글이나 댓글을 수정하거나 삭제할 때는<br>
                            동일한 이메일 주소로 인증을 받으실 수 있습니다.
                        </p>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <button type="button" class="btn btn-primary" onclick="window.close()">
                            <i class="bi bi-x-lg me-1"></i>
                            창 닫기
                        </button>
                        <a href="{{ url('/') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-house-door me-1"></i>
                            메인으로
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 5초 후 자동으로 창 닫기 (팝업인 경우)
setTimeout(function() {
    if (window.opener) {
        window.close();
    }
}, 5000);
</script>
@endsection
