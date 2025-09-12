@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', $board->name . ' - 게시글이 없습니다')

@push('head')
    <meta name="robots" content="noindex,nofollow">
    
    {{-- 로그인 상태 정보 전달 --}}
    <meta name="auth-user" content="{{ auth()->check() ? 'true' : 'false' }}">

    {!! resource('sitemanager::css/board.default.css') !!}
@endpush

@section('content')
<main class="board show-empty">
    <div class="content-container">
        <div class="text-center py-5">
            <div class="empty-state">
                <div class="empty-icon mb-4">
                    <i class="bi bi-file-earmark-text" style="font-size: 4rem; color: #6c757d;"></i>
                </div>
                
                <h1 class="h3 mb-3">No posts yet</h1>
                <p class="text-muted mb-4">
                    There are no posts in {{ $board->name }} yet.<br>
                    Be the first to write a post.
                </p>
                
                @if($board->menu_id && can('write', $board))
                    <a href="{{ route('board.create', $board->slug) }}" class="btn btn-dark">
                        <i class="bi bi-plus-circle me-1"></i>
                        Write the first post
                    </a>
                @endif
            </div>
        </div>
    </div>
</main>

<style>
.board.show-empty {
    min-height: 90vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.empty-state {
    max-width: 400px;
    margin: 0 auto;
}

.empty-icon {
    opacity: 0.6;
}

@media (max-width: 768px) {
    .empty-state {
        padding: 0 1rem;
    }
    
    .empty-icon {
        font-size: 3rem !important;
    }
}
</style>
@endsection