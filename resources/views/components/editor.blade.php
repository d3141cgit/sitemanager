@props([
    'name' => 'content', 
    'value' => '', 
    'height' => '400', 
    'placeholder' => 'Enter your content...',
    'referenceType' => null,
    'referenceSlug' => null,
    'referenceId' => null
])

<div class="editor-wrapper">
    <textarea 
        name="{{ $name }}" 
        id="{{ $name }}_editor" 
        placeholder="{{ $placeholder }}"
        style="height: {{ $height }}px;"
    >{{ old($name, $value) }}</textarea>
</div>

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs5.min.js"></script>
<script>
$(document).ready(function() {
    $('#{{ $name }}_editor').summernote({
        height: {{ $height }},
        placeholder: '{{ $placeholder }}',
        
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture', 'video']],
            ['view', ['fullscreen', 'codeview']]
        ],
        
        fontSizes: ['8', '9', '10', '11', '12', '14', '18', '24', '36'],
        
        // Image upload callback
        callbacks: {
            onImageUpload: function(files) {
                uploadSummernoteImage(files[0], this);
            },
            onPaste: function(e) {
                var clipboardData = e.originalEvent.clipboardData;
                if (clipboardData && clipboardData.items && clipboardData.items.length) {
                    var item = clipboardData.items[0];
                    if (item.kind === 'file' && item.type.indexOf('image/') !== -1) {
                        e.preventDefault();
                        var file = item.getAsFile();
                        uploadSummernoteImage(file, this);
                    }
                }
            },
            onChange: function(contents, $editable) {
                // Sync content with textarea
                $('#{{ $name }}_editor').val(contents);
            }
        }
    });
    
    // Drag and drop event handling
    var $noteEditable = $('.note-editable');
    
    $noteEditable.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('drag-over');
    });
    
    $noteEditable.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');
    });
    
    $noteEditable.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');
        
        var files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            var file = files[0];
            if (file.type.startsWith('image/')) {
                uploadSummernoteImage(file, $('#{{ $name }}_editor')[0]);
            }
        }
    });
    
    // Sync editor content when form is submitted
    var form = $('#{{ $name }}_editor').closest('form');
    if (form.length > 0) {
        form.on('submit', function() {
            var content = $('#{{ $name }}_editor').summernote('code');
            $('#{{ $name }}_editor').val(content);
        });
    }
    
    // Image upload function
    function uploadSummernoteImage(file, editor) {
        // File size check (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('Image size is too large. Maximum 5MB allowed.');
            return;
        }
        
        // File type check
        var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (allowedTypes.indexOf(file.type) === -1) {
            alert('Unsupported image format. Only JPG, PNG, GIF, WebP are allowed.');
            return;
        }
        
        var formData = new FormData();
        formData.append('upload', file);
        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
        
        @if($referenceType)
        formData.append('reference_type', '{{ $referenceType }}');
        @endif
        
        @if($referenceSlug)
        formData.append('reference_slug', '{{ $referenceSlug }}');
        @endif
        
        @if($referenceId)
        formData.append('reference_id', '{{ $referenceId }}');
        @endif
        
        // Show progress indicator
        showUploadProgress();
        
        $.ajax({
            url: '{{ route("editor.upload-image") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(result) {
                hideUploadProgress();
                
                if (result.uploaded) {
                    // Insert image into editor
                    $('#{{ $name }}_editor').summernote('insertImage', result.url, function(image) {
                        // Set image attributes
                        image.css('max-width', '100%');
                        image.css('height', 'auto');
                        image.attr('alt', file.name);
                    });
                    
                    // 임시 reference_id가 있으면 form에 hidden input으로 추가
                    if (result.temp_reference_id) {
                        var form = $('#{{ $name }}_editor').closest('form');
                        var existingInput = form.find('input[name="temp_reference_id"]');
                        if (existingInput.length === 0) {
                            form.append('<input type="hidden" name="temp_reference_id" value="' + result.temp_reference_id + '">');
                        } else {
                            existingInput.val(result.temp_reference_id);
                        }
                    }
                    
                    // Success notification (optional)
                    if (typeof showNotification === 'function') {
                        showNotification('Image uploaded successfully.', 'success');
                    }
                } else {
                    console.error('Image upload failed:', result.error);
                    alert('Image upload failed: ' + (result.error?.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                hideUploadProgress();
                console.error('Image upload error:', error);
                
                var errorMessage = 'An error occurred while uploading the image.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    if (errors.upload && errors.upload.length > 0) {
                        errorMessage = errors.upload[0];
                    }
                }
                alert(errorMessage);
            }
        });
    }
    
    // Show upload progress function
    function showUploadProgress() {
        var progressBar = $('.editor-upload-progress');
        if (progressBar.length === 0) {
            progressBar = $('<div class="editor-upload-progress"><div class="editor-upload-progress-bar"></div></div>');
            $('.editor-wrapper').append(progressBar);
        }
        
        var bar = progressBar.find('.editor-upload-progress-bar');
        bar.css('width', '0%');
        
        // Animation effect
        setTimeout(function() {
            bar.css('width', '70%');
        }, 100);
    }
    
    // Hide upload progress function
    function hideUploadProgress() {
        var progressBar = $('.editor-upload-progress');
        if (progressBar.length > 0) {
            var bar = progressBar.find('.editor-upload-progress-bar');
            bar.css('width', '100%');
            
            setTimeout(function() {
                progressBar.remove();
            }, 300);
        }
    }
    
    // Global functions to access editor
    window.{{ $name }}_getContent = function() {
        return $('#{{ $name }}_editor').summernote('code');
    };
    
    window.{{ $name }}_setContent = function(content) {
        $('#{{ $name }}_editor').summernote('code', content);
    };
    
    window.{{ $name }}_focus = function() {
        $('#{{ $name }}_editor').summernote('focus');
    };
    
    window.{{ $name }}_destroy = function() {
        $('#{{ $name }}_editor').summernote('destroy');
    };
});
</script>
@endpush
