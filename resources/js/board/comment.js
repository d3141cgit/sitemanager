// Comment Management JavaScript Functions

// 댓글 시스템 네임스페이스 (중복 방지)
window.CommentManager = window.CommentManager || {
    isSubmitting: false,
    selectedFiles: new Map() // 폼별로 선택된 파일들 관리 (formId -> FileList)
};

// Initialize comment form when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // 페이지 로드 시 제출 상태 초기화
    window.CommentManager.isSubmitting = false;
    
    // 모든 폼의 제출 상태 초기화 (브라우저 새로고침 시 자동 제출 방지)
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        // 폼 제출 이벤트 기본 동작 방지 (페이지 새로고침 시)
        form.addEventListener('submit', function(e) {
            if (window.CommentManager.isSubmitting) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    initializeCommentForm();
    
    // Initialize pagination events if comments exist
    initializePaginationEvents();
});

// 페이지 언로드 시 제출 상태 리셋 (브라우저 새로고침/뒤로가기 대응)
window.addEventListener('beforeunload', function() {
    window.CommentManager.isSubmitting = false;
});

// 페이지 포커스 시 제출 상태 리셋 (탭 전환 후 돌아올 때)
window.addEventListener('focus', function() {
    window.CommentManager.isSubmitting = false;
});

function initializeCommentForm() {
    const commentForm = document.getElementById('commentForm');
    
    // 댓글 첨부파일 이미지 클릭 이벤트 (중복 방지)
    if (!window.commentImageEventInitialized) {
        window.commentImageEventInitialized = true;
        
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('comment-attachment-image')) {
                e.preventDefault();
                e.stopPropagation();
                
                const imageUrl = e.target.dataset.imageUrl;
                const imageName = e.target.dataset.imageName;
                const downloadUrl = e.target.dataset.downloadUrl;
                
                if (window.SiteManager && window.SiteManager.modals) {
                    SiteManager.modals.showImagePreview(imageUrl, imageName, downloadUrl);
                } else if (window.showImageModal) {
                    // 폴백: 기존 showImageModal 함수 사용
                    showImageModal(imageUrl, imageName, downloadUrl);
                } else {
                    // 최종 폴백: 새 창으로 열기
                    window.open(imageUrl, '_blank');
                }
            }
        });
    }
    
    if (commentForm) {
        // 메인 댓글 폼의 파일 업로드 미리보기 이벤트 추가
        const commentFileInput = document.getElementById('comment-files');
        const commentFilePreview = commentForm.querySelector('.comment-file-preview');
        
        if (commentFileInput && commentFilePreview) {
            commentFileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    // 새로 선택된 파일들을 기존 선택에 추가
                    const formId = 'main-comment-form';
                    const allFiles = addFilesToSelection(formId, Array.from(this.files));
                    
                    // 원래 파일 input은 빈 상태로 리셋 (hidden input들이 실제 데이터를 관리)
                    this.value = '';
                    
                    // 미리보기 업데이트 (hidden input들이 자동 생성됨)
                    displayFilePreview(allFiles, commentFilePreview, formId);
                    
                    // Add Files 버튼 텍스트 업데이트
                    const addFilesBtn = document.querySelector('button[onclick*="comment-files"]');
                    if (addFilesBtn) {
                        addFilesBtn.innerHTML = `<i class="bi bi-paperclip"></i> ${allFiles.length} file(s) selected`;
                        addFilesBtn.className = 'btn btn-sm btn-outline-primary';
                    }
                }
            });
        }
        
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // 중복 제출 방지
            if (window.CommentManager.isSubmitting) {
                return;
            }
            
            const formData = new FormData(this);
            const content = formData.get('content');
            const postId = formData.get('post_id');
            
            // 실제 파일 input 상태 확인
            const fileInput = this.querySelector('input[type="file"]');
            if (fileInput) {
                const fileNames = Array.from(fileInput.files).map(f => f.name);

                // FormData에 포함된 파일들도 확인
                const formDataFiles = formData.getAll('files[]');
            }
            
            if (!content.trim()) {
                SiteManager.notifications.warning('Please write a comment.');
                return;
            }
            
            // 제출 상태 설정
            window.CommentManager.isSubmitting = true;
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Posting...';
            submitBtn.disabled = true;
            
            // Get route from global variables
            const storeUrl = window.commentRoutes?.store;
            if (!storeUrl) {
                SiteManager.notifications.error('Configuration error [No route]. Please refresh the page.');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                return;
            }
            
            // Submit comment
            fetch(storeUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: formData // FormData 객체를 직접 전송 (Content-Type은 자동으로 multipart/form-data로 설정됨)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear form
                    this.reset();
                    
                    // Clear file preview
                    if (commentFilePreview) {
                        commentFilePreview.innerHTML = '';
                    }
                    
                    // Clear file selection
                    clearFileSelection('main-comment-form');
                    
                    // Reset Add Files button
                    const addFilesBtn = document.querySelector('button[onclick*="comment-files"]');
                    if (addFilesBtn) {
                        addFilesBtn.innerHTML = '<i class="bi bi-paperclip"></i> Add Files';
                        addFilesBtn.className = 'btn btn-sm btn-outline-secondary';
                    }
                    
                    // Show success message
                    SiteManager.notifications.toast(data.message, 'success');
                    
                    // 페이지 새로고침 시 폼 재제출 방지를 위해 히스토리 상태 변경
                    if (window.history.replaceState) {
                        window.history.replaceState({}, document.title, window.location.pathname + window.location.search);
                    }
                    
                    // Remove no comments message if exists
                    const noComments = document.getElementById('no-comments');
                    if (noComments) {
                        noComments.remove();
                    }
                    
                    // Reload comments with pagination (새 댓글이 첫 페이지에 나타나도록)
                    loadComments(1);
                    
                    // Update comment count if provided
                    if (data.comment_count !== undefined) {
                        updateCommentCount(data.comment_count);
                    }
                } else {
                    SiteManager.notifications.error(data.message || 'An error occurred while posting your comment.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                SiteManager.notifications.error('An error occurred while posting your comment.');
            })
            .finally(() => {
                // 제출 상태 해제
                window.CommentManager.isSubmitting = false;
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
}

// 선택된 파일들을 누적 관리하는 함수들
function addFilesToSelection(formId, newFiles) {
    if (!window.CommentManager.selectedFiles.has(formId)) {
        window.CommentManager.selectedFiles.set(formId, []);
    }
    
    const currentFiles = window.CommentManager.selectedFiles.get(formId);
    
    // 새로운 파일들을 기존 목록에 추가 (중복 파일명 체크)
    for (const newFile of newFiles) {
        const isDuplicate = currentFiles.some(existingFile => 
            existingFile.name === newFile.name && existingFile.size === newFile.size
        );
        
        if (!isDuplicate) {
            currentFiles.push(newFile);
        }
    }
    
    window.CommentManager.selectedFiles.set(formId, currentFiles);
    return currentFiles;
}

function removeFileFromSelection(formId, fileIndex) {
    console.log('DEBUG - removeFileFromSelection:', { formId, fileIndex });
    
    if (!window.CommentManager.selectedFiles.has(formId)) {
        console.log('DEBUG - no files found for formId:', formId);
        return [];
    }
    
    const currentFiles = window.CommentManager.selectedFiles.get(formId);
    console.log('DEBUG - currentFiles before removal:', currentFiles.length);
    
    currentFiles.splice(fileIndex, 1);
    window.CommentManager.selectedFiles.set(formId, currentFiles);
    
    console.log('DEBUG - currentFiles after removal:', currentFiles.length);
    return currentFiles;
}

function clearFileSelection(formId) {
    window.CommentManager.selectedFiles.delete(formId);
}

function getSelectedFiles(formId) {
    return window.CommentManager.selectedFiles.get(formId) || [];
}

// 파일 배열을 FileList로 변환하는 함수
function createFileList(filesArray) {
    const dt = new DataTransfer();
    filesArray.forEach(file => dt.items.add(file));
    return dt.files;
}

// 새로 추가된 댓글에 이벤트 리스너 연결
function initializeNewCommentEvents(commentId) {
    const commentElement = document.querySelector(`[data-comment-id="${commentId}"]`);
    if (!commentElement) return;
    
    // 편집 폼의 파일 업로드 기능 초기화
    const editForm = commentElement.querySelector(`#edit-form-${commentId}`);
    if (editForm) {
        const fileInput = editForm.querySelector('input[type="file"]');
        const filePreview = editForm.querySelector('.comment-file-preview');
        
        if (fileInput && filePreview) {
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    // 새로 선택된 파일들을 기존 선택에 추가
                    const formId = `edit-comment-${commentId}`;
                    const allFiles = addFilesToSelection(formId, Array.from(this.files));
                    
                    // 파일 input을 업데이트된 파일 목록으로 설정
                    this.files = createFileList(allFiles);
                    
                    // 미리보기 업데이트
                    displayFilePreview(allFiles, filePreview, formId);
                    
                    // Add Files 버튼 텍스트 업데이트
                    updateAddFilesButton(formId, allFiles.length);
                }
            });
        }
        
        // 삭제 예정 첨부파일 추적을 위한 배열 초기화
        if (!editForm.dataset.deletedAttachments) {
            editForm.dataset.deletedAttachments = JSON.stringify([]);
        }
        
        // 누적 파일 선택 초기화
        const formId = `edit-comment-${commentId}`;
        clearFileSelection(formId);
    }
}

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
    // Hide other edit forms
    document.querySelectorAll('.edit-form').forEach(form => {
        form.remove();
    });
    
    // Hide other reply forms
    document.querySelectorAll('.reply-form').forEach(form => {
        form.remove();
    });
    
    // Check if edit form already exists
    const existingForm = document.getElementById(`edit-form-${commentId}`);
    if (existingForm) {
        // Show content and hide edit form
        const contentElement = document.querySelector(`[data-comment-id="${commentId}"] .comment-content`);
        if (contentElement) contentElement.style.display = 'block';
        existingForm.remove();
        return;
    }
    
    // Hide content
    const contentElement = document.querySelector(`[data-comment-id="${commentId}"] .comment-content`);
    if (contentElement) contentElement.style.display = 'none';
    
    // Load edit form via AJAX
    const container = document.getElementById(`edit-form-container-${commentId}`);
    if (container) {
        const url = window.commentRoutes.editForm.replace(':id', commentId);
        
        fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(html => {
            container.innerHTML = html;
            
            // Initialize file upload functionality
            initializeEditFormFileUpload(commentId);
        })
        .catch(error => {
            console.error('Error loading edit form:', error);
            // Show content back if error
            if (contentElement) contentElement.style.display = 'block';
            if (window.SiteManager && window.SiteManager.notifications) {
                SiteManager.notifications.error('편집 폼을 불러오는 중 오류가 발생했습니다.');
            } else {
                alert('편집 폼을 불러오는 중 오류가 발생했습니다.');
            }
        });
    }
}

