/**
 * Board Form JavaScript
 * Handles slug generation, excerpt generation, auto-save, and form validation
 */

document.addEventListener('DOMContentLoaded', function() {
    // DOM elements
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    const slugValue = document.getElementById('slug-value');
    const slugFeedback = document.getElementById('slug-feedback');
    const generateSlugBtn = document.getElementById('generate-slug-btn');
    const checkSlugBtn = document.getElementById('check-slug-btn');
    
    const excerptInput = document.getElementById('excerpt');
    const excerptCount = document.getElementById('excerpt-count');
    const generateExcerptBtn = document.getElementById('generate-excerpt-btn');
    
    // Get board slug and post ID from data attributes or global variables
    const boardForm = document.querySelector('form[data-board-slug]');
    const boardSlug = boardForm?.dataset.boardSlug || window.boardSlug;
    const postId = boardForm?.dataset.postId || window.postId || null;
    
    // Character counter for title
    if (titleInput) {
        titleInput.addEventListener('input', function() {
            const maxLength = 200;
            const currentLength = this.value.length;
            const remaining = maxLength - currentLength;
            
            let counter = document.getElementById('title-counter');
            if (!counter) {
                counter = document.createElement('div');
                counter.id = 'title-counter';
                counter.className = 'form-text';
                this.parentNode.appendChild(counter);
            }
            
            counter.textContent = `${currentLength}/${maxLength} characters`;
            counter.className = remaining < 20 ? 'form-text text-warning' : 'form-text text-muted';
        });
    }
    
    // Update slug preview
    function updateSlugPreview() {
        if (slugInput && slugValue) {
            const slugVal = slugInput.value || 'your-slug';
            slugValue.textContent = slugVal;
        }
    }
    
    // Update excerpt character count
    function updateExcerptCount() {
        if (excerptInput && excerptCount) {
            const length = excerptInput.value.length;
            excerptCount.textContent = length;
            excerptCount.parentElement.className = length > 1000 ? 'form-text text-danger' : 'form-text';
        }
    }
    
    // Generate slug from title
    function generateSlugFromTitle() {
        if (!titleInput || !boardSlug) return;
        
        const title = titleInput.value.trim();
        if (!title) {
            alert('Please enter a title first');
            return;
        }
        
        if (generateSlugBtn) {
            generateSlugBtn.disabled = true;
            generateSlugBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating...';
        }
        
        fetch(`/board/${boardSlug}/generate-slug`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ title: title })
        })
        .then(response => response.json())
        .then(data => {
            if (data.slug && slugInput) {
                slugInput.value = data.slug;
                updateSlugPreview();
                checkSlugAvailability(); // Auto-check after generation
            }
        })
        .catch(error => {
            console.error('Error generating slug:', error);
            alert('Error generating slug. Please try again.');
        })
        .finally(() => {
            if (generateSlugBtn) {
                generateSlugBtn.disabled = false;
                generateSlugBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Generate';
            }
        });
    }
    
    // Check slug availability
    function checkSlugAvailability() {
        if (!slugInput || !slugFeedback || !boardSlug) return;
        
        const slug = slugInput.value.trim();
        if (!slug) {
            slugFeedback.innerHTML = '';
            return;
        }
        
        if (checkSlugBtn) {
            checkSlugBtn.disabled = true;
            checkSlugBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Checking...';
        }
        slugFeedback.innerHTML = '<small class="text-muted">Checking availability...</small>';
        
        fetch(`/board/${boardSlug}/check-slug`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ 
                slug: slug,
                post_id: postId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.available) {
                slugFeedback.innerHTML = '<small class="text-success"><i class="bi bi-check-circle"></i> ' + data.message + '</small>';
                slugInput.classList.remove('is-invalid');
                slugInput.classList.add('is-valid');
            } else {
                slugFeedback.innerHTML = `
                    <small class="text-danger"><i class="bi bi-x-circle"></i> ${data.message}</small>
                    ${data.suggested_slug ? `<br><small class="text-muted">Suggestion: <a href="#" onclick="useSlug('${data.suggested_slug}')">${data.suggested_slug}</a></small>` : ''}
                `;
                slugInput.classList.remove('is-valid');
                slugInput.classList.add('is-invalid');
            }
        })
        .catch(error => {
            console.error('Error checking slug:', error);
            slugFeedback.innerHTML = '<small class="text-danger">Error checking availability</small>';
        })
        .finally(() => {
            if (checkSlugBtn) {
                checkSlugBtn.disabled = false;
                checkSlugBtn.innerHTML = '<i class="bi bi-check-circle"></i> Check';
            }
        });
    }
    
    // Use suggested slug (global function for onclick)
    window.useSlug = function(slug) {
        if (slugInput) {
            slugInput.value = slug;
            updateSlugPreview();
            checkSlugAvailability();
        }
    };
    
    // Generate excerpt from content
    function generateExcerptFromContent() {
        let content = '';
        let textContent = '';
        
        // Try multiple methods to get content from Summernote editor
        
        // Method 1: Try .note-editable elements (most reliable for Summernote)
        const editableElements = document.querySelectorAll('.note-editable');
        if (editableElements.length > 0) {
            content = editableElements[0].innerHTML || '';
        }
        
        // Method 2: Try jQuery if available and no content found yet
        if (!content && typeof $ !== 'undefined') {
            if ($('#content').data('summernote')) {
                try {
                    content = $('#content').summernote('code') || '';
                } catch (e) {
                    // Fallback to textarea value
                    content = $('#content').val() || '';
                }
            } else if ($('.note-editable').length > 0) {
                content = $('.note-editable').html() || '';
            }
        }
        
        // Method 3: Try textarea by name as fallback
        if (!content) {
            const contentTextarea = document.querySelector('textarea[name="content"]');
            if (contentTextarea && contentTextarea.value) {
                content = contentTextarea.value;
            }
        }
        
        // Extract text content from HTML
        if (content) {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = content;
            textContent = tempDiv.textContent || tempDiv.innerText || '';
        }
        
        // Ensure content is a string
        content = String(content || '');
        textContent = String(textContent || '');
        
        if (!textContent || textContent.trim() === '') {
            alert('Please write some content first');
            return;
        }
        
        if (generateExcerptBtn) {
            generateExcerptBtn.disabled = true;
            generateExcerptBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
        }
        
        fetch('/board/generate-excerpt', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ 
                content: content,
                length: 200
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.excerpt && excerptInput) {
                excerptInput.value = data.excerpt;
                updateExcerptCount();
            }
        })
        .catch(error => {
            console.error('Error generating excerpt:', error);
            alert('Error generating excerpt. Please try again.');
        })
        .finally(() => {
            if (generateExcerptBtn) {
                generateExcerptBtn.disabled = false;
                generateExcerptBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
            }
        });
    }
    
    // Event listeners
    if (slugInput) {
        slugInput.addEventListener('input', updateSlugPreview);
        updateSlugPreview();
    }
    if (excerptInput) {
        excerptInput.addEventListener('input', updateExcerptCount);
        updateExcerptCount();
    }
    if (generateSlugBtn) generateSlugBtn.addEventListener('click', generateSlugFromTitle);
    if (checkSlugBtn) checkSlugBtn.addEventListener('click', checkSlugAvailability);
    if (generateExcerptBtn) generateExcerptBtn.addEventListener('click', generateExcerptFromContent);
    
    // Auto-generate slug when title changes (optional)
    let titleTimeout;
    if (titleInput) {
        titleInput.addEventListener('input', function() {
            clearTimeout(titleTimeout);
            titleTimeout = setTimeout(() => {
                if (titleInput.value.trim() && slugInput && !slugInput.value.trim()) {
                    generateSlugFromTitle();
                }
            }, 1000); // Wait 1 second after user stops typing
        });
    }

    // Auto-save functionality (only for create mode)
    const isEditMode = boardForm?.dataset.editMode === 'true' || window.isEditMode;
    
    if (!isEditMode) {
        const form = boardForm;
        
        // Load saved data
        if (localStorage.getItem('post_title') && titleInput) {
            titleInput.value = localStorage.getItem('post_title');
        }
        if (localStorage.getItem('post_content')) {
            // Wait for Summernote to initialize before setting content
            setTimeout(() => {
                if (typeof $ !== 'undefined' && typeof $('#content').summernote !== 'undefined') {
                    $('#content').summernote('code', localStorage.getItem('post_content'));
                } else {
                    const contentInput = document.getElementById('content');
                    if (contentInput) {
                        contentInput.value = localStorage.getItem('post_content');
                    }
                }
            }, 1000);
        }
        
        // Save data on input
        if (titleInput) {
            titleInput.addEventListener('input', function() {
                localStorage.setItem('post_title', this.value);
            });
        }
        
        // Save content from Summernote
        function saveContent() {
            let content = '';
            
            if (typeof $ !== 'undefined' && $('#content').length > 0) {
                try {
                    if ($('#content').hasClass('note-editable') || $('#content').data('summernote')) {
                        content = $('#content').summernote('code');
                    } else {
                        content = $('#content').val() || '';
                    }
                } catch (e) {
                    content = $('#content').val() || '';
                }
            } else {
                const contentInput = document.getElementById('content');
                if (contentInput) {
                    content = contentInput.value || '';
                }
            }
            
            // Ensure content is a string
            if (typeof content === 'object') {
                content = '';
            } else {
                content = String(content || '');
            }
            
            localStorage.setItem('post_content', content);
        }
        
        // Set up content saving (try multiple approaches)
        setTimeout(() => {
            if (typeof $ !== 'undefined' && typeof $('#content').summernote !== 'undefined') {
                $('#content').on('summernote.change', saveContent);
            }
            
            // Fallback for regular textarea
            const contentInput = document.getElementById('content');
            if (contentInput) {
                contentInput.addEventListener('input', saveContent);
            }
        }, 1000);
        
        // Clear saved data on form submit
        if (form) {
            form.addEventListener('submit', function() {
                localStorage.removeItem('post_title');
                localStorage.removeItem('post_content');
            });
        }
        
        // Warn before leaving with unsaved changes
        let hasUnsavedChanges = false;
        
        if (titleInput) {
            titleInput.addEventListener('input', () => hasUnsavedChanges = true);
        }
        
        // Monitor Summernote changes
        setTimeout(() => {
            if (typeof $ !== 'undefined' && typeof $('#content').summernote !== 'undefined') {
                $('#content').on('summernote.change', () => hasUnsavedChanges = true);
            }
            
            const contentInput = document.getElementById('content');
            if (contentInput) {
                contentInput.addEventListener('input', () => hasUnsavedChanges = true);
            }
        }, 1000);
        
        window.addEventListener('beforeunload', function(e) {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        if (form) {
            form.addEventListener('submit', () => hasUnsavedChanges = false);
        }
    }
});
