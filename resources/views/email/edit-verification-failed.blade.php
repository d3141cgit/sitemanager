@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', 'Edit Verification Failed')

@push('head')
    {!! resource('sitemanager::css/email-verification.css') !!}
@endpush

@section('content')
<div class="email-verification-container">
    <div class="email-verification-card">
        <div class="icon text-danger">
            <i class="bi bi-x-circle-fill"></i>
        </div>
        
        <h1>Authentication Failed</h1>
        
        <div class="alert alert-danger">
            <strong>Edit/Delete authentication failed</strong>
            <p class="mb-0">The verification link has expired or is invalid.</p>
        </div>
        
        <div>
            <p><strong>Possible causes:</strong></p>
            <ul class="text-start">
                <li>The verification link has expired (1 hour)</li>
                <li>The verification link has already been used</li>
                <li>Invalid verification link</li>
            </ul>
        </div>
        
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            To edit/delete again, please request a new verification email.
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
@endsection