// 댓글 수정 폼의 파일 업로드 기능 초기화
function initializeEditFormFileUpload(commentId) {
    const editForm = document.getElementById(`edit-form-${commentId}`);
    if (!editForm) return;
    
    const fileInput = editForm.querySelector('input[type="file"]');
    const filePreview = editForm.querySelector('.comment-file-preview');
    
    if (fileInput && filePreview) {
        // 기존 이벤트 리스너 제거 (중복 방지)
        const newFileInput = fileInput.cloneNode(true);
        fileInput.parentNode.replaceChild(newFileInput, fileInput);
        
        newFileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                // 새로 선택된 파일들을 기존 선택에 추가
                const formId = `edit-comment-${commentId}`;
                const allFiles = addFilesToSelection(formId, Array.from(this.files));
                
                // 원래 파일 input은 빈 상태로 리셋 (hidden input들이 실제 데이터를 관리)
                this.value = '';
                
                // 미리보기 업데이트 (hidden input들이 자동 생성됨)
                displayFilePreview(allFiles, filePreview, formId);
                
                // Add Files 버튼 텍스트 업데이트
                const addFilesBtn = editForm.querySelector(`button[onclick*="file-input-${commentId}"]`);
                if (addFilesBtn) {
                    addFilesBtn.innerHTML = `<i class="bi bi-paperclip"></i> ${allFiles.length} file(s) selected`;
                    addFilesBtn.className = 'btn btn-sm btn-outline-primary';
                }
            }
        });
    }
    
    // 삭제 예정 첨부파일 추적을 위한 배열 초기화
    if (!editForm.dataset.deletedAttachments) {
        editForm.dataset.deletedAttachments = JSON.stringify([]);
    }
}

