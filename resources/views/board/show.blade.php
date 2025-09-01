@extends('sitemanager::layouts.app')

@section('title', $post->title . ' - ' . $board->name)

@push('head')
    @if($post->isSecret())
        <meta name="robots" content="noindex,nofollow">
    @endif
    {!! resource('sitemanager::js/board/show.js') !!}
    @if($board->getSetting('allow_comments', true))
        {!! resource('sitemanager::js/board/comment.js') !!}
    @endif
@endpush

@section('content')
<div class="container py-4">
    <!-- Navigation Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('board.index', $board->slug) }}">{{ $board->name }}</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">{{ $post->title }}</li>
        </ol>
    </nav>

    <!-- Post Content -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <!-- Post Header -->
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                        <div class="flex-grow-1">
                            <h1 class="h4 mb-2">
                                @if($post->is_notice)
                                    <span class="badge bg-warning text-dark me-2">Notice</span>
                                @endif
                                @if($post->is_featured)
                                    <i class="bi bi-star-fill text-warning me-2"></i>
                                @endif
                                @if($post->isSecret())
                                    <i class="bi bi-lock-fill text-warning me-2"></i>
                                @endif
                                {{ $post->title }}
                            </h1>
                            
                            <div class="d-flex flex-wrap gap-3 text-muted small">
                                <span>
                                    <i class="bi bi-person"></i> 
                                    @if($post->author_profile_photo)
                                        <img src="{{ $post->author_profile_photo }}" 
                                             alt="{{ $post->author }}" 
                                             class="profile-photo me-1"
                                             style="width: 20px; height: 20px; border-radius: 50%; object-fit: cover;">
                                    @endif
                                    {{ $post->author }}
                                </span>
                                <span>
                                    <i class="bi bi-calendar"></i> {{ $post->created_at->format('M j, Y H:i') }}
                                </span>
                                <span>
                                    <i class="bi bi-eye"></i> {{ number_format($post->view_count) }} views
                                </span>
                                @if($post->comment_count > 0)
                                    <span data-comment-count>
                                        <i class="bi bi-chat"></i> {{ $post->comment_count }} comments
                                    </span>
                                @endif
                                @if($post->category)
                                    <span class="badge bg-secondary">{{ $post->category }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-2 mt-2 mt-md-0">
                            @if($canEdit)
                                <a href="{{ route('board.edit', [$board->slug, $post->slug ?: $post->id]) }}" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                            @endif

                            @if($canDelete)
                                <form method="POST" action="{{ route('board.destroy', [$board->slug, $post->slug ?: $post->id]) }}" 
                                      class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Are you sure you want to delete this post?')">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Post Body -->
                <div class="card-body">
                    <div class="post-content">
                        {!! $post->content !!}
                    </div>

                    <!-- Attachments -->
                    @if($attachments && $attachments->count() > 0)
                        <div class="mt-4 pt-3 border-top">
                            <h6 class="mb-3">
                                <i class="bi bi-paperclip"></i> Attachments ({{ $attachments->count() }})
                            </h6>
                            <div class="row">
                                @foreach($attachments as $attachment)
                                    <div class="col-md-6 col-lg-4 mb-2">
                                        <div class="card h-100">
                                            <div class="card-body p-3">
                                                <div class="d-flex align-items-start">
                                                    <div class="flex-shrink-0 me-2">
                                                        @if($attachment->is_image)
                                                            <div style="width: 60px; height: 60px; overflow: hidden; border-radius: 8px; border: 1px solid #ddd; cursor: pointer; transition: transform 0.2s ease;"
                                                                 onmouseover="this.style.transform='scale(1.05)'"
                                                                 onmouseout="this.style.transform='scale(1)'"
                                                                 onclick="showImagePreview('{{ $attachment->preview_url }}', '{{ $attachment->original_name }}')"
                                                                 title="Click to view full image">
                                                                <img src="{{ $attachment->preview_url }}" 
                                                                     style="width: 100%; height: 100%; object-fit: cover;" 
                                                                     alt="Preview">
                                                            </div>
                                                        @else
                                                            <i class="bi {{ $attachment->file_icon }} fs-4"></i>
                                                        @endif
                                                    </div>
                                                    <div class="flex-grow-1 min-w-0">
                                                        <h6 class="mb-1" title="{{ $attachment->original_name }}">
                                                            {{ $attachment->original_name }}
                                                        </h6>
                                                        <div class="small text-muted">
                                                            {{ $attachment->file_size_human }}
                                                            • {{ $attachment->created_at->format('M j, Y') }}
                                                            @if($attachment->download_count > 0)
                                                                • {{ $attachment->download_count }} downloads
                                                            @endif
                                                        </div>
                                                        @if($attachment->description)
                                                            <div class="small text-muted mt-1">
                                                                {{ Str::limit($attachment->description, 50) }}
                                                            </div>
                                                        @endif
                                                        <div class="mt-2">
                                                            <a href="{{ $attachment->download_url }}" 
                                                               class="btn btn-sm btn-outline-primary"
                                                               target="_blank">
                                                                <i class="bi bi-download"></i> Download
                                                            </a>
                                                            @if($attachment->is_image)
                                                                <button type="button" class="btn btn-sm btn-outline-secondary ms-1"
                                                                        onclick="showImagePreview('{{ $attachment->preview_url }}', '{{ $attachment->original_name }}')">
                                                                    <i class="bi bi-arrows-fullscreen"></i> Full Size
                                                                </button>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Tags -->
                    @if($post->tags && count($post->tags) > 0)
                        <div class="mt-4 pt-3 border-top">
                            <div class="d-flex flex-wrap gap-1">
                                <span class="text-muted me-2">Tags:</span>
                                @foreach($post->tags as $tag)
                                    <span class="badge bg-light text-dark border">{{ $tag }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Post Navigation -->
    @if($prevPost || $nextPost)
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-2">
                        <div class="row">
                            @if($prevPost)
                                <div class="col-md-6 mb-2 mb-md-0">
                                    <a href="{{ route('board.show', [$board->slug, $prevPost->slug ?: $prevPost->id]) }}" 
                                       class="text-decoration-none">
                                        <small class="text-muted d-block">
                                            <i class="bi bi-chevron-left"></i> Previous Post
                                        </small>
                                        <span class="small">{{ Str::limit($prevPost->title, 50) }}</span>
                                    </a>
                                </div>
                            @endif
                            
                            @if($nextPost)
                                <div class="col-md-6 text-md-end">
                                    <a href="{{ route('board.show', [$board->slug, $nextPost->slug ?: $nextPost->id]) }}" 
                                       class="text-decoration-none">
                                        <small class="text-muted d-block">
                                            Next Post <i class="bi bi-chevron-right"></i>
                                        </small>
                                        <span class="small">{{ Str::limit($nextPost->title, 50) }}</span>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Comments Section -->
    @if($board->getSetting('allow_comments', true))
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-chat-left"></i> <span data-comment-count>Comments ({{ $post->comment_count }})</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Comment Form -->
                        @if(can('writeComments', $board))
                            <form id="commentForm" action="{{ route('board.comments.store', [$board->slug, $post->id]) }}" method="POST" class="mb-4">
                                @csrf
                                <input type="hidden" name="post_id" value="{{ $post->id }}">
                                <div class="mb-3">
                                    <label for="comment-content" class="form-label">Write a comment</label>
                                    <textarea id="comment-content" name="content" class="form-control" 
                                              rows="3" placeholder="Share your thoughts..." required></textarea>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        @if($board->getSetting('moderate_comments', false))
                                            Comments require approval before being displayed.
                                        @endif
                                    </small>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-send"></i> Post Comment
                                    </button>
                                </div>
                            </form>
                        @else
                            <div class="alert alert-info">
                                @if(!auth()->check())
                                    <a href="{{ route('login') }}">Login</a> to write a comment.
                                @else
                                    You don't have permission to write comments.
                                @endif
                            </div>
                        @endif

                        <!-- Comments List -->
                        <div id="comments-container">
                            @if($comments && $comments->count() > 0)
                                @foreach($comments as $comment)
                                    @include('sitemanager::board.partials.comment', ['comment' => $comment, 'level' => 0])
                                @endforeach
                            @else
                                <div class="text-center text-muted py-4" id="no-comments">
                                    <i class="bi bi-chat display-4 mb-3"></i>
                                    <p>No comments yet. Be the first to comment!</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Back to List -->
    <div class="row mt-4">
        <div class="col-12 text-center">
            <a href="{{ route('board.index', $board->slug) }}" class="btn btn-outline-secondary">
                <i class="bi bi-list"></i> Back to List
            </a>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.post-content {
    line-height: 1.6;
    font-size: 1rem;
}

.post-content img {
    max-width: 100%;
    height: auto;
    margin: 1rem 0;
}

.breadcrumb {
    background: none;
    padding: 0;
    margin: 0;
}

.comment-item {
    border-left: 3px solid var(--bs-border-color);
    margin-left: 1rem;
    padding-left: 1rem;
}

.comment-content {
    white-space: pre-wrap;
}

@media (max-width: 768px) {
    .container {
        padding-left: 15px;
        padding-right: 15px;
    }
    
    .card-header .d-flex {
        flex-direction: column;
        gap: 1rem;
    }
    
    .comment-item {
        margin-left: 0.5rem;
        padding-left: 0.5rem;
    }
}
</style>
@endpush

@push('scripts')
<script>
// Comment routes configuration for comment.js
window.commentRoutes = {
    store: "{{ route('board.comments.store', [$board->slug, $post->id]) }}",
    update: "{{ route('board.comments.update', [$board->slug, $post->id, ':id']) }}",
    destroy: "{{ route('board.comments.destroy', [$board->slug, $post->id, ':id']) }}",
    approve: "{{ route('board.comments.approve', [$board->slug, $post->id, ':id']) }}"
};
</script>
@endpush
