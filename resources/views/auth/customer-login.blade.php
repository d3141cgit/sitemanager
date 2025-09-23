@extends('sitemanager::layouts.app')

@section('title', '고객 로그인')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="text-center">고객 로그인</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('customer.login') }}">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="mm_id" class="form-label">아이디</label>
                            <input type="text" 
                                   class="form-control @error('mm_id') is-invalid @enderror" 
                                   id="mm_id" 
                                   name="mm_id" 
                                   value="{{ old('mm_id') }}" 
                                   required>
                            @error('mm_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">비밀번호</label>
                            <input type="password" 
                                   class="form-control @error('password') is-invalid @enderror" 
                                   id="password" 
                                   name="password" 
                                   required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">로그인 상태 유지</label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">로그인</button>
                        </div>
                    </form>
                    
                    @if(is_logged_in())
                        <div class="mt-3 alert alert-info">
                            <strong>현재 로그인 상태:</strong><br>
                            사용자: {{ current_user_name() }}<br>
                            가드: {{ current_guard() }}<br>
                            @if(current_user_email())
                                이메일: {{ current_user_email() }}
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection