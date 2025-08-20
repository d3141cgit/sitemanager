<div class="comment-item mb-3" data-comment-id="{{ $comment->id }}" style="margin-left: {{ ($level ?? 0) * 30 }}px;">
    <div class="d-flex justify-content-between align-items-start mb-2">
        <div class="d-flex align-items-center">
            @if(($level ?? 0) > 0)
                <i class="bi bi-arrow-return-right me-2 text-muted"></i>
            @endif
            <strong class="me-2">{{ $comment->author_name }}</strong>
            <small class="text-muted">
                {{ $comment->created_at->diffForHumans() }}
                @if($comment->is_edited)
                    <span class="text-muted">(edited)</span>
                @endif
            </small>
            @if($comment->status === 'pending')
                <span class="badge bg-warning text-dark ms-2">Pending Approval</span>
            @endif
        </div>
        
        <!-- Comment Actions -->
        <div class="dropdown">
            @php
                $user = auth()->user();
                $canEdit = false;
                $canDelete = false;
                $canManage = false;
                $canReply = false;
                
                if ($user) {
                    // 본인 댓글인 경우 수정/삭제 가능
                    if ($comment->member_id === $user->id) {
                        $canEdit = true;
                        $canDelete = true;
                    }
                    
                    // 댓글 관리 권한이 있는 경우 모든 댓글 관리 가능
                    if (can('manageComments', $board)) {
                        $canEdit = true;
                        $canDelete = true;
                        $canManage = true;
                    }
                    
                    // 댓글 쓰기 권한이 있는 경우 답글 가능
                    if (can('writeComments', $board)) {
                        $canReply = true;
                    }
                }
            @endphp
            
            @if($canEdit || $canDelete || $canManage)
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                        type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @if($canEdit)
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="editComment({{ $comment->id }})">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                        </li>
                    @endif
                    
                    @if($canReply)
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="event.preventDefault(); showReplyLoading({{ $comment->id }}); replyToComment({{ $comment->id }})">
                                <i class="bi bi-reply"></i> <span class="reply-text">Reply</span>
                            </a>
                        </li>
                    @endif
                    
                    @if($canManage && $comment->status === 'pending')
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="approveComment({{ $comment->id }})">
                                <i class="bi bi-check-circle text-success"></i> Approve
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="rejectComment({{ $comment->id }})">
                                <i class="bi bi-x-circle text-danger"></i> Reject
                            </a>
                        </li>
                    @endif
                    
                    @if($canDelete)
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="javascript:void(0)" 
                               onclick="deleteComment({{ $comment->id }})">
                                <i class="bi bi-trash"></i> Delete
                            </a>
                        </li>
                    @endif
                </ul>
            @endif
        </div>
    </div>
    
    <!-- Comment Content -->
    <div class="comment-content mb-2">{!! nl2br($comment->content) !!}</div>
    
    <!-- Reply Form (Hidden by default) -->
    <div id="reply-form-{{ $comment->id }}" class="reply-form mt-3" style="display: none;">
        <form onsubmit="submitReply(event, {{ $comment->id }})">
            @csrf
            <input type="hidden" name="post_id" value="{{ $comment->post_id }}">
            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
            <div class="mb-2">
                <textarea name="content" class="form-control form-control-sm" 
                          rows="2" placeholder="Write a reply..." required></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-send"></i> Reply
                </button>
                <button type="button" class="btn btn-sm btn-secondary" 
                        onclick="cancelReply({{ $comment->id }})">
                    Cancel
                </button>
            </div>
        </form>
    </div>
    
    <!-- Edit Form (Hidden by default) -->
    <div id="edit-form-{{ $comment->id }}" class="edit-form mt-3" style="display: none;">
        <form onsubmit="submitEdit(event, {{ $comment->id }})">
            @csrf
            @method('PUT')
            <div class="mb-2">
                <textarea name="content" class="form-control form-control-sm" 
                          rows="3" required>{{ $comment->content }}</textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-check"></i> Save
                </button>
                <button type="button" class="btn btn-sm btn-secondary" 
                        onclick="cancelEdit({{ $comment->id }})">
                    Cancel
                </button>
            </div>
        </form>
    </div>
    
    <!-- Child Comments -->
    @if($comment->children && $comment->children->count() > 0 && $level < 3)
        <div class="child-comments mt-3">
            @foreach($comment->children->sortByDesc('created_at') as $child)
                @include('sitemanager::board.partials.comment', ['comment' => $child, 'level' => $level + 1])
            @endforeach
        </div>
    @endif
</div>