// 댓글 reply 폼의 파일 업로드 기능 초기화
function initializeReplyFormFileUpload(commentId) {
    const replyForm = document.getElementById(`reply-form-${commentId}`);
    if (!replyForm) return;
    
    const fileInput = replyForm.querySelector('input[type="file"]');
    const filePreview = replyForm.querySelector('.comment-file-preview');
    
    if (fileInput && filePreview) {
        // 기존 이벤트 리스너 제거 (중복 방지)
        const newFileInput = fileInput.cloneNode(true);
        fileInput.parentNode.replaceChild(newFileInput, fileInput);
        
        newFileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                // 새로 선택된 파일들을 기존 선택에 추가
                const formId = `reply-comment-${commentId}`;
                const allFiles = addFilesToSelection(formId, Array.from(this.files));
                
                // 원래 파일 input은 빈 상태로 리셋 (hidden input들이 실제 데이터를 관리)
                this.value = '';
                
                // 미리보기 업데이트 (hidden input들이 자동 생성됨)
                displayFilePreview(allFiles, filePreview, formId);
                
                // Add Files 버튼 텍스트 업데이트
                const addFilesBtn = replyForm.querySelector(`button[onclick*="file-reply-${commentId}"]`);
                if (addFilesBtn) {
                    addFilesBtn.innerHTML = `<i class="bi bi-paperclip"></i> ${allFiles.length} file(s) selected`;
                    addFilesBtn.className = 'btn btn-sm btn-outline-primary';
                }
            }
        });
    }
}

