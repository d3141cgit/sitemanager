@extends('sitemanager::layouts.sitemanager')

@section('title', t('Comment Management'))

@section('content')
<div class="content-header">
    <h1>
        <a href="{{ route('sitemanager.comments.index') }}">
            <i class="bi bi-chat opacity-75"></i> {{ t(Str::ucfirst($status) . ' Comments') }}
        </a>

        <span class="count">{{ number_format($pendingComments->total()) }}</span>
    </h1>

    <a href="{{ route('sitemanager.boards.index') }}" class="btn-default">
        <i class="bi bi-arrow-left"></i> {{ t('Back to Boards List') }}
    </a>
</div>

<form method="GET" action="{{ route('sitemanager.comments.index') }}" class="search-form" id="filterForm">
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
        <i class="bi bi-clock"></i> {{ t('Pending') }}
        @if($selectedBoardId && isset($statusCounts['pending']))
            <span class="badge bg-warning text-dark ms-1">{{ $statusCounts['pending'] }}</span>
        @endif
    </label>

    <input type="radio" class="btn-check" name="status" id="status_approved" value="approved" 
            {{ $status === 'approved' ? 'checked' : '' }} onchange="document.getElementById('filterForm').submit();">
    <label class="btn btn-outline-success" for="status_approved">
        <i class="bi bi-check-circle"></i> {{ t('Approved') }}
        @if($selectedBoardId && isset($statusCounts['approved']))
            <span class="badge bg-success ms-1">{{ $statusCounts['approved'] }}</span>
        @endif
    </label>

    <input type="radio" class="btn-check" name="status" id="status_deleted" value="deleted" 
            {{ $status === 'deleted' ? 'checked' : '' }} onchange="document.getElementById('filterForm').submit();">
    <label class="btn btn-outline-danger" for="status_deleted">
        <i class="bi bi-trash"></i> {{ t('Deleted') }}
        @if($selectedBoardId && isset($statusCounts['deleted']))
            <span class="badge bg-danger ms-1">{{ $statusCounts['deleted'] }}</span>
        @endif
    </label>

    <button type="submit" class="d-none"></button>
</form>

@if($selectedBoardId && $pendingComments->count() > 0)
    <div class="mb-3">    
        @if($status === 'pending')
        <div class="btn-group">
            <button type="button" class="btn btn-sm btn-outline-success" onclick="bulkAction('approve')">
                {{ t('Approve Selected') }}
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="bulkAction('delete')">
                {{ t('Delete Selected') }}
            </button>
        </div>
        @elseif($status === 'deleted')
        <div class="btn-group">
            <button type="button" class="btn btn-sm btn-outline-warning" onclick="bulkAction('restore')">
                {{ t('Restore Selected') }}
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="bulkAction('force_delete')">
                {{ t('Force Delete Selected') }}
            </button>
        </div>
        @else
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="bulkAction('delete')">
            {{ t('Delete Selected') }}
        </button>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th width="30">
                        <input type="checkbox" id="checkAll">
                    </th>
                    <th>{{ t('Author') }}</th>
                    <th>{{ t('Post') }}</th>
                    <th>{{ t('Comment Content') }}</th>
                    <th>{{ t('Created At') }}</th>
                    <th class="text-end">{{ t('Actions') }}</th>
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
                            <span class="text-muted">{{ t('Deleted Post') }}</span>
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
                                <i class="bi bi-exclamation-triangle"></i> {{ t('Parent') }}: {{ $comment->parent->status }}
                            </small>
                        @elseif($comment->parent_id && !$comment->parent)
                            <small class="text-danger">
                                <i class="bi bi-exclamation-triangle"></i> {{ t('Parent deleted') }}
                            </small>
                        @endif
                        
                        {{-- 자식 댓글 정보 (부모 댓글의 경우) --}}
                        @if(!$comment->parent_id && $comment->children_count > 0)
                            <small class="text-info">
                                <i class="bi bi-chat-dots"></i> {{ $comment->children_count }} {{ t('replies') }}
                            </small>
                        @endif
                    </td>

                    <td class="number">
                        {{ $comment->created_at->format('Y-m-d H:i') }}
                    </td>

                    <td class="text-end actions">
                        {{-- 상태에 따른 버튼 표시 --}}
                        @if($status === 'deleted')
                            {{-- Restore 버튼 --}}
                            <button type="button" 
                                class="btn btn-outline-warning btn-sm {{ !$comment->actions['restore']['can'] ? 'disabled' : '' }}" 
                                onclick="restoreComment({{ $comment->id }}, '{{ $selectedBoard->slug }}')"
                                @if(!$comment->actions['restore']['can']) 
                                    disabled 
                                    title="{{ $comment->actions['restore']['reason'] }}"
                                @endif>
                                {{ t('Restore') }}
                            </button>
                            {{-- Force Delete 버튼 --}}
                            <button type="button" 
                                class="btn btn-outline-danger btn-sm {{ !$comment->actions['force_delete']['can'] ? 'disabled' : '' }}" 
                                onclick="forceDeleteComment({{ $comment->id }}, '{{ $selectedBoard->slug }}')"
                                @if(!$comment->actions['force_delete']['can']) 
                                    disabled 
                                    title="{{ $comment->actions['force_delete']['reason'] }}"
                                @endif>
                                {{ t('Force Delete') }}
                            </button>
                        @elseif($comment->status === 'pending')
                            {{-- Approve 버튼 --}}
                            <button type="button" 
                                class="btn btn-outline-success btn-sm {{ !$comment->actions['approve']['can'] ? 'disabled' : '' }}" 
                                onclick="approveComment({{ $comment->id }}, '{{ $selectedBoard->slug }}')"
                                @if(!$comment->actions['approve']['can']) 
                                    disabled 
                                    title="{{ $comment->actions['approve']['reason'] }}"
                                @endif>
                                {{ t('Approve') }}
                            </button>
                            {{-- Delete 버튼 --}}
                            <button type="button" 
                                class="btn btn-outline-danger btn-sm {{ !$comment->actions['delete']['can'] ? 'disabled' : '' }}" 
                                onclick="deleteComment({{ $comment->id }}, '{{ $selectedBoard->slug }}')"
                                @if(!$comment->actions['delete']['can']) 
                                    disabled 
                                    title="{{ $comment->actions['delete']['reason'] }}"
                                @endif>
                                {{ t('Delete') }}
                            </button>
                        @else
                            {{-- Delete 버튼 (approved 상태) --}}
                            <button type="button" 
                                class="btn btn-outline-danger btn-sm {{ !$comment->actions['delete']['can'] ? 'disabled' : '' }}" 
                                onclick="deleteComment({{ $comment->id }}, '{{ $selectedBoard->slug }}')"
                                @if(!$comment->actions['delete']['can']) 
                                    disabled 
                                    title="{{ $comment->actions['delete']['reason'] }}"
                                @endif>
                                {{ t('Delete') }}
                            </button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $pendingComments->appends(request()->query())->links() }}
