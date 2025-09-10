@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', '이메일 인증 실패')

@section('content')
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="bi bi-x-circle-fill text-danger" style="font-size: 4rem;"></i>
                    </div>
                    
                    <h2 class="card-title text-danger mb-3">❌ 이메일 인증 실패</h2>
                    
                    <div class="alert alert-danger" role="alert">
                        <h5>인증에 실패했습니다</h5>
                        <p class="mb-0">인증 링크가 만료되었거나 잘못된 링크입니다.</p>
                    </div>
                    
                    <div class="mb-4 text-start">
                        <p class="text-muted">
                            가능한 원인:
                        </p>
                        <ul class="list-unstyled text-start">
                            <li>• 인증 링크가 24시간을 초과했습니다</li>
                            <li>• 이미 사용된 인증 링크입니다</li>
                            <li>• 잘못된 인증 링크입니다</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-info" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        새로운 인증 이메일을 요청하시거나, 관리자에게 문의해주세요.
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        {{-- <button type="button" class="btn btn-secondary" onclick="window.close()">
                            <i class="bi bi-x-lg me-1"></i>
                            창 닫기
                        </button> --}}
                        <a href="{{ url('/') }}" class="btn btn-primary">
                            <i class="bi bi-house-door me-1"></i>
                            메인으로
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