// 기존 첨부파일 삭제 (UI에서만 제거, 실제 삭제는 submit 시 처리)
function removeExistingAttachment(commentId, attachmentId) {
    SiteManager.notifications.confirmDelete('this attachment').then((confirmed) => {
        if (!confirmed) return;
        
        const editForm = document.getElementById(`edit-form-${commentId}`);
        if (!editForm) {
            console.error('Edit form not found for comment:', commentId);
            return;
        }
    
        // 삭제 예정 목록에 추가
        let deletedAttachments = JSON.parse(editForm.dataset.deletedAttachments || '[]');
        // attachmentId를 정수로 변환하여 일관성 유지
        const attachmentIdInt = parseInt(attachmentId);
        if (!deletedAttachments.includes(attachmentIdInt)) {
            deletedAttachments.push(attachmentIdInt);
            editForm.dataset.deletedAttachments = JSON.stringify(deletedAttachments);
        }
        
        console.log('DEBUG - removeExistingAttachment:', {
            commentId,
            attachmentId,
            attachmentIdInt,
            deletedAttachments,
            formDataset: editForm.dataset.deletedAttachments
        });
        
        // UI에서 첨부파일 제거 (시각적으로만)
        const attachmentElement = document.querySelector(`[data-attachment-id="${attachmentId}"]`);
        if (attachmentElement) {
            attachmentElement.style.opacity = '0.5';
            attachmentElement.style.textDecoration = 'line-through';
            
            // 삭제 버튼을 복원 버튼으로 변경
            const deleteBtn = attachmentElement.querySelector('button');
            if (deleteBtn) {
                deleteBtn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i>';
                deleteBtn.className = 'btn btn-sm btn-outline-success';
                deleteBtn.onclick = () => restoreExistingAttachment(commentId, attachmentId);
                deleteBtn.title = 'Restore attachment';
            }
        }
    });
}

// 기존 첨부파일 복원 (삭제 예정에서 제거)
function restoreExistingAttachment(commentId, attachmentId) {
    const editForm = document.getElementById(`edit-form-${commentId}`);
    if (!editForm) return;
    
    // 삭제 예정 목록에서 제거
    let deletedAttachments = JSON.parse(editForm.dataset.deletedAttachments || '[]');
    const attachmentIdInt = parseInt(attachmentId);
    deletedAttachments = deletedAttachments.filter(id => id !== attachmentIdInt);
    editForm.dataset.deletedAttachments = JSON.stringify(deletedAttachments);
    
    // UI 원복
    const attachmentElement = document.querySelector(`[data-attachment-id="${attachmentId}"]`);
    if (attachmentElement) {
        attachmentElement.style.opacity = '1';
        attachmentElement.style.textDecoration = 'none';
        
        // 복원 버튼을 삭제 버튼으로 변경
        const restoreBtn = attachmentElement.querySelector('button');
        if (restoreBtn) {
            restoreBtn.innerHTML = '<i class="bi bi-x"></i>';
            restoreBtn.className = 'btn btn-sm btn-outline-danger';
            restoreBtn.onclick = () => removeExistingAttachment(commentId, attachmentId);
            restoreBtn.title = 'Remove attachment';
        }
    }
    
    console.log('Restored attachment:', { commentId, attachmentId, deletedAttachments });
}

// 파일 미리보기 표시 (간소화 버전)
function displayFilePreview(files, previewContainer, formId = null) {
    previewContainer.innerHTML = '';
    
    if (files.length === 0) {
        return;
    }
    
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const fileItem = document.createElement('div');
        fileItem.className = 'file-preview-item';
        
        const fileIcon = getFileIcon(file.type);
        
        // 각 파일마다 개별 hidden input 생성
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'file';
        hiddenInput.style.display = 'none';
        hiddenInput.name = 'files[]';
        hiddenInput.className = `file-input-${formId || 'default'}-${i}`;
        
        // DataTransfer를 사용해서 파일을 hidden input에 설정
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        hiddenInput.files = dataTransfer.files;
        
        // 간단한 이미지 미리보기 (작은 썸네일만)
        let imagePreview = '';
        if (file.type.startsWith('image/')) {
            const imageUrl = URL.createObjectURL(file);
            imagePreview = `
                <img src="${imageUrl}" alt="Preview">
            `;
        } else {
            imagePreview = `<i class="${fileIcon} me-2"></i>`;
        }
        
        fileItem.innerHTML = `
            ${imagePreview}
            <span class="text-truncate" style="max-width: 120px;" title="${file.name}">${file.name}</span>
            <button type="button" class="btn btn-sm ms-1" style="padding: 0 4px;" onclick="removeFilePreview(this, ${i}, ${formId ? `'${formId}'` : 'null'})">
                <i class="bi bi-x" style="font-size: 12px;"></i>
            </button>
        `;
        
        // 프리뷰 컨테이너에 hidden input과 파일 아이템 추가
        previewContainer.appendChild(hiddenInput);
        previewContainer.appendChild(fileItem);
    }
}

