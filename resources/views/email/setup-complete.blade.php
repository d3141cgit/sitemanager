@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', '설정 완료')

@section('content')





<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                    </div>
                    
                    <h2 class="card-title text-success mb-3">🎉 설정 완료!</h2>
                    
                    <div class="alert alert-success" role="alert">
                        <h5>이메일 인증 및 비밀번호 설정이 완료되었습니다!</h5>
                        <p class="mb-0">작성하신 내용이 정상적으로 게시되었습니다.</p>
                    </div>
                    
                    <div class="mb-4">
                        <h6 class="text-muted mb-3">이제 다음과 같이 이용하실 수 있습니다:</h6>
                        <div class="row text-start">
                            <div class="col-md-6">
                                <div class="card border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-pencil-square text-primary me-2"></i>
                                            수정하기
                                        </h6>
                                        <p class="card-text small">
                                            게시글/댓글 옆의 수정 버튼을 클릭하고<br>
                                            이메일과 비밀번호를 입력하세요
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-trash text-danger me-2"></i>
                                            삭제하기
                                        </h6>
                                        <p class="card-text small">
                                            게시글/댓글 옆의 삭제 버튼을 클릭하고<br>
                                            이메일과 비밀번호를 입력하세요
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning" role="alert">
                        <h6 class="alert-heading">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            중요 안내
                        </h6>
                        <ul class="mb-0 text-start small">
                            <li><strong>비밀번호는 안전하게 보관해주세요</strong> - 분실 시 복구가 불가능합니다</li>
                            <li><strong>이메일 주소를 정확히 기억해주세요</strong> - 수정/삭제 시 필요합니다</li>
                            <li>타인이 추측하기 어려운 비밀번호를 사용하세요</li>
                        </ul>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <a href="{{ url('/') }}" class="btn btn-primary">
                            <i class="bi bi-house-door me-1"></i>
                            메인으로 돌아가기
                        </a>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.close()">
                            <i class="bi bi-x-lg me-1"></i>
                            창 닫기
                        </button>
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
}, 10000); // 10초 후 자동 닫기
</script>
@endsection
