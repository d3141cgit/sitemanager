/**
 * Board Show JavaScript
 * Handles post detail view functionality including image preview
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeImagePreview();
});

/**
 * Initialize image preview for content images
 */
function initializeImagePreview() {
    // Add click handlers to all images in content (excluding comment attachment images)
    const contentImages = document.querySelectorAll('.post-content img, .comment-content img:not(.comment-attachment-image)');
    contentImages.forEach(img => {
        img.style.cursor = 'pointer';
        img.addEventListener('click', function() {
            showImagePreview(this.src, this.alt || 'Image');
        });
    });
}

/**
 * Show image in preview modal using SiteManager notification system
 */
function showImagePreview(src, alt) {
    SiteManager.modals.showImagePreview(src, alt || 'Image', src);
}

/**
 * Print post content
 */
function printPost() {
    const title = document.querySelector('.post-title, h1')?.textContent || 'Post';
    const content = document.querySelector('.post-content')?.innerHTML || '';
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${title}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .post-title { margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .post-content { line-height: 1.6; }
                .post-content img { max-width: 100%; height: auto; }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <h1 class="post-title">${title}</h1>
            <div class="post-content">${content}</div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
    printWindow.close();
}

/**
 * Helper function to show messages using SiteManager notification system
 */
function showMessage(type, message) {
    // Convert type to match SiteManager notification types
    const notificationType = type === 'error' ? 'error' : 
                            type === 'success' ? 'success' : 
                            type === 'info' ? 'info' : 'info';
    
    // Use SiteManager toast notifications
    SiteManager.notifications.toast(message, notificationType);
}

// Global functions
window.showImagePreview = showImagePreview;
window.printPost = printPost;
window.showMessage = showMessage;
