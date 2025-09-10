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
    <div class="text-muted">
        <p>No comments yet.</p>
    </div>
@endif
