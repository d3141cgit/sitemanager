@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', 'Setup Complete')

@push('head')
    {!! resource('sitemanager::css/email-verification.css') !!}
@endpush

@section('content')
<div class="email-verification-container">
    <div class="email-verification-card">
        <div class="icon text-success">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        
        <h1>Setup Complete!</h1>
        
        <div class="alert alert-success">
            <strong>Email verification and password setup completed successfully!</strong>
            <p>Your content has been published.</p>
        </div>
        
        <p class="lead">You can now manage your content using the following options:</p>
        
        <div class="mb-4">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div style="background: #f8f9fa; padding: 16px; border-radius: 8px;">
                        <h6><i class="bi bi-pencil-square text-primary me-2"></i>Edit Content</h6>
                        <p class="small mb-0">Click the edit button next to your post/comment and enter your email and password</p>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div style="background: #f8f9fa; padding: 16px; border-radius: 8px;">
                        <h6><i class="bi bi-trash text-danger me-2"></i>Delete Content</h6>
                        <p class="small mb-0">Click the delete button next to your post/comment and enter your email and password</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="alert alert-info">
            <h6><i class="bi bi-exclamation-triangle me-2"></i>Important Notes</h6>
            <ul class="mb-0 text-start">
                <li><strong>Keep your password safe</strong> - Recovery is not possible if lost</li>
                <li><strong>Remember your email address</strong> - Required for editing/deleting</li>
                <li>Use a password that others cannot easily guess</li>
            </ul>
        </div>
        
        <div>
            <a href="{{ url('/') }}" class="btn btn-dark">
                <i class="bi bi-house-door me-1"></i>
                Back to Home
            </a>
            {{-- <button type="button" class="btn btn-secondary" onclick="window.close()">
                <i class="bi bi-x-lg me-1"></i>
                Close Window
            </button> --}}
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
