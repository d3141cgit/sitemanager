// Comment Management JavaScript Functions

function createLoadingOverlay() {
    // Remove existing overlay if any
    const existingOverlay = document.getElementById('page-loading-overlay');
    if (existingOverlay) {
        existingOverlay.remove();
    }
    
    const overlay = document.createElement('div');
    overlay.id = 'page-loading-overlay';
    overlay.className = 'page-loading-overlay';
    overlay.style.cssText = `
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
    `;
    
    overlay.innerHTML = `
        <div class="page-loading-spinner" style="
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        "></div>
        <div class="text-muted">Loading comments...</div>
    `;
    
    // Add spin animation if not already defined
    if (!document.querySelector('#loading-spinner-style')) {
        const style = document.createElement('style');
        style.id = 'loading-spinner-style';
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }
    
    document.body.appendChild(overlay);
    return overlay;
}

function showPageLoading() {
    const overlay = document.getElementById('page-loading-overlay') || createLoadingOverlay();
    overlay.style.display = 'flex';
}

function hidePageLoading() {
    const overlay = document.getElementById('page-loading-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

function editComment(commentId) {
    // Hide content and show edit form
    const contentElement = document.querySelector(`[data-comment-id="${commentId}"] .comment-content`);
    const editForm = document.getElementById(`edit-form-${commentId}`);
    
    if (contentElement) contentElement.style.display = 'none';
    if (editForm) editForm.style.display = 'block';
}

function cancelEdit(commentId) {
    // Show content and hide edit form
    const contentElement = document.querySelector(`[data-comment-id="${commentId}"] .comment-content`);
    const editForm = document.getElementById(`edit-form-${commentId}`);
    
    if (contentElement) contentElement.style.display = 'block';
    if (editForm) editForm.style.display = 'none';
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
    
    // Get route from data attributes or global variables
    const updateUrl = window.commentRoutes?.update?.replace(':id', commentId);
    if (!updateUrl) {
        console.error('Comment update route not found');
        alert('Configuration error. Please refresh the page.');
        return;
    }
    
    fetch(updateUrl, {
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
            if (contentElement) {
                contentElement.innerHTML = content.replace(/\n/g, '<br>');
            }
            cancelEdit(commentId);
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
    const replyForm = document.getElementById(`reply-form-${commentId}`);
    if (replyForm) {
        replyForm.style.display = 'block';
        
        // Focus on the textarea
        const textarea = replyForm.querySelector('textarea');
        if (textarea) {
            textarea.focus();
        }
    }
    
    // Hide loading state
    hideReplyLoading(commentId);
}

function cancelReply(commentId) {
    const replyForm = document.getElementById(`reply-form-${commentId}`);
    if (replyForm) {
        replyForm.style.display = 'none';
    }
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
    
    // Get route from global variables
    const storeUrl = window.commentRoutes?.store;
    if (!storeUrl) {
        console.error('Comment store route not found');
        alert('Configuration error. Please refresh the page.');
        return;
    }
    
    fetch(storeUrl, {
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
    
    // Get route from global variables
    const deleteUrl = window.commentRoutes?.destroy?.replace(':id', commentId);
    if (!deleteUrl) {
        console.error('Comment delete route not found');
        alert('Configuration error. Please refresh the page.');
        return;
    }
    
    fetch(deleteUrl, {
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
            const commentElement = document.querySelector(`[data-comment-id="${commentId}"]`);
            if (commentElement) {
                commentElement.remove();
            }
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
    // Get route from global variables
    const approveUrl = window.commentRoutes?.approve?.replace(':id', commentId);
    if (!approveUrl) {
        console.error('Comment approve route not found');
        alert('Configuration error. Please refresh the page.');
        return;
    }
    
    fetch(approveUrl, {
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

function showAlert(message, type = 'info') {
    // Remove existing alerts
    document.querySelectorAll('.comment-alert').forEach(alert => alert.remove());
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show position-fixed comment-alert`;
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
