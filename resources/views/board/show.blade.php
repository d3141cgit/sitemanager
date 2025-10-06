@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', $post->title . ' - ' . $board->name)

@push('head')
    @if($post->isSecret())
        <meta name="robots" content="noindex,nofollow">
    @endif
    
    {{-- 로그인 상태 정보 전달 --}}
    <meta name="auth-user" content="{{ auth()->check() ? 'true' : 'false' }}">

    {!! resource('sitemanager::js/image-optimizer.js') !!}
    
    @if ($board->getSetting('enable_likes', false))
        {!! resource('sitemanager::js/board/like.js') !!}
    @endif

    @if($board->getSetting('allow_comments', true))
        {!! resource('sitemanager::js/board/comment.js') !!}
    @endif

    {!! resource('sitemanager::js/board/show.js') !!}
    {!! resource('sitemanager::css/board.default.css') !!}
    {!! resource('sitemanager::css/pagination.css') !!}
@endpush

@section('content')
<main class="board">
    <div class="content-container show">
        <div class="d-flex align-items-center">
            <time class="mb-0">{{ $post->published_at->format('M j, Y') }}</time>

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
                                            {{ $attachment->published_at->format('M j, Y') }}
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

        {{-- Action Buttons --}}
        <div class="action-buttons">
            @if($post->canEdit())
                <a href="{{ route('board.edit', [$board->slug, $post->slug ?: $post->id]) }}" 
                class="btn btn-sm btn-dark">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            @endif

            @if($post->canDelete())
                <form method="POST" action="{{ route('board.destroy', [$board->slug, $post->slug ?: $post->id]) }}" 
                    class="d-inline delete-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger delete-post-btn">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </form>
            @endif
        </div>

        {{-- Post Navigation --}}
        @if($prevPost || $nextPost)
            <div class="post-navigation">
                @if($prevPost)
                    <div class="prev-post">
                        <i class="bi bi-chevron-left"></i>
                        <a href="{{ route('board.show', [$board->slug, $prevPost->slug ?: $prevPost->id]) }}">
                            {{ Str::limit($prevPost->title, 50) }}
                        </a>
                    </div>
                @endif
                
                @if($nextPost)
                    <div class="next-post">
                        <a href="{{ route('board.show', [$board->slug, $nextPost->slug ?: $nextPost->id]) }}">
                            {{ Str::limit($nextPost->title, 50) }} 
                        </a>
                        <i class="bi bi-chevron-right"></i>
                    </div>
                @endif
            </div>
        @endif

        {{-- Comments Section --}}
        @if($board->getSetting('allow_comments', true) && can('readComments', $board))
            <a name="comments"></a>
            <div class="comments">
                <h5>
                    <i class="bi bi-chat-left"></i> Comments <span data-comment-count>{{ $post->comment_count ? $post->comment_count:'' }}</span>
                </h5>

                {{-- Comment Form --}}
                @if(can('writeComments', $board))
                    <form id="commentForm" action="{{ route('board.comments.store', [$board->slug, $post->id]) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="post_id" value="{{ $post->id }}">

                        @if(!auth()->check())
                            @include('sitemanager::board.partials.guest-author-form', ['comments' => $comments, 'board' => $board])
                        @endif

                        <textarea id="comment-content" name="content" class="form-control" rows="3" placeholder="Share your thoughts..." required></textarea>
                        
                        @if(can('uploadCommentFiles', $board))
                            <div>
                                <input type="file" id="comment-files" name="files[]" class="d-none" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                                <button type="button" class="btn btn-sm btn-light" onclick="document.getElementById('comment-files').click()"> <i class="bi bi-paperclip"></i> Add Files </button>
                                <div class="comment-file-preview"></div>
                            </div>
                        @endif

                        <div class="text-end">
                            <button type="submit" class="btn btn-sm btn-dark text-nowrap">
                                <i class="bi bi-send"></i> Post Comment @if($board->getSetting('moderate_comments', false)) (Require approval) @endif
                            </button>
                        </div>
                    </form>
                @endif

                {{-- Comments List with Pagination --}}
                <div id="comments-container">
                    @include('sitemanager::board.partials.comments', ['comments' => $comments, 'board' => $board])
                </div>
            </div>
        @endif
    </div>

    <div class="container" style="margin: 3rem auto;">
        @if ($board->getSetting('index_in_show', true))
            @if($posts->count() > 0)
                @include('sitemanager::board.partials.posts', ['posts' => $posts, 'notices' => $notices, 'board' => $board])
            @endif
        @endif

        {{-- Back to List --}}
        <div class="my-4">
            <div class="text-center">
                <a href="{{ route('board.index', $board->slug) }}" class="btn btn-outline-dark">
                    <i class="bi bi-list"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</main>
@endsection

@if($board->getSetting('allow_comments', true))
@push('scripts')
<script>
// Comment routes configuration for comment.js
window.commentRoutes = {
    index: "{{ route('board.comments.index', [$board->slug, $post->id]) }}",
    store: "{{ route('board.comments.store', [$board->slug, $post->id]) }}",
    update: "{{ route('board.comments.update', [$board->slug, $post->id, ':id']) }}",
    destroy: "{{ route('board.comments.destroy', [$board->slug, $post->id, ':id']) }}",
    approve: "{{ route('board.comments.approve', [$board->slug, $post->id, ':id']) }}",
    deleteAttachment: "{{ route('board.comments.attachment.delete', [$board->slug, $post->id, ':commentId', ':attachmentId']) }}",
    replyForm: "{{ route('board.comments.reply-form', [$board->slug, $post->id, ':id']) }}",
    editForm: "{{ route('board.comments.edit-form', [$board->slug, $post->id, ':id']) }}"
};
</script>
@endpush
@endif