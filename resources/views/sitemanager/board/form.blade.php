@extends('sitemanager::layouts.sitemanager')

@section('title', isset($board) ? 'Edit Board - ' . $board->name : 'Create New Board')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            @if(isset($board))
                <i class="bi bi-pencil"></i> Edit Board - {{ $board->name }}
            @else
                <i class="bi bi-plus-lg"></i> Create New Board
            @endif
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="{{ route('sitemanager.boards.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <h6>Please correct the following errors:</h6>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ isset($board) ? route('sitemanager.boards.update', $board) : route('sitemanager.boards.store') }}">
        @csrf
        @if(isset($board))
            @method('PUT')
        @endif
        
        <!-- Basic Information Row -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Basic Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Board Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" value="{{ old('name', isset($board) ? $board->name : '') }}" required maxlength="100">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('slug') is-invalid @enderror" 
                                       id="slug" name="slug" value="{{ old('slug', isset($board) ? $board->slug : '') }}" required maxlength="50"
                                       pattern="[a-z0-9_]+" title="Only lowercase letters, numbers, and underscores are allowed">
                                <div class="form-text">Used in URLs and database table names. Only lowercase letters, numbers, and underscores are allowed.</div>
                                <div id="slug-feedback" class="mt-1" style="display: none;"></div>
                                @error('slug')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="menu_id" class="form-label">Connect to Menu</label>
                                <select class="form-select @error('menu_id') is-invalid @enderror" id="menu_id" name="menu_id">
                                    <option value="">Select Menu (Optional)</option>
                                    @if(isset($menus))
                                        @foreach($menus as $menu)
                                            <option value="{{ $menu->id }}" 
                                                    {{ old('menu_id', isset($board) ? $board->menu_id : '') == $menu->id ? 'selected' : '' }}>
                                                {{ str_repeat('　', $menu->depth) }}{{ $menu->title }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                @error('menu_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                                    <option value="active" {{ old('status', isset($board) ? $board->status : 'active') === 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status', isset($board) ? $board->status : '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="posts_per_page" class="form-label">Posts per Page</label>
                            <input type="number" class="form-control @error('posts_per_page') is-invalid @enderror" 
                                   id="posts_per_page" name="posts_per_page" 
                                   value="{{ old('posts_per_page', isset($board) ? $board->posts_per_page : 20) }}" 
                                   min="5" max="100">
                            @error('posts_per_page')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Features</label>
                            <div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="use_categories" name="use_categories" 
                                           value="1" {{ old('use_categories', isset($board) ? $board->getSetting('use_categories', false) : false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="use_categories">Use Categories</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="use_files" name="use_files" 
                                           value="1" {{ old('use_files', isset($board) ? $board->getSetting('allow_file_upload', false) : false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="use_files">Allow File Attachments</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="allow_comments" name="allow_comments" 
                                           value="1" {{ old('allow_comments', isset($board) ? $board->getSetting('allow_comments', true) : true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="allow_comments">Enable Comments</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="use_tags" name="use_tags" 
                                           value="1" {{ old('use_tags', isset($board) ? $board->getSetting('use_tags', false) : false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="use_tags">Enable Tags</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Custom Settings Card -->
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0">Custom Settings</h6>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-custom-setting">
                            <i class="bi bi-plus"></i> Add Setting
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="custom-settings-container">
                            @if(isset($board) && $board->getCustomSettings())
                                @foreach($board->getCustomSettings() as $key => $value)
                                    <div class="custom-setting-item mb-3">
                                        <div class="row g-2">
                                            <div class="col-5">
                                                <input type="text" class="form-control form-control-sm" 
                                                       name="custom_settings[{{ $loop->index }}][key]" 
                                                       value="{{ $key }}" 
                                                       placeholder="Setting key">
                                            </div>
                                            <div class="col-6">
                                                <input type="text" class="form-control form-control-sm" 
                                                       name="custom_settings[{{ $loop->index }}][value]" 
                                                       value="{{ is_array($value) ? json_encode($value) : $value }}" 
                                                       placeholder="Setting value">
                                            </div>
                                            <div class="col-1">
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-setting">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        <div class="form-text">
                            <small>Add custom settings that can be accessed in templates using <code>$board->getSetting('key')</code></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Additional Settings Row (appears when needed) -->
        <div class="row mt-4">
            <!-- Category Settings -->
            <div class="col-lg-6">
                <div class="card" id="category_settings" style="display: none;">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Category Settings</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="categories" class="form-label">Category List</label>
                            <textarea class="form-control" id="categories" name="categories" rows="5" 
                                      placeholder="Enter one category per line">{{ old('categories', isset($board) && $board->categories ? implode("\n", $board->categories) : '') }}</textarea>
                            <div class="form-text">Enter one category name per line.</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- File Upload Settings -->
            <div class="col-lg-6">
                <div class="card" id="file_settings" style="display: none;">
                    <div class="card-header">
                        <h6 class="card-title mb-0">File Upload Settings</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="max_file_size" class="form-label">Max File Size (KB)</label>
                            <input type="number" class="form-control" id="max_file_size" name="settings[max_file_size]" 
                                   value="{{ old('settings.max_file_size', isset($board) ? $board->getSetting('max_file_size', 2048) : 2048) }}" 
                                   min="100" max="51200">
                        </div>
                        
                        <div class="mb-3">
                            <label for="max_files_per_post" class="form-label">Max Files per Post</label>
                            <input type="number" class="form-control" id="max_files_per_post" name="settings[max_files_per_post]" 
                                   value="{{ old('settings.max_files_per_post', isset($board) ? $board->getSetting('max_files_per_post', 5) : 5) }}" 
                                   min="1" max="20">
                        </div>
                        
                        <div class="mb-3">
                            <label for="allowed_file_types" class="form-label">Allowed File Types</label>
                            <input type="text" class="form-control" id="allowed_file_types" name="settings[allowed_file_types]" 
                                   value="{{ old('settings.allowed_file_types', isset($board) ? implode(',', $board->getAllowedFileTypes()) : implode(',', config('sitemanager.board.allowed_extensions'))) }}" 
                                   placeholder="jpg,jpeg,png,gif,pdf">
                            <div class="form-text">Separate with commas.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('sitemanager.boards.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        @if(isset($board))
                            <i class="bi bi-check-lg"></i> Update
                        @else
                            <i class="bi bi-plus-lg"></i> Create
                        @endif
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto slug generation
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    const slugFeedback = document.getElementById('slug-feedback');
    
    if (nameInput && slugInput) {
        nameInput.addEventListener('input', function() {
            if (!slugInput.dataset.manual) {
                const slug = this.value
                    .toLowerCase()
                    .replace(/[^a-z0-9가-힣\s_]/g, '')
                    .replace(/[\s]+/g, '_')
                    .replace(/[가-힣]/g, ''); // Remove Korean characters
                slugInput.value = slug;
                validateSlug(slug);
            }
        });
        
        slugInput.addEventListener('input', function() {
            this.dataset.manual = 'true';
            validateSlug(this.value);
        });
        
        // Initial validation if there's already a value
        if (slugInput.value) {
            validateSlug(slugInput.value);
        }
    }
    
    // Slug validation function
    function validateSlug(slug) {
        const slugPattern = /^[a-z0-9_]+$/;
        const feedbackDiv = slugFeedback;
        
        if (!feedbackDiv) return;
        
        // Clear previous feedback
        feedbackDiv.style.display = 'none';
        slugInput.classList.remove('is-valid', 'is-invalid');
        
        if (slug === '') {
            feedbackDiv.style.display = 'none';
            return;
        }
        
        let isValid = true;
        let messages = [];
        let suggestions = [];
        
        // Check for invalid characters
        const invalidChars = slug.match(/[^a-z0-9_]/g);
        if (invalidChars) {
            isValid = false;
            const uniqueInvalidChars = [...new Set(invalidChars)];
            messages.push(`Invalid characters found: <code>${uniqueInvalidChars.join(', ')}</code>`);
            
            // Generate suggestion
            const suggestion = slug.replace(/[^a-z0-9_]/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '');
            if (suggestion !== slug) {
                suggestions.push(`Suggested: <code>${suggestion}</code>`);
            }
        }
        
        // Check for uppercase letters
        if (/[A-Z]/.test(slug)) {
            isValid = false;
            messages.push('Uppercase letters are not allowed');
            suggestions.push(`Try: <code>${slug.toLowerCase()}</code>`);
        }
        
        // Check for spaces
        if (/\s/.test(slug)) {
            isValid = false;
            messages.push('Spaces are not allowed');
            suggestions.push(`Try: <code>${slug.replace(/\s+/g, '_')}</code>`);
        }
        
        // Check for hyphens (old format)
        if (/-/.test(slug)) {
            isValid = false;
            messages.push('Hyphens are not allowed (use underscores instead)');
            suggestions.push(`Try: <code>${slug.replace(/-/g, '_')}</code>`);
        }
        
        // Check length
        if (slug.length > 50) {
            isValid = false;
            messages.push('Slug is too long (maximum 50 characters)');
            suggestions.push(`Try: <code>${slug.substring(0, 50)}</code>`);
        }
        
        // Check if starts or ends with underscore
        if (slug.startsWith('_') || slug.endsWith('_')) {
            isValid = false;
            messages.push('Slug should not start or end with underscores');
            suggestions.push(`Try: <code>${slug.replace(/^_+|_+$/g, '')}</code>`);
        }
        
        // Check for multiple consecutive underscores
        if (/__/.test(slug)) {
            isValid = false;
            messages.push('Multiple consecutive underscores are not recommended');
            suggestions.push(`Try: <code>${slug.replace(/_+/g, '_')}</code>`);
        }
        
        // Display feedback
        if (isValid && slugPattern.test(slug)) {
            slugInput.classList.add('is-valid');
            feedbackDiv.innerHTML = `
                <div class="text-success">
                    <i class="bi bi-check-circle me-1"></i>
                    Valid slug format!
                </div>
            `;
            feedbackDiv.style.display = 'block';
        } else {
            slugInput.classList.add('is-invalid');
            let feedbackHtml = '<div class="text-danger">';
            feedbackHtml += '<i class="bi bi-exclamation-triangle me-1"></i>';
            feedbackHtml += messages.join('<br>');
            feedbackHtml += '</div>';
            
            if (suggestions.length > 0) {
                feedbackHtml += '<div class="text-info mt-1">';
                feedbackHtml += '<i class="bi bi-lightbulb me-1"></i>';
                feedbackHtml += suggestions.join('<br>');
                feedbackHtml += '</div>';
            }
            
            feedbackDiv.innerHTML = feedbackHtml;
            feedbackDiv.style.display = 'block';
        }
        
        // Add click handlers for suggestions
        const suggestions_codes = feedbackDiv.querySelectorAll('code');
        suggestions_codes.forEach(code => {
            code.style.cursor = 'pointer';
            code.style.textDecoration = 'underline';
            code.title = 'Click to use this suggestion';
            code.addEventListener('click', function() {
                slugInput.value = this.textContent;
                slugInput.dataset.manual = 'true';
                validateSlug(this.textContent);
                slugInput.focus();
            });
        });
    }
    
    // Category settings toggle
    const useCategoriesCheckbox = document.getElementById('use_categories');
    const categorySettings = document.getElementById('category_settings');
    
    function toggleCategorySettings() {
        if (useCategoriesCheckbox && categorySettings) {
            categorySettings.style.display = useCategoriesCheckbox.checked ? 'block' : 'none';
        }
    }
    
    if (useCategoriesCheckbox) {
        useCategoriesCheckbox.addEventListener('change', toggleCategorySettings);
        toggleCategorySettings(); // Set initial state
    }
    
    // File upload settings toggle
    const useFilesCheckbox = document.getElementById('use_files');
    const fileSettings = document.getElementById('file_settings');
    
    function toggleFileSettings() {
        if (useFilesCheckbox && fileSettings) {
            fileSettings.style.display = useFilesCheckbox.checked ? 'block' : 'none';
        }
    }
    
    if (useFilesCheckbox) {
        useFilesCheckbox.addEventListener('change', toggleFileSettings);
        toggleFileSettings(); // Set initial state
    }
    
    // Custom Settings functionality
    const addCustomSettingBtn = document.getElementById('add-custom-setting');
    const customSettingsContainer = document.getElementById('custom-settings-container');
    let customSettingIndex = customSettingsContainer.children.length;
    
    if (addCustomSettingBtn) {
        addCustomSettingBtn.addEventListener('click', function() {
            addCustomSetting();
        });
    }
    
    function addCustomSetting(key = '', value = '') {
        const settingHtml = `
            <div class="custom-setting-item mb-3">
                <div class="row g-2">
                    <div class="col-5">
                        <input type="text" class="form-control form-control-sm" 
                               name="custom_settings[${customSettingIndex}][key]" 
                               value="${key}" 
                               placeholder="Setting key (e.g., show_name)">
                    </div>
                    <div class="col-6">
                        <input type="text" class="form-control form-control-sm" 
                               name="custom_settings[${customSettingIndex}][value]" 
                               value="${value}" 
                               placeholder="Setting value (e.g., true, hello world)">
                    </div>
                    <div class="col-1">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-setting">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        customSettingsContainer.insertAdjacentHTML('beforeend', settingHtml);
        customSettingIndex++;
        
        // Add event listener to the new remove button
        const newSettingItem = customSettingsContainer.lastElementChild;
        const removeBtn = newSettingItem.querySelector('.remove-setting');
        removeBtn.addEventListener('click', function() {
            newSettingItem.remove();
        });
    }
    
    // Add event listeners to existing remove buttons
    document.querySelectorAll('.remove-setting').forEach(function(btn) {
        btn.addEventListener('click', function() {
            btn.closest('.custom-setting-item').remove();
        });
    });
});
</script>
@endpush

@push('styles')
<style>
@media (max-width: 768px) {
    .card-header h5, .card-header h6 {
        font-size: 1rem;
    }
    
    .btn-toolbar .btn {
        font-size: 0.875rem;
        padding: 0.375rem 0.75rem;
    }
}

/* Slug validation styles */
#slug-feedback {
    font-size: 0.875rem;
    line-height: 1.4;
}

#slug-feedback code {
    background-color: #f8f9fa;
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    color: #0066cc;
    border: 1px solid #e9ecef;
    transition: all 0.2s ease;
}

#slug-feedback code:hover {
    background-color: #0066cc;
    color: white;
    transform: translateY(-1px);
}

.form-control.is-valid {
    border-color: #198754;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='m2.3 6.73.4-.4 1.4-1.4.7-.7-.7-.7L2.7 2.23 2.3 1.83 1.23 2.9 0 4.14l.4.4 1.4 1.4.5.9Z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.form-control.is-invalid {
    border-color: #dc3545;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 5.8 4.4 4.4m0-4.4-4.4 4.4'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}
</style>
@endpush
