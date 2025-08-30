@extends('sitemanager::admin.layout')

@section('title', 'Comment Management')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    Comment Management
                    @if($selectedBoard)
                        <small class="text-muted">- {{ $selectedBoard->name }}</small>
                    @endif
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="{{ route('sitemanager.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('sitemanager.boards.index') }}">Boards</a></li>
                    <li class="breadcrumb-item active">Comment Management</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <!-- 필터 영역 -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Filters</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('sitemanager.admin.comments.index') }}" class="row g-3">
                    <div class="col-md-4">
                        <label for="board_id" class="form-label">Board</label>
                        <select name="board_id" id="board_id" class="form-control">
                            <option value="">All Boards</option>
                            @foreach($boards as $board)
                                <option value="{{ $board->id }}" 
                                    {{ $selectedBoardId == $board->id ? 'selected' : '' }}>
                                    {{ $board->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="deleted" {{ $status === 'deleted' ? 'selected' : '' }}>Deleted</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        @if($selectedBoardId && $pendingComments->count() > 0)
        <!-- 댓글 목록 -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Comments ({{ $pendingComments->total() }})</h3>
                <div class="card-tools">
                    @if($status === 'pending')
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-success" onclick="bulkAction('approve')">
                            Approve Selected
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="bulkAction('reject')">
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
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                @if($status === 'pending' || $status === 'deleted')
                                <th width="30">
                                    <input type="checkbox" id="checkAll">
                                </th>
                                @endif
                                <th>Author</th>
                                <th>Post</th>
                                <th>Comment Content</th>
                                <th>Created At</th>
                                <th>Status</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingComments as $comment)
                            <tr id="comment-{{ $comment->id }}">
                                @if($status === 'pending' || $status === 'deleted')
                                <td>
                                    <input type="checkbox" class="comment-checkbox" value="{{ $comment->id }}">
                                </td>
                                @endif
                                <td>
                                    {{ $comment->author_name ?: 'Anonymous' }}
                                    @if($comment->member)
                                        <br><small class="text-muted">{{ $comment->member->name }}</small>
                                    @endif
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
                                    <div class="comment-content">
                                        {!! Str::limit(strip_tags($comment->content), 100) !!}
                                    </div>
                                    @if($comment->parent_id)
                                        <small class="text-muted">↳ Reply</small>
                                    @endif
                                </td>
                                <td>
                                    {{ $comment->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td>
                                    <span class="badge badge-{{ 
                                        $status === 'deleted' ? 'danger' : (
                                            $comment->status === 'approved' ? 'success' : 
                                            ($comment->status === 'pending' ? 'warning' : 'danger')
                                        )
                                    }}">
                                        @if($status === 'deleted')
                                            Deleted
                                        @else
                                            {{ [
                                                'pending' => 'Pending',
                                                'approved' => 'Approved'
                                            ][$comment->status] ?? $comment->status }}
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        @if($status === 'deleted')
                                            <button type="button" class="btn btn-warning btn-sm" 
                                                onclick="restoreComment({{ $comment->id }}, '{{ $selectedBoard->slug }}')">
                                                Restore
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" 
                                                onclick="forceDeleteComment({{ $comment->id }}, '{{ $selectedBoard->slug }}')">
                                                Force Delete
                                            </button>
                                        @elseif($comment->status === 'pending')
                                            <button type="button" class="btn btn-success btn-sm" 
                                                onclick="approveComment({{ $comment->id }}, '{{ $selectedBoard->slug }}')">
                                                Approve
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" 
                                                onclick="rejectComment({{ $comment->id }}, '{{ $selectedBoard->slug }}')">
                                                Delete
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-danger btn-sm" 
                                                onclick="deleteComment({{ $comment->id }}, '{{ $selectedBoard->slug }}')">
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
            </div>
            <div class="card-footer">
                {{ $pendingComments->appends(request()->query())->links() }}
            </div>
        </div>
        @elseif($selectedBoardId)
        <div class="card">
            <div class="card-body text-center">
                <p class="text-muted">No comments found matching the criteria.</p>
            </div>
        </div>
        @else
        <div class="card">
            <div class="card-body text-center">
                <p class="text-muted">Please select a board.</p>
            </div>
        </div>
        @endif
    </div>
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
    
    fetch('{{ route("sitemanager.admin.comments.approve") }}', {
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

// Individual comment rejection (delete)
function rejectComment(commentId, boardSlug) {
    if (!confirm('Are you sure you want to delete this comment?')) return;
    
    fetch('{{ route("sitemanager.admin.comments.reject") }}', {
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
    
    fetch('{{ route("sitemanager.admin.comments.delete") }}', {
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
    
    fetch('{{ route("sitemanager.admin.comments.restore") }}', {
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
    
    fetch('{{ route("sitemanager.admin.comments.force-delete") }}', {
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
        'reject': 'delete',
        'restore': 'restore',
        'force_delete': 'permanently delete'
    }[action];
    
    if (!confirm(`Are you sure you want to ${actionText} ${selectedComments.length} selected comment(s)?`)) return;
    
    const boardSlug = '{{ $selectedBoard?->slug }}';
    
    fetch('{{ route("sitemanager.admin.comments.bulk") }}', {
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
