{{-- Comments List with Pagination --}}
@if($comments && $comments->count() > 0)
    <div id="comments-list">
        @foreach($comments as $comment)
            @include('sitemanager::board.partials.comment', ['comment' => $comment, 'level' => 0])
        @endforeach
    </div>
    
    {{-- Pagination Links --}}
    @if($comments->hasPages())
        {{ $comments->links('sitemanager::pagination.comments') }}
    @endif
@else
    <div id="no-comments" class="text-center text-muted mt-4">
        <p>No comments yet. Be the first to comment!</p>
    </div>
@endif
