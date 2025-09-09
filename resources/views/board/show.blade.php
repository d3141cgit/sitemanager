@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', $post->title . ' - ' . $board->name)

@push('head')
    @if($post->isSecret())
        <meta name="robots" content="noindex,nofollow">
    @endif

    @if ($board->getSetting('enable_likes', false))
        {!! resource('sitemanager::js/board/like.js') !!}
    @endif

    @if($board->getSetting('allow_comments', true))
        {!! resource('sitemanager::js/board/comment.js') !!}
    @endif

    {!! resource('sitemanager::js/board/show.js') !!}
    {!! resource('sitemanager::css/board.default.css') !!}
@endpush

@section('content')
<main class="board show">
    <div class="content-container">
        <div class="d-flex align-items-center">
            <time class="mb-0">{{ $post->created_at->format('M j, Y') }}</time>

            @if($post->category)
                <div class="post-categories mb-0 ms-2">
                    @foreach($post->categories as $category)
                        <span>{{ $category }}</span>
                    @endforeach
                </div>
            @endif
        </div>
        
        <h1>
            @if($post->isSecret())
                <i class="bi bi-lock-fill text-warning"></i>
            @endif
            {{ $post->title }}
        </h1>

        @if ($board->getSetting('show_info'))
        <div class="post-meta">
            @if ($board->getSetting('show_name'))
                @if($post->author_profile_photo)
                    <img src="{{ $post->author_profile_photo }}" alt="{{ $post->author }}" class="profile-photo">
                @endif
                <span class="name">
                    {{ $post->author_name }}
                </span>
            @endif

            <span>
                <i class="bi bi-eye"></i> {{ number_format($post->view_count) }}
            </span>

            @if($post->comment_count > 0)
                <span data-comment-count>
                    <i class="bi bi-chat"></i> {{ $post->comment_count }}
                </span>
            @endif

            @if ($board->getSetting('enable_likes', false))
                <button type="button" 
                        class="like-btn" 
                        data-post-id="{{ $post->id }}"
                        data-board-slug="{{ $board->slug }}"
                        data-like-count="{{ $post->like_count ?? 0 }}"
                        data-has-liked="{{ $hasLiked ? 'true' : 'false' }}"
                        {{ $hasLiked ? 'disabled' : '' }}>
                    <i class="bi {{ $hasLiked ? 'bi-heart-fill' : 'bi-heart' }}"></i> 
                    <span class="like-count">{{ number_format($post->like_count ?? '') }}</span>
                </button>
            @endif
        </div>
        @endif

        @if ($post->header_image)
            <div class="header-image">
                <img src="{{ $post->header_image['url'] }}" 
                        alt="{{ $post->header_image['alt'] }}" 
                        class="img-fluid">
                @if($post->header_image['description'])
                    <p class="image-caption">
                        {{ $post->header_image['description'] }}
                    </p>
                @endif
            </div>
        @endif

        <article>
            {!! $post->content !!}

            {{-- Tags --}}
            @if($post->tags && count($post->tags) > 0)
                <div class="tags">
                    @foreach($post->tags as $tag)
                        <span>{{ $tag }}</span>
                    @endforeach
                </div>
            @endif
        </article>

        {{-- Attachments --}}
        @if($attachments && $attachments->count() > 0)
            <div class="attachments">
                <h6>
                    <i class="bi bi-paperclip"></i> Attachments ({{ $attachments->count() }})
                </h6>
                <div class="attachment-list">
                    @foreach($attachments as $attachment)
                        <div class="attachment-item">
                            <div class="attachment-item-body">
                                @if($attachment->is_image)
                                    <div class="attachment-thumbnail"
                                        onmouseover="this.style.transform='scale(1.05)'"
                                        onmouseout="this.style.transform='scale(1)'"
                                        onclick="showImagePreview('{{ $attachment->preview_url }}', '{{ $attachment->original_name }}')"
                                        title="Click to view full image">
                                        <img src="{{ $attachment->preview_url }}" alt="Preview">
                                    </div>
                                @else
                                    <div class="attachment-thumbnail">
                                        <i class="bi {{ $attachment->file_icon }} file-icon"></i>
                                    </div>
                                @endif
        
                                <div class="attachment-content">
                                    <h6 title="{{ $attachment->original_name }}">
                                        {{ $attachment->original_name }}
                                    </h6>

                                    @if($attachment->description)
                                        <p class="attachment-description">
                                            {{ $attachment->description }}
                                        </p>
                                    @endif

                                    <div class="attachment-meta">
                                        <span>
                                            <i class="bi bi-file"></i>
                                            {{ $attachment->file_size_human }}
                                        </span>
                                        <span>
                                            <i class="bi bi-calendar"></i>
                                            {{ $attachment->created_at->format('M j, Y') }}
                                        </span>
                                        @if($attachment->download_count > 0)
                                            <span>
                                                <i class="bi bi-download"></i>
                                                {{ $attachment->download_count }} downloads
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <div class="attachment-footer">
                                <div class="attachment-actions">
                                    <a href="{{ $attachment->download_url }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                    @if($attachment->is_image)
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                onclick="showImagePreview('{{ $attachment->preview_url }}', '{{ $attachment->original_name }}')">
                                            <i class="bi bi-arrows-fullscreen"></i> Preview
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif


        <!-- Action Buttons -->
        <div class="action-buttons">
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
        @if($board->getSetting('allow_comments', true) && can('readComments', $board))
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
                                <form id="commentForm" action="{{ route('board.comments.store', [$board->slug, $post->id]) }}" method="POST" enctype="multipart/form-data" class="mb-4">
                                    @csrf
                                    <input type="hidden" name="post_id" value="{{ $post->id }}">
                                    <div class="mb-3">
                                        <label for="comment-content" class="form-label">Write a comment</label>
                                        <textarea id="comment-content" name="content" class="form-control" 
                                                rows="3" placeholder="Share your thoughts..." required></textarea>
                                    </div>
                                    
                                    @if(can('uploadCommentFiles', $board))
                                        <div class="mb-3">
                                            <label for="comment-files" class="form-label">
                                                <i class="bi bi-paperclip"></i> Attach files (optional)
                                            </label>
                                            <input type="file" class="form-control" id="comment-files" name="files[]" multiple>
                                            <div class="form-text">
                                                You can attach multiple files. Maximum file size: {{ ini_get('upload_max_filesize') }}
                                            </div>
                                            <div id="comment-file-preview" class="mt-2"></div>
                                        </div>
                                    @endif
                                    
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

        <!-- Comments Access Denied Message -->
        @if($board->getSetting('allow_comments', true) && !can('readComments', $board))
            <div class="row mt-4">
                <div class="col-12">
                    <div class="alert alert-warning">
                        <i class="bi bi-lock"></i> 
                        @if(!auth()->check())
                            <a href="{{ route('login') }}">Login</a> to view comments.
                        @else
                            You don't have permission to view comments.
                        @endif
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
</main>
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

@if($board->getSetting('allow_comments', true))
@push('scripts')
<script>
// Comment routes configuration for comment.js
window.commentRoutes = {
    store: "{{ route('board.comments.store', [$board->slug, $post->id]) }}",
    update: "{{ route('board.comments.update', [$board->slug, $post->id, ':id']) }}",
    destroy: "{{ route('board.comments.destroy', [$board->slug, $post->id, ':id']) }}",
    approve: "{{ route('board.comments.approve', [$board->slug, $post->id, ':id']) }}",
    deleteAttachment: "{{ route('board.comments.attachment.delete', [$board->slug, $post->id, ':commentId', ':attachmentId']) }}"
};
</script>
@endpush
@endif