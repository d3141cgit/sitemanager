/**
 * Board Show JavaScript
 * Handles post detail view functionality including image preview
 */

document.addEventListener('DOMContentLoaded', function() {
    // Image preview modal functionality
    initializeImagePreview();
});

/**
 * Initialize image preview modal
 */
function initializeImagePreview() {
    // Create modal if it doesn't exist
    let modal = document.getElementById('imagePreviewModal');
    if (!modal) {
        modal = createImagePreviewModal();
    }

    // Add click handlers to all images in content
    const contentImages = document.querySelectorAll('.post-content img, .comment-content img');
    contentImages.forEach(img => {
        img.style.cursor = 'pointer';
        img.addEventListener('click', function() {
            showImagePreview(this.src, this.alt || 'Image');
        });
    });
}

/**
 * Create image preview modal
 */
function createImagePreviewModal() {
    const modal = document.createElement('div');
    modal.id = 'imagePreviewModal';
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Image Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="previewImage" class="img-fluid" src="" alt="">
                </div>
                <div class="modal-footer">
                    <a id="downloadImage" class="btn btn-primary" href="" download>
                        <i class="bi bi-download"></i> Download
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    return modal;
}

/**
 * Show image in preview modal
 */
function showImagePreview(src, alt) {
    const modal = document.getElementById('imagePreviewModal');
    const previewImage = document.getElementById('previewImage');
    const downloadLink = document.getElementById('downloadImage');
    
    if (modal && previewImage) {
        previewImage.src = src;
        previewImage.alt = alt;
        
        if (downloadLink) {
            downloadLink.href = src;
            downloadLink.download = alt || 'image';
        }
        
        // Show modal (Bootstrap 5)
        if (typeof bootstrap !== 'undefined') {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        } else {
            // Fallback for older Bootstrap or jQuery
            if (typeof $ !== 'undefined') {
                $(modal).modal('show');
            }
        }
    }
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

// Global functions
window.showImagePreview = showImagePreview;
window.printPost = printPost;