@if($level === 0)
    @push('scripts')
    <style>
    .page-loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        z-index: 9999;
        display: none;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }
    
    .page-loading-spinner {
        width: 50px;
        height: 50px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #007bff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 15px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>
    
    <!-- Loading Overlay -->
    <div id="page-loading-overlay" class="page-loading-overlay">
        <div class="page-loading-spinner"></div>
        <div class="text-muted">Loading comments...</div>
    </div>
    
    <script>
    function showPageLoading() {
        document.getElementById('page-loading-overlay').style.display = 'flex';
    }
    
    function hidePageLoading() {
        document.getElementById('page-loading-overlay').style.display = 'none';
    }
    
    function editComment(commentId) {
        // Hide content and show edit form
        document.querySelector(`[data-comment-id="${commentId}"] .comment-content`).style.display = 'none';
        document.getElementById(`edit-form-${commentId}`).style.display = 'block';
    }
    
    function cancelEdit(commentId) {
        // Show content and hide edit form
        document.querySelector(`[data-comment-id="${commentId}"] .comment-content`).style.display = 'block';
        document.getElementById(`edit-form-${commentId}`).style.display = 'none';
    }
    
    function submitEdit(event, commentId) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const content = formData.get('content');
        
        if (!content.trim()) {
            alert('Please write a comment.');
            return;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';
        submitBtn.disabled = true;
        
        fetch(`{{ route('board.comments.update', [$board->slug, $post->id, ':id']) }}`.replace(':id', commentId), {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ content: content })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update comment content with HTML rendering
                const contentElement = document.querySelector(`[data-comment-id="${commentId}"] .comment-content`);
                contentElement.innerHTML = content.replace(/\n/g, '<br>');
                cancelEdit(commentId);
                
                // Show success message
                showAlert('Comment updated successfully!', 'success');
            } else {
                alert(data.message || 'An error occurred while updating the comment.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the comment.');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }
    
    function showReplyLoading(commentId) {
        const replyLink = document.querySelector(`[onclick*="replyToComment(${commentId})"] .reply-text`);
        if (replyLink) {
            replyLink.innerHTML = '<i class="bi bi-hourglass-split"></i> Loading...';
        }
    }
    
    function hideReplyLoading(commentId) {
        const replyLink = document.querySelector(`[onclick*="replyToComment(${commentId})"] .reply-text`);
        if (replyLink) {
            replyLink.innerHTML = 'Reply';
        }
    }
    
    function replyToComment(commentId) {
        // Hide other reply forms
        document.querySelectorAll('.reply-form').forEach(form => {
            form.style.display = 'none';
        });
        
        // Show this reply form
        document.getElementById(`reply-form-${commentId}`).style.display = 'block';
        
        // Hide loading state
        hideReplyLoading(commentId);
        
        // Focus on the textarea
        const textarea = document.querySelector(`#reply-form-${commentId} textarea`);
        if (textarea) {
            textarea.focus();
        }
    }
    
    function cancelReply(commentId) {
        document.getElementById(`reply-form-${commentId}`).style.display = 'none';
        hideReplyLoading(commentId);
    }
    
    function submitReply(event, parentId) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const content = formData.get('content');
        const postId = formData.get('post_id');
        const parentCommentId = formData.get('parent_id');
        
        if (!content.trim()) {
            alert('Please write a reply.');
            return;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Posting...';
        submitBtn.disabled = true;
        
        fetch(`{{ route('board.comments.store', [$board->slug, $post->id]) }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                content: content,
                post_id: postId,
                parent_id: parentCommentId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                form.reset();
                cancelReply(parentId);
                showAlert(data.message, 'success');
                
                // Show loading overlay before refresh
                showPageLoading();
                
                // Refresh page to show new reply
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                alert(data.message || 'An error occurred while posting your reply.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while posting your reply.');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }
    
    function deleteComment(commentId) {
        if (!confirm('Are you sure you want to delete this comment?')) {
            return;
        }
        
        fetch(`{{ route('board.comments.destroy', [$board->slug, $post->id, ':id']) }}`.replace(':id', commentId), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove comment element
                document.querySelector(`[data-comment-id="${commentId}"]`).remove();
                showAlert(data.message, 'success');
            } else {
                alert(data.message || 'An error occurred while deleting the comment.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the comment.');
        });
    }
    
    function approveComment(commentId) {
        fetch(`{{ route('board.comments.approve', [$board->slug, $post->id, ':id']) }}`.replace(':id', commentId), {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove pending badge
                const badge = document.querySelector(`[data-comment-id="${commentId}"] .badge`);
                if (badge) badge.remove();
                showAlert(data.message, 'success');
            } else {
                alert(data.message || 'An error occurred while approving the comment.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while approving the comment.');
        });
    }
    
    function rejectComment(commentId) {
        fetch(`{{ route('board.comments.reject', [$board->slug, $post->id, ':id']) }}`.replace(':id', commentId), {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Hide comment
                document.querySelector(`[data-comment-id="${commentId}"]`).style.opacity = '0.5';
                showAlert(data.message, 'success');
            } else {
                alert(data.message || 'An error occurred while rejecting the comment.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while rejecting the comment.');
        });
    }
    
    function showAlert(message, type = 'info') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alert);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
    </script>
    @endpush
@endif