// 파일 미리보기에서 파일 제거 (간소화된 버전)
function removeFilePreview(button, fileIndex, formId = null) {
    console.log('DEBUG - removeFilePreview:', { fileIndex, formId, formIdType: typeof formId });
    console.log('DEBUG - button element:', button);
    console.log('DEBUG - button.className:', button?.className);
    console.log('DEBUG - button.parentElement:', button?.parentElement);
    
    // formId가 문자열 'null'인 경우 null로 변환
    if (formId === 'null' || formId === '') {
        formId = null;
    }
    
    console.log('DEBUG - after formId normalization:', { formId, formIdType: typeof formId });
    
    // 다양한 방법으로 fileItem 찾기
    let fileItem = button.closest('.file-preview-item');
    if (!fileItem) {
        fileItem = button.closest('.file-item');
    }
    if (!fileItem) {
        fileItem = button.parentElement;
    }
    
    console.log('DEBUG - fileItem found:', !!fileItem);
    console.log('DEBUG - fileItem className:', fileItem?.className);
    
    // 다양한 방법으로 previewContainer 찾기 (클래스 우선)
    let previewContainer = button.closest('.comment-file-preview');
    if (!previewContainer) {
        // 폴백: 메인 댓글 폼의 경우
        previewContainer = document.querySelector('.comment-file-preview');
    }
    
    console.log('DEBUG - previewContainer found:', !!previewContainer);
    console.log('DEBUG - previewContainer id:', previewContainer?.id);
    console.log('DEBUG - previewContainer className:', previewContainer?.className);
    
    if (fileItem && previewContainer) {
        // DOM 요소를 제거하기 전에 모든 작업을 먼저 수행
        
        // 1. 해당 파일의 hidden input 찾아서 제거
        const hiddenInputClass = `file-input-${formId || 'default'}-${fileIndex}`;
        const hiddenInput = previewContainer.querySelector(`.${hiddenInputClass}`);
        
        console.log('DEBUG - hiddenInput found:', !!hiddenInput, 'class:', hiddenInputClass);
        
        if (hiddenInput) {
            hiddenInput.remove();
            console.log('DEBUG - hiddenInput removed successfully');
        } else {
            console.log('DEBUG - hiddenInput not found, trying fallback method');
            // 기존 방식 (하위 호환성)
            const fileInput = previewContainer.parentElement?.querySelector('input[type="file"]');
            if (fileInput && fileInput.files.length > 0) {
                const dt = new DataTransfer();
                const files = Array.from(fileInput.files);
                
                files.forEach((file, index) => {
                    if (index !== fileIndex) {
                        dt.items.add(file);
                    }
                });
                
                fileInput.files = dt.files;
                console.log('DEBUG - fallback method applied, remaining files:', fileInput.files.length);
            }
        }
        
        // 2. selectedFiles Map에서도 제거
        removeFileFromSelection(formId, fileIndex);
        
        // 3. Add Files 버튼 상태 업데이트
        updateAddFilesButton(formId, window.CommentManager.selectedFiles.get(formId || 'default')?.length || 0);
        
        // 4. 마지막에 DOM 요소 제거
        fileItem.remove();
        console.log('DEBUG - fileItem removed successfully');
        
        // 5. 기존 방식인 경우 미리보기 다시 생성
        if (!hiddenInput && previewContainer.parentElement) {
            const fileInput = previewContainer.parentElement.querySelector('input[type="file"]');
            if (fileInput) {
                displayFilePreview(fileInput.files, previewContainer, null);
            }
        }
        
        console.log('DEBUG - removeFilePreview completed for:', { fileIndex, formId });
    } else {
        console.log('DEBUG - fileItem or previewContainer not found');
        console.log('DEBUG - Available elements in DOM:');
        console.log('DEBUG - .file-preview-item elements:', document.querySelectorAll('.file-preview-item').length);
        console.log('DEBUG - .comment-file-preview elements:', document.querySelectorAll('.comment-file-preview').length);
    }
}

// Add Files 버튼 상태 업데이트 함수
function updateAddFilesButton(formId, fileCount) {
    let addFilesBtn = null;
    
    if (formId === 'main-comment-form') {
        addFilesBtn = document.querySelector('button[onclick*="comment-files"]');
    } else if (formId.startsWith('edit-comment-')) {
        const commentId = formId.replace('edit-comment-', '');
        addFilesBtn = document.querySelector(`button[onclick*="file-input-${commentId}"]`);
    }
    
    if (addFilesBtn) {
        if (fileCount > 0) {
            addFilesBtn.innerHTML = `<i class="bi bi-paperclip"></i> ${fileCount} file(s) selected`;
            addFilesBtn.className = 'btn btn-sm btn-outline-primary';
        } else {
            addFilesBtn.innerHTML = '<i class="bi bi-paperclip"></i> Add Files';
            addFilesBtn.className = 'btn btn-sm btn-outline-secondary';
        }
    }
}

