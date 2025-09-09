document.addEventListener('DOMContentLoaded', function() {
    initializeLikeButton();
});

/**
 * Initialize like button functionality
 */
function initializeLikeButton() {
    const likeBtns = document.querySelectorAll('.like-btn');
    
    likeBtns.forEach(likeBtn => {
        const hasLiked = likeBtn.dataset.hasLiked === 'true';
        
        // 이미 좋아요를 눌렀다면 툴팁 추가
        if (hasLiked) {
            likeBtn.title = 'You have already liked this post';
        }
        
        likeBtn.addEventListener('click', function() {
            // 이미 좋아요를 눌렀다면 동작하지 않음
            if (this.dataset.hasLiked === 'true') {
                showMessage('info', 'You have already liked this post');
                return;
            }
            
            const postId = this.dataset.postId;
            const boardSlug = this.dataset.boardSlug;
            const likeCountSpan = this.querySelector('.like-count');
            const icon = this.querySelector('i');
            
            // Disable button to prevent multiple clicks
            this.disabled = true;
            
            fetch(`/board/${boardSlug}/${postId}/like`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update like count
                    likeCountSpan.textContent = new Intl.NumberFormat().format(data.like_count);
                    
                    // Change icon to filled heart
                    icon.className = 'bi bi-heart-fill';
                    
                    // Change button style to indicate it's been liked
                    this.className = 'btn btn-sm btn-danger like-btn';
                    
                    // Mark as liked and keep disabled
                    this.dataset.hasLiked = 'true';
                    this.title = 'You have already liked this post';
                    
                    // Show success message
                    showMessage('success', data.message || 'Like added successfully!');
                } else if (data.already_liked) {
                    // Handle case where user already liked (shouldn't happen with proper state management)
                    showMessage('info', data.error || 'You have already liked this post');
                    
                    // Update UI to reflect already liked state
                    icon.className = 'bi bi-heart-fill';
                    this.className = 'btn btn-sm btn-danger like-btn';
                    this.dataset.hasLiked = 'true';
                    this.title = 'You have already liked this post';
                } else {
                    showMessage('error', data.error || 'Failed to like post');
                    this.disabled = false; // Re-enable if it was a different error
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'An error occurred while liking the post');
                this.disabled = false; // Re-enable on network error
            });
        });
    });
}