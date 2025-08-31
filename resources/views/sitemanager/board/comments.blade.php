@extends('sitemanager::layouts.sitemanager')

@section('title', 'Comment Management')

@section('content')
<div class="container">
    <h1>{{ Str::ucfirst($status) }} Comments ({{ $pendingComments->total() }})</h1>

    <form method="GET" action="{{ route('sitemanager.comments.index') }}" class="my-4" id="filterForm">
        <div class="input-group">
            <select name="board_id" id="board_id" class="form-select" style="min-width:180px;max-width:300px;" onchange="document.getElementById('filterForm').submit();">
                @foreach($boards as $board)
                    <option value="{{ $board->id }}" 
                        {{ $selectedBoardId == $board->id ? 'selected' : '' }}>
                        {{ $board->name }}
                    </option>
                @endforeach
            </select>

            <input type="radio" class="btn-check" name="status" id="status_pending" value="pending" 
                    {{ $status === 'pending' ? 'checked' : '' }} onchange="document.getElementById('filterForm').submit();">
            <label class="btn btn-outline-warning" for="status_pending">
                <i class="bi bi-clock"></i> Pending
                @if($selectedBoardId && isset($statusCounts['pending']))
                    <span class="badge bg-warning text-dark ms-1">{{ $statusCounts['pending'] }}</span>
                @endif
            </label>

            <input type="radio" class="btn-check" name="status" id="status_approved" value="approved" 
                    {{ $status === 'approved' ? 'checked' : '' }} onchange="document.getElementById('filterForm').submit();">
            <label class="btn btn-outline-success" for="status_approved">
                <i class="bi bi-check-circle"></i> Approved
                @if($selectedBoardId && isset($statusCounts['approved']))
                    <span class="badge bg-success ms-1">{{ $statusCounts['approved'] }}</span>
                @endif
            </label>

            <input type="radio" class="btn-check" name="status" id="status_deleted" value="deleted" 
                    {{ $status === 'deleted' ? 'checked' : '' }} onchange="document.getElementById('filterForm').submit();">
            <label class="btn btn-outline-danger" for="status_deleted">
                <i class="bi bi-trash"></i> Deleted
                @if($selectedBoardId && isset($statusCounts['deleted']))
                    <span class="badge bg-danger ms-1">{{ $statusCounts['deleted'] }}</span>
                @endif
            </label>
        </div>

        <button type="submit" class="d-none"></button>
    </form>

    @if($selectedBoardId && $pendingComments->count() > 0)
        <div class="mb-1">    
            @if($status === 'pending')
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-success" onclick="bulkAction('approve')">
                    Approve Selected
                </button>
                <button type="button" class="btn btn-sm btn-danger" onclick="bulkAction('delete')">
                    Delete Selected
                </button>
            </div>
            @elseif($status === 'deleted')
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-warning" onclick="bulkAction('restore')">
                    Restore Selected
                </button>
                <button type="button" class="btn btn-sm btn-danger" onclick="bulkAction('force_delete')">
                    Force Delete Selected
                </button>
            </div>
            @else
            <button type="button" class="btn btn-sm btn-danger" onclick="bulkAction('delete')">
                Delete Selected
            </button>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th width="30">
                            <input type="checkbox" id="checkAll">
                        </th>
                        <th>Author</th>
                        <th>Post</th>
                        <th>Comment Content</th>
                        <th>Created At</th>
                        <th width="120">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingComments as $comment)
                    <tr id="comment-{{ $comment->id }}" class="{{ $comment->parent_id ? 'table-secondary' : '' }}">
                        <td>
                            <input type="checkbox" class="comment-checkbox" value="{{ $comment->id }}">
                        </td>

                        <td>
                            {{ $comment->author }}
                        </td>

                        <td>
                            @if($comment->post)
                                <a href="{{ route('board.show', [$selectedBoard->slug, $comment->post->id]) }}" 
                                    target="_blank" class="text-decoration-none">
                                    {{ Str::limit($comment->post->title, 30) }}
                                </a>
                            @else
                                <span class="text-muted">Deleted Post</span>
                            @endif
                        </td>

                        <td>
                            <div class="comment-content {{ $comment->parent_id ? 'ms-2' : '' }}">
                                @if($comment->parent_id)
                                    <span class="text-muted me-2">↳</span>
                                @endif
                                {!! Str::limit(strip_tags($comment->content), 100) !!}
                            </div>
                            
                            {{-- 부모 상태 경고 (자식 댓글의 경우) --}}
                            @if($comment->parent_id && $comment->parent && $comment->parent->status !== 'approved')
                                <small class="text-warning">
                                    <i class="bi bi-exclamation-triangle"></i> Parent: {{ $comment->parent->status }}
                                </small>
                            @elseif($comment->parent_id && !$comment->parent)
                                <small class="text-danger">
                                    <i class="bi bi-exclamation-triangle"></i> Parent deleted
                                </small>
                            @endif
                            
                            {{-- 자식 댓글 정보 (부모 댓글의 경우) --}}
                            @if(!$comment->parent_id && $comment->children_count > 0)
                                <small class="text-info">
                                    <i class="bi bi-chat-dots"></i> {{ $comment->children_count }} replies
                                </small>
                            @endif
                        </td>

                        <td>
                            {{ $comment->created_at->format('Y-m-d H:i') }}
                        </td>

                        <td>
                            <div class="btn-group btn-group-sm text-nowrap" role="group">
                                {{-- 상태에 따른 버튼 표시 --}}
                                @if($status === 'deleted')
                                    {{-- Restore 버튼 --}}
                                    <button type="button" 
                                        class="btn btn-warning btn-sm {{ !$comment->actions['restore']['can'] ? 'disabled' : '' }}" 
                                        onclick="restoreComment({{ $comment->id }}, '{{ $selectedBoard->slug }}')"
                                        @if(!$comment->actions['restore']['can']) 
                                            disabled 
                                            title="{{ $comment->actions['restore']['reason'] }}"
                                        @endif>
                                        Restore
                                    </button>
                                    {{-- Force Delete 버튼 --}}
                                    <button type="button" 
                                        class="btn btn-danger btn-sm {{ !$comment->actions['force_delete']['can'] ? 'disabled' : '' }}" 
                                        onclick="forceDeleteComment({{ $comment->id }}, '{{ $selectedBoard->slug }}')"
                                        @if(!$comment->actions['force_delete']['can']) 
                                            disabled 
                                            title="{{ $comment->actions['force_delete']['reason'] }}"
                                        @endif>
                                        Force Delete
                                    </button>
                                @elseif($comment->status === 'pending')
                                    {{-- Approve 버튼 --}}
                                    <button type="button" 
                                        class="btn btn-success btn-sm {{ !$comment->actions['approve']['can'] ? 'disabled' : '' }}" 
                                        onclick="approveComment({{ $comment->id }}, '{{ $selectedBoard->slug }}')"
                                        @if(!$comment->actions['approve']['can']) 
                                            disabled 
                                            title="{{ $comment->actions['approve']['reason'] }}"
                                        @endif>
                                        Approve
                                    </button>
                                    {{-- Delete 버튼 --}}
                                    <button type="button" 
                                        class="btn btn-danger btn-sm {{ !$comment->actions['delete']['can'] ? 'disabled' : '' }}" 
                                        onclick="deleteComment({{ $comment->id }}, '{{ $selectedBoard->slug }}')"
                                        @if(!$comment->actions['delete']['can']) 
                                            disabled 
                                            title="{{ $comment->actions['delete']['reason'] }}"
                                        @endif>
                                        Delete
                                    </button>
                                @else
                                    {{-- Delete 버튼 (approved 상태) --}}
                                    <button type="button" 
                                        class="btn btn-danger btn-sm {{ !$comment->actions['delete']['can'] ? 'disabled' : '' }}" 
                                        onclick="deleteComment({{ $comment->id }}, '{{ $selectedBoard->slug }}')"
                                        @if(!$comment->actions['delete']['can']) 
                                            disabled 
                                            title="{{ $comment->actions['delete']['reason'] }}"
                                        @endif>
                                        Delete
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $pendingComments->appends(request()->query())->links() }}
    @elseif($selectedBoardId)
        <p class="text-muted">No comments found matching the criteria.</p>        
    @else
        <p class="text-muted">Please select a board.</p>    
    @endif