@elseif($selectedBoardId)
    <p class="text-muted">{{ t('No comments found matching the criteria.') }}</p>        
@else
    <p class="text-muted">{{ t('Please select a board.') }}</p>    
@endif

@endsection

@push('scripts')
<script>
// Translation strings for JavaScript
const commentTranslations = {
    confirmApprove: @json(t('Are you sure you want to approve this comment?')),
    confirmDelete: @json(t('Are you sure you want to permanently delete this comment?')),
    confirmRestore: @json(t('Are you sure you want to restore this comment?')),
    confirmForceDelete: @json(t('Are you sure you want to permanently delete this comment? This action cannot be undone.')),
    anErrorOccurred: @json(t('An error occurred.')),
    networkError: @json(t('A network error occurred.')),
    pleaseSelectComments: @json(t('Please select comments to process.')),
    approve: @json(t('approve')),
    delete: @json(t('delete')),
    restore: @json(t('restore')),
    permanentlyDelete: @json(t('permanently delete')),
    confirmBulkAction: @json(t('Are you sure you want to {action} {count} selected comment(s)?'))
};

// 전체 체크박스 토글
document.getElementById('checkAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.comment-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Individual comment approval
function approveComment(commentId, boardSlug) {
    if (!confirm(commentTranslations.confirmApprove)) return;
    
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
            alert(data.message || commentTranslations.anErrorOccurred);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(commentTranslations.networkError);
    });
}

// Individual comment deletion
function deleteComment(commentId, boardSlug) {
    if (!confirm(commentTranslations.confirmDelete)) return;
    
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
            alert(data.message || commentTranslations.anErrorOccurred);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(commentTranslations.networkError);
    });
}

// Restore comment
function restoreComment(commentId, boardSlug) {
    if (!confirm(commentTranslations.confirmRestore)) return;
    
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
            alert(data.message || commentTranslations.anErrorOccurred);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(commentTranslations.networkError);
    });
}

// Force delete comment
function forceDeleteComment(commentId, boardSlug) {
    if (!confirm(commentTranslations.confirmForceDelete)) return;
    
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
            alert(data.message || commentTranslations.anErrorOccurred);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(commentTranslations.networkError);
    });
}

// Bulk actions
function bulkAction(action) {
    const selectedComments = Array.from(document.querySelectorAll('.comment-checkbox:checked'))
        .map(checkbox => checkbox.value);
    
    if (selectedComments.length === 0) {
        alert(commentTranslations.pleaseSelectComments);
        return;
    }
    
    const actionText = {
        'approve': commentTranslations.approve,
        'delete': commentTranslations.delete,
        'restore': commentTranslations.restore,
        'force_delete': commentTranslations.permanentlyDelete
    }[action];
    
    const confirmMessage = commentTranslations.confirmBulkAction
        .replace('{action}', actionText)
        .replace('{count}', selectedComments.length);
    
    if (!confirm(confirmMessage)) return;
    
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
            alert(data.message || commentTranslations.anErrorOccurred);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(commentTranslations.networkError);
    });
}
</script>
@endpush
