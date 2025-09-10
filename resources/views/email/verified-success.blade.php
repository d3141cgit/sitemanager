@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', 'Email Verification Complete')

@push('head')
    {!! resource('sitemanager::css/email-verification.css') !!}
@endpush

@section('content')
<div class="email-verification-container">
    <div class="email-verification-card">
        <div class="icon text-success">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        
        <h1>Email Verified!</h1>
        
        <div class="alert alert-success">
            <strong>Verification completed successfully!</strong>
            <p class="mb-0">Your content has been published.</p>
        </div>
        
        <p>You can now edit or delete your post or comment by using the same email address for verification.</p>
        
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
}, 5000);
</script>
@endsection