// 파일 타입에 따른 아이콘 반환
function getFileIcon(fileType) {
    if (fileType.startsWith('image/')) return 'bi bi-image';
    if (fileType.startsWith('video/')) return 'bi bi-play-circle';
    if (fileType.startsWith('audio/')) return 'bi bi-music-note';
    if (fileType.includes('pdf')) return 'bi bi-file-pdf';
    if (fileType.includes('word') || fileType.includes('document')) return 'bi bi-file-word';
    if (fileType.includes('excel') || fileType.includes('spreadsheet')) return 'bi bi-file-excel';
    if (fileType.includes('powerpoint') || fileType.includes('presentation')) return 'bi bi-file-ppt';
    return 'bi bi-file-earmark';
}

// 파일 크기 포맷팅
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function cancelEdit(commentId) {
    // Show content and hide edit form
    const contentElement = document.querySelector(`[data-comment-id="${commentId}"] .comment-content`);
    const editForm = document.getElementById(`edit-form-${commentId}`);
    
    if (contentElement) contentElement.style.display = 'block';
    if (editForm) {
        editForm.style.display = 'none';
        
        // 삭제 예정 목록 초기화 및 UI 복원
        editForm.dataset.deletedAttachments = JSON.stringify([]);
        
        // 모든 첨부파일 UI 복원
        editForm.querySelectorAll('[data-attachment-id]').forEach(attachmentElement => {
            attachmentElement.style.opacity = '1';
            attachmentElement.style.textDecoration = 'none';
            
            const button = attachmentElement.querySelector('button');
            if (button) {
                button.innerHTML = '<i class="bi bi-x"></i>';
                button.className = 'btn btn-sm btn-outline-danger';
                const attachmentId = parseInt(attachmentElement.dataset.attachmentId);
                button.onclick = () => removeExistingAttachment(commentId, attachmentId);
                button.title = 'Remove attachment';
            }
        });
        
        // 새 파일 input 및 preview 초기화
        const fileInput = editForm.querySelector('input[type="file"]');
        const filePreview = editForm.querySelector('.comment-file-preview');
        if (fileInput) fileInput.value = '';
        if (filePreview) filePreview.innerHTML = '';
        
        // 누적 파일 선택 초기화
        const formId = `edit-comment-${commentId}`;
        clearFileSelection(formId);
        updateAddFilesButton(formId, 0);
    }
}

