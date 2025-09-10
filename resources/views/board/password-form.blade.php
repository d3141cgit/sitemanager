@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', 'Private - ' . $post->title)

@push('head')
    <meta name="robots" content="noindex,nofollow">
    {!! resource('sitemanager::js/board/password-form.js') !!}
    {!! resource('sitemanager::css/board.default.css') !!}
@endpush

@section('content')
<main class="board">
    <div class="private-form-container">
        <div class="post-info">
            <h3 class="text-center"><i class="bi bi-lock-fill text-warning"></i> {{ $post->title }}</h3>
            <p class="text-center text-muted">
                {{ $post->author_name ?: $post->member?->name ?: '익명' }}
                <span class="mx-2">•</span>
                <time datetime="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->format('Y-m-d H:i') }}</time>
            </p>
        </div>

        <form id="passwordForm" action="{{ route('board.verify-password', [$board->slug, $post->slug ?: $post->id]) }}" method="POST">
            @csrf

            <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required autofocus>

            @error('password')
                <p class="mt-2 text-sm text-red-600 flex items-center">
                    <i class="bi bi-exclamation-triangle-fill mr-2"></i>
                    {{ $message }}
                </p>
            @enderror

            <div class="d-flex align-items-center justify-content-center gap-2">
                <a href="{{ route('board.index', $board->slug) }}" class="btn btn-outline-dark">
                    <i class="bi bi-arrow-left mr-2"></i> Back to List
                </a>

                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-check-lg mr-2"></i> Confirm
                </button>
            </div>
        </form>
    </div>
</main>
@endsection
