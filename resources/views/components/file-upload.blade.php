@props([
    'name' => 'files[]',
    'id' => 'files',
    'multiple' => true,
    'maxFileSize' => 10240,
    'maxFiles' => 10,
    'allowedTypes' => null,
    'enablePreview' => true,
    'enableEdit' => true,
    'showFileInfo' => true,
    'showGuidelines' => true,
    'existingAttachments' => null,
    'label' => 'Attachments',
    'icon' => 'bi-paperclip',
    'required' => false,
    'errors' => null,
    'class' => '',
    'wrapperClass' => 'mb-3'
])

@once
    @push('head')
        {!! resource('sitemanager::css/file-upload.css') !!}
        {!! resource('sitemanager::js/file-upload.js') !!}
        <script>
            // Global file upload configuration
            window.FileUploadConfig = window.FileUploadConfig || {};
            window.FileUploadConfig.allowedTypes = {!! json_encode(config('sitemanager.board.allowed_extensions', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'])) !!};
            window.FileUploadConfig.maxFileSize = {{ config('sitemanager.board.max_file_size', 2048) }};
            window.FileUploadConfig.maxFilesPerPost = {{ config('sitemanager.board.max_files_per_post', 5) }};
        </script>
    @endpush
@endonce

<div class="{{ $wrapperClass }}">
    @if($label)
        <label for="{{ $id }}" class="form-label">
            @if($icon)
                <i class="{{ $icon }}"></i>
            @endif
            {{ $label }}
            @if($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif
    
    <div class="file-upload-wrapper {{ $class }}">
        <!-- Drag & Drop Zone -->
        <div class="file-drop-zone border-2 border-dashed border-secondary rounded p-4 text-center mb-3">
            <div class="mb-2">
                <i class="bi bi-cloud-upload fs-1 text-muted"></i>
            </div>
            <p class="mb-2">
                <strong>Drag & drop files here</strong> or <span class="text-primary">click to browse</span>
            </p>
            <small class="text-muted">
                Max {{ number_format($maxFileSize) }}KB per file • 
                {{ is_array($allowedTypes) ? implode(', ', $allowedTypes) : $allowedTypes }}
            </small>
        </div>
        
        <input type="file" 
               class="file-upload-input form-control d-none @if($errors && $errors->has($name)) is-invalid @endif" 
               id="{{ $id }}" 
               name="{{ $name }}" 
               @if($multiple) multiple @endif
               accept=".{{ implode(',.', $allowedTypes) }}"
               @if($required) required @endif>
        
        @if($showGuidelines)
            <div class="upload-guidelines">
                <strong>Upload Guidelines:</strong><br>
                • Max file size: {{ number_format($maxFileSize) }}KB per file<br>
                • Max files per upload: {{ $maxFiles }}<br>
                • Allowed types: {{ is_array($allowedTypes) ? implode(', ', $allowedTypes) : $allowedTypes }}
            </div>
        @endif
        
        <!-- File List Preview with Edit Options -->
        <div class="file-list mt-3">
            <div class="border rounded p-3 bg-light">
                <h6 class="mb-2">Selected Files:</h6>
                <div class="file-items"></div>
            </div>
        </div>

        <!-- Existing Attachments (for edit mode) -->
        @if($existingAttachments && $existingAttachments->count() > 0)
            <div class="existing-attachments mt-3">
                <h6 class="mb-2">Current Attachments:</h6>
                @foreach($existingAttachments->sortBy('sort_order') as $attachment)
                    <div class="existing-attachment" id="attachment-{{ $attachment->id }}" data-attachment-id="{{ $attachment->id }}" data-sort-order="{{ $attachment->sort_order ?? 999 }}" draggable="true">
                        <div class="row align-items-center">
                            <!-- 드래그 핸들 -->
                            <div class="col-auto">
                                <div class="drag-handle">
                                    <i class="bi bi-grip-vertical"></i>
                                </div>
                            </div>
                            
                            <!-- 파일 미리보기 -->
                            <div class="col-auto">
                                <div class="file-preview">
                                    @if($attachment->is_image ?? false)
                                        <img src="{{ $attachment->preview_url ?? '#' }}" alt="Preview">
                                    @else
                                        <i class="bi {{ $attachment->file_icon ?? 'bi-file-earmark' }} file-icon"></i>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- 파일 정보 및 편집 -->
                            <div class="col">
                                @if($enableEdit)
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Display Name</label>
                                            <input type="text" 
                                                   class="form-control form-control-sm" 
                                                   name="existing_file_names[{{ $attachment->id }}]"
                                                   value="{{ $attachment->original_name ?? $attachment->name ?? '' }}"
                                                   placeholder="Display Name">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">Description</label>
                                            <input type="text" 
                                                   class="form-control form-control-sm" 
                                                   name="existing_file_descriptions[{{ $attachment->id }}]"
                                                   value="{{ $attachment->description ?? '' }}"
                                                   placeholder="Description">
                                        </div>
                                    </div>
                                @else
                                    <div class="fw-medium">{{ $attachment->original_name ?? $attachment->name ?? 'Unknown File' }}</div>
                                    @if(isset($attachment->description) && $attachment->description)
                                        <div class="text-muted small">{{ $attachment->description }}</div>
                                    @endif
                                @endif
                                
                                @if($showFileInfo)
                                    <div class="file-info mt-1">
                                        <small class="text-muted">
                                            @if(isset($attachment->original_name))
                                                Original: {{ $attachment->original_name }}
                                            @endif
                                            @if(isset($attachment->formatted_file_size))
                                                ({{ $attachment->formatted_file_size }})
                                            @elseif(isset($attachment->size))
                                                ({{ number_format($attachment->size / 1024, 1) }}KB)
                                            @endif
                                        </small>
                                    </div>
                                @endif
                            </div>
                            
                            <!-- 액션 버튼 -->
                            <div class="col-auto">
                                <div class="file-actions">
                                    @if(isset($attachment->download_url))
                                        <a href="{{ $attachment->download_url }}" 
                                           class="btn btn-sm btn-outline-primary" 
                                           target="_blank" 
                                           title="Download">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    @endif
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            onclick="fileUpload.removeAttachment({{ $attachment->id }})"
                                            title="Delete">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    
    @if($errors && $errors->has($name))
        <div class="invalid-feedback d-block">{{ $errors->first($name) }}</div>
    @endif
</div>

@once
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize file upload component
            if (typeof FileUploadComponent !== 'undefined' && document.querySelector('.file-drop-zone')) {
                window.fileUpload = new FileUploadComponent({
                    dropZoneSelector: '.file-drop-zone',
                    fileInputSelector: '#{{ $id }}',
                    fileListSelector: '.file-list',
                    fileItemsSelector: '.file-items',
                    maxFileSize: {{ $maxFileSize }}, // KB
                    maxFiles: {{ $maxFiles }},
                    allowedTypes: {!! json_encode($allowedTypes) !!},
                    enablePreview: {{ $enablePreview ? 'true' : 'false' }},
                    enableEdit: {{ $enableEdit ? 'true' : 'false' }},
                    showFileInfo: {{ $showFileInfo ? 'true' : 'false' }}
                });
            }
        });
    </script>
    @endpush
@endonce