function submitEdit(event, commentId) {
    event.preventDefault();
    
    // 중복 제출 방지
    if (window.CommentManager.isSubmitting) {
        return;
    }
    
    // Form을 정확히 찾기 (event.target이 아닌 commentId로 직접 찾기)
    const form = document.getElementById(`edit-form-${commentId}`).querySelector('form');
    if (!form) {
        console.error('Form not found for comment:', commentId);
        return;
    }
    
    // 원래 파일 input을 비워서 중복 업로드 방지
    const originalFileInput = form.querySelector('input[type="file"]:not([style*="display: none"])');
    if (originalFileInput) {
        originalFileInput.value = '';
        console.log('DEBUG - Original file input cleared to prevent duplicates');
    }

    const formData = new FormData(form);
    const content = formData.get('content');    if (!content.trim()) {
        SiteManager.notifications.warning('Please write a comment.');
        return;
    }
    
    // 삭제 예정 첨부파일 정보 추가 (form의 부모 div에서 데이터셋 가져오기)
    const editFormContainer = document.getElementById(`edit-form-${commentId}`);
    const deletedAttachments = JSON.parse(editFormContainer.dataset.deletedAttachments || '[]');
    if (deletedAttachments.length > 0) {
        formData.append('deleted_attachments', JSON.stringify(deletedAttachments));
    }
    
    // _method 필드 명시적 추가 (PUT 요청 시뮬레이션)
    formData.append('_method', 'PUT');
    
    // 파일 상태 디버깅
    const fileInput = form.querySelector('input[type="file"]');
    const hiddenFileInputs = form.querySelectorAll('input[type="file"][style*="display: none"]');
    console.log('DEBUG - Edit form file status:', {
        originalFileInput: {
            found: !!fileInput,
            filesCount: fileInput?.files.length || 0,
            fileNames: fileInput ? Array.from(fileInput.files).map(f => f.name) : []
        },
        hiddenFileInputs: {
            count: hiddenFileInputs.length,
            totalFiles: Array.from(hiddenFileInputs).reduce((total, input) => total + input.files.length, 0)
        },
        formDataFiles: formData.getAll('files[]').length
    });
    
    console.log('DEBUG - submitEdit (FIXED):', {
        commentId,
        deletedAttachments,
        editFormDataset: editFormContainer.dataset.deletedAttachments,
        hasDeletedAttachments: deletedAttachments.length > 0
    });
    
    // FormData 내용 확인
    console.log('FormData contents:');
    for (let [key, value] of formData.entries()) {
        console.log(key, value);
    }
    
    // 제출 상태 설정
    window.CommentManager.isSubmitting = true;
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';
    submitBtn.disabled = true;
    
    // Get route from data attributes or global variables
    const updateUrl = window.commentRoutes?.update?.replace(':id', commentId);
    if (!updateUrl) {
        console.error('Comment update route not found');
        SiteManager.notifications.error('Configuration error. Please refresh the page.');
        return;
    }
    
    fetch(updateUrl, {
        method: 'POST', // PUT 대신 POST 사용 (Laravel에서 FormData와 함께 _method 사용)
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: formData // FormData 객체를 직접 전송 (Content-Type은 자동으로 multipart/form-data로 설정됨)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update comment content with HTML rendering
            const contentElement = document.querySelector(`[data-comment-id="${commentId}"] .comment-content`);
            if (contentElement) {
                contentElement.innerHTML = content.replace(/\n/g, '<br>');
            }
            
            // 첨부파일 업데이트 (새로운 HTML이 제공된 경우)
            if (data.attachments_html) {
                const editFormContainer = document.getElementById(`edit-form-${commentId}`);
                const existingAttachmentsContainer = editFormContainer.querySelector('.existing-attachments');
                if (existingAttachmentsContainer) {
                    existingAttachmentsContainer.innerHTML = data.attachments_html;
                }
                
                // 첨부파일 섹션 전체 표시/숨김 처리
                const attachmentsSection = editFormContainer.querySelector('.mb-2');
                if (attachmentsSection && attachmentsSection.querySelector('.form-label')) {
                    if (data.has_attachments) {
                        attachmentsSection.style.display = 'block';
                    } else {
                        attachmentsSection.style.display = 'none';
                    }
                }
            } else {
                // 첨부파일이 없는 경우 섹션 숨김
                const editFormContainer = document.getElementById(`edit-form-${commentId}`);
                const attachmentsSection = editFormContainer.querySelector('.mb-2');
                if (attachmentsSection && attachmentsSection.querySelector('.form-label')) {
                    attachmentsSection.style.display = 'none';
                }
            }
            
            // 댓글 본문의 첨부파일도 업데이트 (댓글 표시 영역)
            const commentElement = document.querySelector(`[data-comment-id="${commentId}"]`);
            if (commentElement && data.attachments_html) {
                const commentAttachmentsContainer = commentElement.querySelector('.comment-attachments');
                if (commentAttachmentsContainer) {
                    commentAttachmentsContainer.innerHTML = data.attachments_html;
                } else if (data.has_attachments) {
                    // 첨부파일 표시 영역이 없다면 생성
                    const contentElement = commentElement.querySelector('.comment-content');
                    if (contentElement) {
                        const attachmentsDiv = document.createElement('div');
                        attachmentsDiv.className = 'comment-attachments';
                        attachmentsDiv.innerHTML = data.attachments_html;
                        contentElement.after(attachmentsDiv);
                    }
                }
            }
            
            // 삭제 예정 목록 초기화
            const editFormContainer = document.getElementById(`edit-form-${commentId}`);
            editFormContainer.dataset.deletedAttachments = JSON.stringify([]);
            
            // 새 파일 input 초기화
            const fileInput = form.querySelector('input[type="file"]');
            const filePreview = form.querySelector('.comment-file-preview');
            if (fileInput) fileInput.value = '';
            if (filePreview) filePreview.innerHTML = '';
            
            // 누적 파일 선택 초기화
            const formId = `edit-comment-${commentId}`;
            clearFileSelection(formId);
            updateAddFilesButton(formId, 0);
            
            cancelEdit(commentId);
            SiteManager.notifications.toast('Comment updated successfully!', 'success');
            
            // 페이지 새로고침 시 폼 재제출 방지를 위해 히스토리 상태 변경
            if (window.history.replaceState) {
                window.history.replaceState({}, document.title, window.location.pathname + window.location.search);
            }
        } else {
            alert(data.message || 'An error occurred while updating the comment.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the comment.');
    })
    .finally(() => {
        // 제출 상태 해제
        window.CommentManager.isSubmitting = false;
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
        form.remove();
    });
    
    // Hide other edit forms
    document.querySelectorAll('.edit-form').forEach(form => {
        form.remove();
    });
    
    // Check if reply form already exists
    const existingForm = document.getElementById(`reply-form-${commentId}`);
    if (existingForm) {
        existingForm.remove();
        hideReplyLoading(commentId);
        return;
    }
    
    // Load reply form via AJAX
    const container = document.getElementById(`reply-form-container-${commentId}`);
    if (container) {
        showReplyLoading(commentId);
        
        const url = window.commentRoutes.replyForm.replace(':id', commentId);
        
        fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(html => {
            container.innerHTML = html;
            
            // Focus on the textarea
            const textarea = container.querySelector('textarea');
            if (textarea) {
                textarea.focus();
            }
            
            // Initialize file upload functionality
            initializeReplyFormFileUpload(commentId);
            
            hideReplyLoading(commentId);
        })
        .catch(error => {
            console.error('Error loading reply form:', error);
            hideReplyLoading(commentId);
            if (window.SiteManager && window.SiteManager.notifications) {
                SiteManager.notifications.error('댓글 폼을 불러오는 중 오류가 발생했습니다.');
            } else {
                alert('댓글 폼을 불러오는 중 오류가 발생했습니다.');
            }
        });
    }
}