</div>

@push('scripts')
<script>
// 전체 체크박스 토글
document.getElementById('checkAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.comment-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Individual comment approval
function approveComment(commentId, boardSlug) {
    if (!confirm('Are you sure you want to approve this comment?')) return;
    
    fetch('{{ route("sitemanager.comments.approve") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            comment_id: commentId,
            board_slug: boardSlug
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'An error occurred.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('A network error occurred.');
    });
}

// Individual comment deletion
function deleteComment(commentId, boardSlug) {
    if (!confirm('Are you sure you want to permanently delete this comment?')) return;
    
    fetch('{{ route("sitemanager.comments.delete") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            comment_id: commentId,
            board_slug: boardSlug
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'An error occurred.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('A network error occurred.');
    });
}

// Restore comment
function restoreComment(commentId, boardSlug) {
    if (!confirm('Are you sure you want to restore this comment?')) return;
    
    fetch('{{ route("sitemanager.comments.restore") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            comment_id: commentId,
            board_slug: boardSlug
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'An error occurred.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('A network error occurred.');
    });
}

// Force delete comment
function forceDeleteComment(commentId, boardSlug) {
    if (!confirm('Are you sure you want to permanently delete this comment? This action cannot be undone.')) return;
    
    fetch('{{ route("sitemanager.comments.force-delete") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            comment_id: commentId,
            board_slug: boardSlug
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'An error occurred.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('A network error occurred.');
    });
}

// Bulk actions
function bulkAction(action) {
    const selectedComments = Array.from(document.querySelectorAll('.comment-checkbox:checked'))
        .map(checkbox => checkbox.value);
    
    if (selectedComments.length === 0) {
        alert('Please select comments to process.');
        return;
    }
    
    const actionText = {
        'approve': 'approve',
        'delete': 'delete',
        'restore': 'restore',
        'force_delete': 'permanently delete'
    }[action];
    
    if (!confirm(`Are you sure you want to ${actionText} ${selectedComments.length} selected comment(s)?`)) return;
    
    const boardSlug = '{{ $selectedBoard?->slug }}';
    
    fetch('{{ route("sitemanager.comments.bulk") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            action: action,
            comment_ids: selectedComments,
            board_slug: boardSlug
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'An error occurred.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('A network error occurred.');
    });
}
</script>
@endpush
@endsection
