/**
 * File Upload Component
 * Reusable drag & drop file upload functionality
 */

(function() {
    'use strict';
    
    // FileUploadComponent 클래스 정의
    const FileUploadComponent = class {
        constructor(options = {}) {
            // Get global config if available
            const globalConfig = window.FileUploadConfig || {};
            
            this.options = {
                dropZoneSelector: '.file-drop-zone',
                fileInputSelector: '.file-upload-input',
                fileListSelector: '.file-list',
                fileItemsSelector: '.file-items',
                maxFileSize: globalConfig.maxFileSize || 10240, // KB
                maxFiles: globalConfig.maxFilesPerPost || 10,
                allowedTypes: globalConfig.allowedTypes || ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'webp'],
                enablePreview: true,
                enableEdit: true,
                showFileInfo: true,
                ...options
            };
            
            this.fileData = new Map(); // Store file metadata
            this.init();
        }
        
        init() {
            this.setupEventListeners();
            // Initialize attachment sorting after a short delay to ensure DOM is ready
            setTimeout(() => {
                this.initAttachmentSorting();
            }, 100);
        }
        
        setupEventListeners() {
            const dropZone = document.querySelector(this.options.dropZoneSelector);
            const fileInput = document.querySelector(this.options.fileInputSelector);
            
            //     dropZone: !!dropZone,
            //     fileInput: !!fileInput,
            //     dropZoneSelector: this.options.dropZoneSelector,
            //     fileInputSelector: this.options.fileInputSelector
            // });
            
            if (!dropZone || !fileInput) {
                return;
            }
            
            // Prevent default drag behaviors on entire page
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                document.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });
            
            // Drop zone specific event handlers
            dropZone.addEventListener('dragenter', (e) => this.handleDragEnter(e));
            dropZone.addEventListener('dragover', (e) => this.handleDragOver(e));
            dropZone.addEventListener('dragleave', (e) => this.handleDragLeave(e));
            dropZone.addEventListener('drop', (e) => this.handleDrop(e));
            dropZone.addEventListener('click', () => this.handleClick());
            
            // File input change handler - 수정된 부분
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    this.addFiles(e.target.files);
                }
            });
        }
        
        handleDragEnter(e) {
            e.preventDefault();
            e.stopPropagation();
            e.currentTarget.classList.add('dragover');
        }
        
        handleDragOver(e) {
            e.preventDefault();
            e.stopPropagation();
            e.currentTarget.classList.add('dragover');
        }
        
        handleDragLeave(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!e.currentTarget.contains(e.relatedTarget)) {
                e.currentTarget.classList.remove('dragover');
            }
        }
        
        handleDrop(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropZone = e.currentTarget;
            dropZone.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            
            if (files.length > 0) {
                this.addFiles(files);
            }
        }
        
        handleClick() {
            console.log('handleClick called');
            const fileInput = document.querySelector(this.options.fileInputSelector);
            console.log('File input found in handleClick:', !!fileInput, fileInput);
            if (fileInput) {
                console.log('Triggering file input click');
                fileInput.click();
            }
        }
        
        addFiles(files) {
            console.log('addFiles called with:', files.length, 'files');
            
            const fileInput = document.querySelector(this.options.fileInputSelector);
            if (!fileInput) {
                console.error('File input not found in addFiles');
                return;
            }
            
            // If not multiple, replace all files
            if (!fileInput.multiple) {
                const dt = new DataTransfer();
                const newFile = files[0];
                if (this.validateFile(newFile)) {
                    dt.items.add(newFile);
                }
                fileInput.files = dt.files;
                this.updateFileList(fileInput);
                return;
            }
            
            const dt = new DataTransfer();
            const existingFiles = new Map(); // 중복 체크를 위한 Map
            
            // Add existing files first and track them
            Array.from(fileInput.files).forEach(file => {
                const fileKey = `${file.name}_${file.size}_${file.lastModified || file.size}`;
                existingFiles.set(fileKey, file);
                dt.items.add(file);
            });
            
            // Add new files (with validation and duplicate check)
            let duplicateCount = 0;
            Array.from(files).forEach(file => {
                const fileKey = `${file.name}_${file.size}_${file.lastModified || file.size}`;
                
                // Skip if file already exists
                if (existingFiles.has(fileKey)) {
                    duplicateCount++;
                    return;
                }
                
                if (this.validateFile(file)) {
                    existingFiles.set(fileKey, file);
                    dt.items.add(file);
                }
            });
            
            // Show message if duplicates were found
            if (duplicateCount > 0) {
            }
            
            // Check max files limit
            if (dt.files.length > this.options.maxFiles) {
                SiteManager.notifications.warning(`Maximum ${this.options.maxFiles} files allowed.`);
                return;
            }
            
            fileInput.files = dt.files;
            this.updateFileList(fileInput);
        }
        
        validateFile(file) {
            console.log('Validating file:', {
                name: file.name,
                size: file.size,
                maxSize: this.options.maxFileSize * 1024,
                extension: file.name.split('.').pop().toLowerCase(),
                allowedTypes: this.options.allowedTypes
            });
            
            // Check file size
            if (file.size > this.options.maxFileSize * 1024) {
                SiteManager.notifications.warning(`File "${file.name}" is too large. Maximum size is ${this.options.maxFileSize}KB.`);
                return false;
            }
            
            // Check file type
            const extension = file.name.split('.').pop().toLowerCase();
            if (!this.options.allowedTypes.includes(extension)) {
                SiteManager.notifications.warning(`File type "${extension}" is not allowed. Allowed types: ${this.options.allowedTypes.join(', ')}`);
                return false;
            }
            
            console.log('File validation passed:', file.name);
            return true;
        }
        
        updateFileList(input) {
            const fileList = document.querySelector(this.options.fileListSelector);
            const fileItems = document.querySelector(this.options.fileItemsSelector);
            
            console.log('updateFileList called:', {
                fileList: !!fileList,
                fileItems: !!fileItems,
                fileListSelector: this.options.fileListSelector,
                fileItemsSelector: this.options.fileItemsSelector,
                filesCount: input.files.length
            });
            
            if (!fileList || !fileItems) {
                console.warn('File list containers not found');
                return;
            }
            
            // Clear existing metadata
            this.fileData.clear();
            
            if (input.files.length > 0) {
                fileList.classList.add('has-files');
                fileItems.innerHTML = '';
                
                Array.from(input.files).forEach((file, index) => {
                    this.createFileItem(file, index, fileItems);
                    // Store file metadata with correct index
                    this.fileData.set(index, {
                        originalName: file.name,
                        size: file.size,
                        type: file.type
                    });
                });
            } else {
                fileList.classList.remove('has-files');
            }
            
            // Trigger custom event
            this.dispatchEvent('fileListUpdated', { files: input.files });
        }
        
        createFileItem(file, index, container) {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.setAttribute('data-file-index', index);
            
            const fileIcon = this.getFileIcon(file.name);
            const fileSize = this.formatFileSize(file.size);
            const isImage = file.type.startsWith('image/');
            
            fileItem.innerHTML = `
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="file-preview">
                            ${isImage ? `
                                <img class="image-preview" style="display: none;" alt="Preview">
                                <i class="bi ${fileIcon} file-icon" style="display: block;"></i>
                            ` : `
                                <i class="bi ${fileIcon} file-icon"></i>
                            `}
                        </div>
                    </div>
                    <div class="col">
                        <div class="row g-2">
                            ${this.options.enableEdit ? `
                                <div class="col-md-4">
                                    <label class="form-label small text-muted mb-1">Display Name</label>
                                    <input type="text" class="form-control form-control-sm file-display-name" 
                                           value="${file.name}" name="file_names[${index}]"
                                           placeholder="Enter display name">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted mb-1">Description</label>
                                    <input type="text" class="form-control form-control-sm file-description" 
                                           name="file_descriptions[${index}]"
                                           placeholder="File description (optional)">
                                </div>
                                ${this.getCategorySelectHTML(index)}
                            ` : `
                                <div class="col-12">
                                    <div class="fw-medium">${file.name}</div>
                                </div>
                            `}
                        </div>
                        ${this.options.showFileInfo ? `
                            <div class="file-info mt-1">
                                <small class="text-muted">${fileSize}</small>
                            </div>
                        ` : ''}
                    </div>
                    <div class="col-auto">
                        <div class="file-actions">
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="window.FileUploadManager.removeFile(${index})" title="Remove">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(fileItem);
            
            // Load image preview if it's an image
            if (isImage && this.options.enablePreview) {
                this.loadImagePreview(file, fileItem);
            }
        }
        
        loadImagePreview(file, fileItem) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const imagePreview = fileItem.querySelector('.image-preview');
                const iconElement = fileItem.querySelector('.file-icon');
                if (imagePreview && iconElement) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                    iconElement.style.display = 'none';
                }
            };
            reader.readAsDataURL(file);
        }
        
        removeFile(index) {
            const fileInput = document.querySelector(this.options.fileInputSelector);
            if (!fileInput) return;
            
            const dt = new DataTransfer();
            
            // Remove file from FileList and rebuild metadata
            this.fileData.clear();
            Array.from(fileInput.files).forEach((file, i) => {
                if (i !== index) {
                    dt.items.add(file);
                    // Re-index metadata
                    this.fileData.set(dt.files.length - 1, {
                        originalName: file.name,
                        size: file.size,
                        type: file.type
                    });
                }
            });
            
            fileInput.files = dt.files;
            
            // Regenerate the file list to update all indices
            this.updateFileList(fileInput);
        }
        
        getFileIcon(filename) {
            const extension = filename.split('.').pop().toLowerCase();
            const iconMap = {
                'pdf': 'bi-file-earmark-pdf text-danger',
                'doc': 'bi-file-earmark-word text-primary',
                'docx': 'bi-file-earmark-word text-primary',
                'xls': 'bi-file-earmark-excel text-success',
                'xlsx': 'bi-file-earmark-excel text-success',
                'ppt': 'bi-file-earmark-ppt text-warning',
                'pptx': 'bi-file-earmark-ppt text-warning',
                'zip': 'bi-file-earmark-zip text-secondary',
                'rar': 'bi-file-earmark-zip text-secondary',
                '7z': 'bi-file-earmark-zip text-secondary',
                'jpg': 'bi-file-earmark-image text-info',
                'jpeg': 'bi-file-earmark-image text-info',
                'png': 'bi-file-earmark-image text-info',
                'gif': 'bi-file-earmark-image text-info',
                'webp': 'bi-file-earmark-image text-info',
                'mp3': 'bi-file-earmark-music text-primary',
                'wav': 'bi-file-earmark-music text-primary',
                'mp4': 'bi-file-earmark-play text-danger',
                'avi': 'bi-file-earmark-play text-danger',
                'txt': 'bi-file-earmark-text text-secondary'
            };
            
            return iconMap[extension] || 'bi-file-earmark text-secondary';
        }
        
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        getCategorySelectHTML(index) {
            const fileCategories = window.FileUploadConfig?.fileCategories || [];
            
            if (fileCategories.length === 0) {
                return '';
            }
            
            let options = '';
            fileCategories.forEach(category => {
                options += `<option value="${category}">${category.charAt(0).toUpperCase() + category.slice(1)}</option>`;
            });
            
            return `
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Category</label>
                    <select class="form-select form-select-sm" name="file_categories[${index}]">
                        <option value="">Select</option>
                        ${options}
                    </select>
                </div>
            `;
        }
        
        dispatchEvent(eventName, detail) {
            const event = new CustomEvent(`fileUpload:${eventName}`, { detail });
            document.dispatchEvent(event);
        }
        
        // Existing attachment management methods
        removeAttachment(attachmentId) {
            SiteManager.notifications.confirmDelete('this attachment').then((confirmed) => {
                if (!confirmed) return;
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]');
                
                fetch(`/board/attachments/${attachmentId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken ? csrfToken.getAttribute('content') : '',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const element = document.getElementById(`attachment-${attachmentId}`);
                        if (element) {
                            element.remove();
                        }
                        this.dispatchEvent('attachmentRemoved', { attachmentId });
                    } else {
                        SiteManager.notifications.error('Failed to remove attachment. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    SiteManager.notifications.error('An error occurred while removing the attachment.');
                });
            });
        }
        
        // Attachment sorting functionality
        initAttachmentSorting() {
            const attachmentsContainer = document.querySelector('.existing-attachments');
            if (!attachmentsContainer) return;
            
            // Make existing attachments sortable
            const attachmentItems = attachmentsContainer.querySelectorAll('.existing-attachment');
            attachmentItems.forEach(item => {
                item.draggable = true;
                
                // Add event listeners
                item.addEventListener('dragstart', this.handleAttachmentDragStart.bind(this));
                item.addEventListener('dragover', this.handleAttachmentDragOver.bind(this));
                item.addEventListener('drop', this.handleAttachmentDrop.bind(this));
                item.addEventListener('dragend', this.handleAttachmentDragEnd.bind(this));
            });
        }
        
        handleAttachmentDragStart(e) {
            e.dataTransfer.setData('text/plain', e.currentTarget.id);
            e.currentTarget.classList.add('dragging');
        }
        
        handleAttachmentDragOver(e) {
            e.preventDefault();
            const dragging = document.querySelector('.existing-attachment.dragging');
            const currentItem = e.currentTarget;
            
            if (dragging && dragging !== currentItem) {
                const rect = currentItem.getBoundingClientRect();
                const midpoint = rect.top + rect.height / 2;
                
                if (e.clientY < midpoint) {
                    currentItem.parentNode.insertBefore(dragging, currentItem);
                } else {
                    currentItem.parentNode.insertBefore(dragging, currentItem.nextSibling);
                }
            }
        }
        
        handleAttachmentDrop(e) {
            e.preventDefault();
            const draggedId = e.dataTransfer.getData('text/plain');
            
            // Update sort order
            this.updateAttachmentSortOrder();
        }
        
        handleAttachmentDragEnd(e) {
            e.currentTarget.classList.remove('dragging');
        }
        
        updateAttachmentSortOrder() {
            const attachmentsContainer = document.querySelector('.existing-attachments');
            if (!attachmentsContainer) return;
            
            const attachmentItems = attachmentsContainer.querySelectorAll('.existing-attachment');
            const sortData = [];
            
            attachmentItems.forEach((item, index) => {
                const attachmentId = item.id.replace('attachment-', '');
                sortData.push({
                    id: parseInt(attachmentId),
                    sort_order: index + 1
                });
                
                // Update hidden input for sort order
                let sortInput = item.querySelector('input[name^="existing_file_sort_order"]');
                if (!sortInput) {
                    sortInput = document.createElement('input');
                    sortInput.type = 'hidden';
                    sortInput.name = `existing_file_sort_order[${attachmentId}]`;
                    item.appendChild(sortInput);
                }
                sortInput.value = index + 1;
            });
            
            // Send AJAX request to update sort order immediately
            if (sortData.length > 0) {
                this.saveSortOrder(sortData);
            }
            
        }
        
        saveSortOrder(sortData) {
            fetch('/board/attachments/sort-order', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    attachments: sortData
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.dispatchEvent('attachmentSortOrderUpdated', { sortData });
                } else {
                    console.error('Failed to save sort order:', data.message);
                }
            })
            .catch(error => {
                console.error('Error saving sort order:', error);
            });
        }
        
        // Utility methods for external access
        getFileCount() {
            const fileInput = document.querySelector(this.options.fileInputSelector);
            return fileInput ? fileInput.files.length : 0;
        }
        
        clearFiles() {
            const fileInput = document.querySelector(this.options.fileInputSelector);
            if (fileInput) {
                fileInput.value = '';
                this.updateFileList(fileInput);
            }
        }
        
        getFiles() {
            const fileInput = document.querySelector(this.options.fileInputSelector);
            return fileInput ? Array.from(fileInput.files) : [];
        }
    };
    
    // 전역 객체 생성 및 관리
    window.FileUploadManager = {
        instance: null,
        
        init: function() {
            if (!this.instance && document.querySelector('.file-drop-zone')) {
                this.instance = new FileUploadComponent();
            }
            return this.instance;
        },
        
        removeFile: function(index) {
            if (this.instance) {
                this.instance.removeFile(index);
            }
        },
        
        removeAttachment: function(attachmentId) {
            if (this.instance) {
                this.instance.removeAttachment(attachmentId);
            }
        },
        
        initAttachmentSorting: function() {
            if (this.instance) {
                this.instance.initAttachmentSorting();
            }
        },
        
        getFileIcon: function(filename) {
            return this.instance ? this.instance.getFileIcon(filename) : 'bi-file-earmark text-secondary';
        },
        
        formatFileSize: function(bytes) {
            return this.instance ? this.instance.formatFileSize(bytes) : '0 Bytes';
        },
        
        getFileCount: function() {
            return this.instance ? this.instance.getFileCount() : 0;
        },
        
        clearFiles: function() {
            if (this.instance) {
                this.instance.clearFiles();
            }
        },
        
        getFiles: function() {
            return this.instance ? this.instance.getFiles() : [];
        }
    };
    
    // 백워드 호환성을 위한 전역 함수들
    window.removeFile = function(index) {
        window.FileUploadManager.removeFile(index);
    };
    
    window.removeAttachment = function(attachmentId) {
        window.FileUploadManager.removeAttachment(attachmentId);
    };
    
    window.getFileIcon = function(filename) {
        return window.FileUploadManager.getFileIcon(filename);
    };
    
    window.formatFileSize = function(bytes) {
        return window.FileUploadManager.formatFileSize(bytes);
    };
    
    // DOM이 로드되면 자동 초기화
    document.addEventListener('DOMContentLoaded', function() {
        window.FileUploadManager.init();
        
        // 레거시 호환성을 위해 fileUpload도 설정
        window.fileUpload = window.FileUploadManager.instance;
    });
    
    // 모듈 시스템을 위한 export
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = FileUploadComponent;
    }
    
})();