function cancelReply(commentId) {
    const replyForm = document.getElementById(`reply-form-${commentId}`);
    if (replyForm) {
        replyForm.remove();
    }
    hideReplyLoading(commentId);
}

function cancelEdit(commentId) {
    const editForm = document.getElementById(`edit-form-${commentId}`);
    if (editForm) {
        editForm.remove();
    }
    
    // Show content back
    const contentElement = document.querySelector(`[data-comment-id="${commentId}"] .comment-content`);
    if (contentElement) {
        contentElement.style.display = 'block';
    }
}

function submitReply(event, parentId) {
    event.preventDefault();
    
    // 중복 제출 방지
    if (window.CommentManager.isSubmitting) {
        return;
    }
    
    const form = event.target;
    const formData = new FormData(form);
    const content = formData.get('content');
    const postId = formData.get('post_id');
    const parentCommentId = formData.get('parent_id');
    
    if (!content.trim()) {
        alert('Please write a reply.');
        return;
    }
    
    // 제출 상태 설정
    window.CommentManager.isSubmitting = true;
    
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
        },
        body: formData // FormData 객체를 직접 전송 (Content-Type은 자동으로 multipart/form-data로 설정됨)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            form.reset();
            cancelReply(parentId);
            SiteManager.notifications.toast(data.message, 'success');
            
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
        // 제출 상태 해제
        window.CommentManager.isSubmitting = false;
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
            SiteManager.notifications.toast(data.message, 'success');
        } else {
            SiteManager.notifications.error(data.message || 'An error occurred while deleting the comment.');
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
            SiteManager.notifications.toast(data.message, 'success');
        } else {
            SiteManager.notifications.error(data.message || 'An error occurred while approving the comment.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        SiteManager.notifications.error('An error occurred while approving the comment.');
    });
}

/**
 * 댓글 목록 로드 (Pagination 지원)
 */
function loadComments(page = 1, perPage = 5) {
    if (!window.commentRoutes) {
        console.error('Comment routes not found');
        return;
    }
    
    const url = new URL(window.commentRoutes.index);
    url.searchParams.append('page', page);
    url.searchParams.append('per_page', perPage);
    
    // Show loading indicator
    const commentsContainer = document.getElementById('comments-container');
    if (commentsContainer) {
        commentsContainer.innerHTML = '<div class="text-center py-4"><i class="bi bi-arrow-clockwise spin"></i> Loading comments...</div>';
    }
    
    fetch(url.toString(), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update comments container
            if (commentsContainer) {
                commentsContainer.innerHTML = data.comments_html;
                
                // Initialize pagination event listeners
                initializePaginationEvents();
                
                // Update comment count
                updateCommentCount(data.comment_count);
            }
        } else {
            console.error('Failed to load comments:', data.message);
            SiteManager.notifications.error(data.message || 'Failed to load comments');
        }
    })
    .catch(error => {
        console.error('Error loading comments:', error);
        SiteManager.notifications.error('An error occurred while loading comments');
        
        if (commentsContainer) {
            commentsContainer.innerHTML = '<div class="text-center text-muted py-4">Failed to load comments. Please try again.</div>';
        }
    });
}

/**
 * Pagination 이벤트 리스너 초기화
 */
function initializePaginationEvents() {
    const paginationLinks = document.querySelectorAll('.comments-page-link');
    if (paginationLinks.length === 0) {
        return; // pagination 링크가 없으면 종료
    }
    
    paginationLinks.forEach(link => {
        // 기존 이벤트 리스너 제거 (중복 방지)
        link.removeEventListener('click', handlePaginationClick);
        link.addEventListener('click', handlePaginationClick);
    });
}

/**
 * Pagination 클릭 핸들러
 */
function handlePaginationClick(e) {
    e.preventDefault();
    const page = parseInt(this.getAttribute('data-page'));
    if (page && page > 0) {
        loadComments(page);
    }
}

/**
 * 댓글 수 업데이트
 */
function updateCommentCount(count) {
    const commentCountElements = document.querySelectorAll('[data-comment-count]');
    commentCountElements.forEach(element => {
        element.textContent = count;
        element.setAttribute('data-comment-count', count);
    });
}

// 전역 함수로 등록
window.loadComments = loadComments;
window.initializePaginationEvents = initializePaginationEvents;
