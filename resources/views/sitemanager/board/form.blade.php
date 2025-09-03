@extends('sitemanager::layouts.sitemanager')

@section('title', isset($board) ? t('Edit Board') . ' - ' . $board->name : t('Create New Board'))

@section('content')
<div class="content-header">
    <h1>
        @if(isset($board))
            <i class="bi bi-pencil"></i> {{ t('Edit Board') }} - {{ $board->name }}
        @else
            <i class="bi bi-plus-lg"></i> {{ t('Create New Board') }}
        @endif
    </h1>

    <a href="{{ route('sitemanager.boards.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> {{ t('Back to List') }}
    </a>
</div>

<form method="POST" action="{{ isset($board) ? route('sitemanager.boards.update', $board) : route('sitemanager.boards.store') }}">
    @csrf
    @if(isset($board))
        @method('PUT')
    @endif
    
    <!-- Basic Information Row -->
    <div class="row">
        <div class="col">
            <div class="card default-form">
                <div class="card-header bg-dark text-white">
                    <h4>{{ t('Basic Information') }}</h4>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="name" class="form-label">{{ t('Board Name') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                id="name" name="name" value="{{ old('name', isset($board) ? $board->name : '') }}" required maxlength="100">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="form-group">
                        <label for="slug" class="form-label">{{ t('Slug') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('slug') is-invalid @enderror" 
                                id="slug" name="slug" value="{{ old('slug', isset($board) ? $board->slug : '') }}" required maxlength="50"
                                pattern="[a-z0-9_]+" title="{{ t('Only lowercase letters, numbers, and underscores are allowed') }}">
                        <div class="form-text">{{ t('Used in URLs and database table names. Only lowercase letters, numbers, and underscores are allowed.') }}</div>
                        <div id="slug-feedback" class="mt-1" style="display: none;"></div>
                        @error('slug')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="form-group">
                        <label for="menu_id" class="form-label">{{ t('Connect to Menu') }}</label>
                        <select class="form-select @error('menu_id') is-invalid @enderror" id="menu_id" name="menu_id">
                            <option value="">{{ t('Select Menu (Optional)') }}</option>
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
                    
                    <div class="form-group">
                        <label for="status" class="form-label">{{ t('Status') }}</label>
                        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                            <option value="active" {{ old('status', isset($board) ? $board->status : 'active') === 'active' ? 'selected' : '' }}>{{ t('Active') }}</option>
                            <option value="inactive" {{ old('status', isset($board) ? $board->status : '') === 'inactive' ? 'selected' : '' }}>{{ t('Inactive') }}</option>
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="form-group">
                        <label for="skin" class="form-label">{{ t('Theme/Skin') }}</label>
                        <select class="form-select @error('skin') is-invalid @enderror" id="skin" name="skin">
                            @if(isset($availableSkins))
                                @foreach($availableSkins as $skinKey => $skinName)
                                    <option value="{{ $skinKey }}" 
                                            {{ old('skin', isset($board) ? $board->skin : 'default') === $skinKey ? 'selected' : '' }}>
                                        {{ $skinName }}
                                    </option>
                                @endforeach
                            @else
                                <option value="default" selected>{{ t('Default') }}</option>
                            @endif
                        </select>
                        <div class="form-text">{{ t('Choose the theme/skin for this board\'s appearance.') }}</div>
                        @error('skin')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col">
            <div class="card default-form">
                <div class="card-header bg-dark text-white">
                    <h4>{{ t('Settings') }}</h4>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="posts_per_page" class="form-label">{{ t('Posts per Page') }}</label>
                        <input type="number" class="form-control @error('posts_per_page') is-invalid @enderror" 
                                id="posts_per_page" name="posts_per_page" 
                                value="{{ old('posts_per_page', isset($board) ? $board->posts_per_page : 20) }}" 
                                min="5" max="100">
                        @error('posts_per_page')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    @if(isset($systemSettings))
                        @foreach($systemSettings as $key => $config)
                            @php
                                $type = $config[0];
                                $label = $config[1];
                                $description = $config[2] ?? '';
                                $options = $config[3] ?? [];
                                $subSection = $options['sub_section'] ?? null;
                                $currentValue = old("settings.{$key}", isset($separatedSettings) ? ($separatedSettings['system'][$key] ?? null) : (isset($board) ? $board->getSetting($key) : null));
                            @endphp
                            
                            <div class="form-group">
                                @if(str_contains($type, 'boolean'))
                                    <div class="form-check">
                                        <input class="form-check-input parent-toggle" type="checkbox" id="{{ $key }}" name="{{ $key }}" 
                                                value="1" {{ old($key, $currentValue) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="{{ $key }}">
                                            {{ $label }} 
                                            <small class="text-muted">({{ $key }})</small>
                                        </label>
                                        @if($description)
                                            <div class="form-text">{{ $description }}</div>
                                        @endif
                                    </div>
                                    
                                    {{-- sub_section이 있는 경우 인라인 서브섹션은 렌더링하지 않음 (카드로만 표시) --}}
                                @else
                                    <label for="settings_{{ $key }}" class="form-label">
                                        {{ $label }} 
                                        <small class="text-muted">({{ $key }})</small>
                                    </label>
                                    @if(str_contains($type, 'integer'))
                                        <input type="number" class="form-control" id="settings_{{ $key }}" name="settings[{{ $key }}]" 
                                                value="{{ $currentValue }}" 
                                                @if(str_contains($type, 'min:')) min="{{ preg_match('/min:(\d+)/', $type, $matches) ? $matches[1] : '' }}" @endif
                                                @if(str_contains($type, 'max:')) max="{{ preg_match('/max:(\d+)/', $type, $matches) ? $matches[1] : '' }}" @endif>
                                    @else
                                        <input type="text" class="form-control" id="settings_{{ $key }}" name="settings[{{ $key }}]" 
                                                value="{{ $currentValue }}">
                                    @endif
                                    @if($description)
                                        <div class="form-text">{{ $description }}</div>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6>{{ t('Custom Settings') }}</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="add-custom-setting">
                            <i class="bi bi-plus"></i> {{ t('Add Setting') }}
                        </button>
                    </div>

                    <div id="custom-settings-container">
                        @if(isset($separatedSettings) && !empty($separatedSettings['custom']))
                            @foreach($separatedSettings['custom'] as $key => $value)
                                <div class="custom-setting-item mb-3">
                                    <div class="row g-2">
                                        <div class="col-5">
                                            <input type="text" class="form-control form-control-sm" 
                                                    name="custom_settings[{{ $loop->index }}][key]" 
                                                    value="{{ $key }}" 
                                                    placeholder="{{ t('Setting key') }}">
                                        </div>
                                        <div class="col-6">
                                            <input type="text" class="form-control form-control-sm" 
                                                    name="custom_settings[{{ $loop->index }}][value]" 
                                                    value="{{ is_array($value) ? json_encode($value) : $value }}" 
                                                    placeholder="{{ t('Setting value') }}">
                                        </div>
                                        <div class="col-1">
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-setting">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @elseif(isset($board) && $board->getCustomSettings())
                            @foreach($board->getCustomSettings() as $key => $value)
                                @if(!isset($systemSettings) || !array_key_exists($key, $systemSettings))
                                    <div class="custom-setting-item mb-3">
                                        <div class="row g-2">
                                            <div class="col-5">
                                                <input type="text" class="form-control form-control-sm" 
                                                        name="custom_settings[{{ $loop->index }}][key]" 
                                                        value="{{ $key }}" 
                                                        placeholder="{{ t('Setting key') }}">
                                            </div>
                                            <div class="col-6">
                                                <input type="text" class="form-control form-control-sm" 
                                                        name="custom_settings[{{ $loop->index }}][value]" 
                                                        value="{{ is_array($value) ? json_encode($value) : $value }}" 
                                                        placeholder="{{ t('Setting value') }}">
                                            </div>
                                            <div class="col-1">
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-setting">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    </div>
                    <div class="form-text">
                        <small>{{ t('Add custom settings that can be accessed in templates using') }} <code>$board->getSetting('key')</code></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col">
            {{-- Dynamic Sub Section Area from systemSettings --}}
            @if(isset($systemSettings))
                @foreach($systemSettings as $key => $config)
                    @php
                        $type = $config[0];
                        $label = $config[1];
                        $options = $config[3] ?? [];
                        $subSection = $options['sub_section'] ?? null;
                    @endphp
                    
                    @if($subSection && str_contains($type, 'boolean'))
                        <div class="card default-form mb-4" id="{{ $key }}_card" style="display: none;">
                            <div class="card-header bg-dark text-white">
                                <h4>{{ $subSection['title'] ?? $label . ' Settings' }}</h4>
                            </div>
                            <div class="card-body">
                                @if(isset($subSection['settings']))
                                    @foreach($subSection['settings'] as $fieldKey => $fieldConfig)
                                        @php
                                            $fieldType = $fieldConfig[0];
                                            $fieldLabel = $fieldConfig[1];
                                            $fieldDescription = $fieldConfig[2] ?? '';
                                            $fieldOptions = $fieldConfig[3] ?? [];
                                            
                                            // Get current value based on field type
                                            if ($fieldKey === 'categories') {
                                                $fieldValue = old($fieldKey, isset($board) && $board->categories ? implode("\n", $board->categories) : '');
                                            } else {
                                                $fieldValue = old("settings.{$fieldKey}", isset($board) ? $board->getSetting($fieldKey, $fieldOptions['default'] ?? '') : ($fieldOptions['default'] ?? ''));
                                            }
                                        @endphp
                                        
                                        <div class="form-group">
                                            @if($fieldType === 'special')
                                                {{-- Special handling for categories field --}}
                                                <label for="{{ $fieldKey }}" class="form-label">
                                                    {{ $fieldLabel }} 
                                                    <small class="text-muted">({{ $fieldKey }})</small>
                                                </label>
                                                @if($fieldOptions['type'] === 'textarea')
                                                    <textarea class="form-control" id="{{ $fieldKey }}" 
                                                                name="{{ $fieldKey }}" 
                                                                rows="{{ $fieldOptions['rows'] ?? 3 }}"
                                                                @if($fieldOptions['placeholder'] ?? null) placeholder="{{ $fieldOptions['placeholder'] }}" @endif>{{ $fieldValue }}</textarea>
                                                @endif
                                            @elseif(str_contains($fieldType, 'boolean'))
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="{{ $fieldKey }}" name="{{ $fieldKey }}" 
                                                            value="1" {{ old($fieldKey, $fieldValue) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="{{ $fieldKey }}">
                                                        {{ $fieldLabel }} 
                                                        <small class="text-muted">({{ $fieldKey }})</small>
                                                    </label>
                                                </div>
                                            @else
                                                <label for="{{ $fieldKey }}" class="form-label">
                                                    {{ $fieldLabel }} 
                                                    <small class="text-muted">({{ $fieldKey }})</small>
                                                </label>
                                                @if($fieldOptions['type'] === 'textarea')
                                                    <textarea class="form-control" id="{{ $fieldKey }}" 
                                                                name="settings[{{ $fieldKey }}]" 
                                                                rows="{{ $fieldOptions['rows'] ?? 3 }}"
                                                                @if($fieldOptions['placeholder'] ?? null) placeholder="{{ $fieldOptions['placeholder'] }}" @endif>{{ $fieldValue }}</textarea>
                                                @elseif($fieldOptions['type'] === 'number')
                                                    <input type="number" class="form-control" id="{{ $fieldKey }}" 
                                                            name="settings[{{ $fieldKey }}]" 
                                                            value="{{ $fieldValue }}"
                                                            @if($fieldOptions['min'] ?? null) min="{{ $fieldOptions['min'] }}" @endif
                                                            @if($fieldOptions['max'] ?? null) max="{{ $fieldOptions['max'] }}" @endif
                                                            @if($fieldOptions['placeholder'] ?? null) placeholder="{{ $fieldOptions['placeholder'] }}" @endif>
                                                @else
                                                    <input type="text" class="form-control" id="{{ $fieldKey }}" 
                                                            name="settings[{{ $fieldKey }}]" 
                                                            value="{{ $fieldValue }}"
                                                            @if($fieldOptions['placeholder'] ?? null) placeholder="{{ $fieldOptions['placeholder'] }}" @endif>
                                                @endif
                                            @endif
                                            
                                            @if($fieldDescription)
                                                <div class="form-text">{{ $fieldDescription }}</div>
                                            @endif
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif
        </div>
    </div>
    
    <div class="d-flex gap-2 mt-4">
        <a href="{{ route('sitemanager.boards.index') }}" class="btn btn-outline-secondary">{{ t('Cancel') }}</a>
        <button type="submit" class="btn btn-danger">
            @if(isset($board))
                <i class="bi bi-check-lg"></i> {{ t('Update') }}
            @else
                <i class="bi bi-plus-lg"></i> {{ t('Create') }}
            @endif
        </button>
    </div>
</form>

@endsection

@push('scripts')
<script>
// Translation strings for JavaScript
const translations = {
    invalidCharactersFound: @json(t('Invalid characters found')),
    suggested: @json(t('Suggested')),
    uppercaseNotAllowed: @json(t('Uppercase letters are not allowed')),
    tryThis: @json(t('Try')),
    spacesNotAllowed: @json(t('Spaces are not allowed')),
    hyphensNotAllowed: @json(t('Hyphens are not allowed (use underscores instead)')),
    slugTooLong: @json(t('Slug is too long (maximum 50 characters)')),
    slugShouldNotStartEnd: @json(t('Slug should not start or end with underscores')),
    multipleUnderscores: @json(t('Multiple consecutive underscores are not recommended')),
    validSlugFormat: @json(t('Valid slug format!')),
    clickToUseThisSuggestion: @json(t('Click to use this suggestion')),
    settingKeyPlaceholder: @json(t('Setting key (e.g., show_name)')),
    settingValuePlaceholder: @json(t('Setting value (e.g., true, hello world)'))
};

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
            messages.push(`${translations.invalidCharactersFound}: <code>${uniqueInvalidChars.join(', ')}</code>`);
            
            // Generate suggestion
            const suggestion = slug.replace(/[^a-z0-9_]/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '');
            if (suggestion !== slug) {
                suggestions.push(`${translations.suggested}: <code>${suggestion}</code>`);
            }
        }
        
        // Check for uppercase letters
        if (/[A-Z]/.test(slug)) {
            isValid = false;
            messages.push(translations.uppercaseNotAllowed);
            suggestions.push(`${translations.tryThis}: <code>${slug.toLowerCase()}</code>`);
        }
        
        // Check for spaces
        if (/\s/.test(slug)) {
            isValid = false;
            messages.push(translations.spacesNotAllowed);
            suggestions.push(`${translations.tryThis}: <code>${slug.replace(/\s+/g, '_')}</code>`);
        }
        
        // Check for hyphens (old format)
        if (/-/.test(slug)) {
            isValid = false;
            messages.push(translations.hyphensNotAllowed);
            suggestions.push(`${translations.tryThis}: <code>${slug.replace(/-/g, '_')}</code>`);
        }
        
        // Check length
        if (slug.length > 50) {
            isValid = false;
            messages.push(translations.slugTooLong);
            suggestions.push(`${translations.tryThis}: <code>${slug.substring(0, 50)}</code>`);
        }
        
        // Check if starts or ends with underscore
        if (slug.startsWith('_') || slug.endsWith('_')) {
            isValid = false;
            messages.push(translations.slugShouldNotStartEnd);
            suggestions.push(`${translations.tryThis}: <code>${slug.replace(/^_+|_+$/g, '')}</code>`);
        }
        
        // Check for multiple consecutive underscores
        if (/__/.test(slug)) {
            isValid = false;
            messages.push(translations.multipleUnderscores);
            suggestions.push(`${translations.tryThis}: <code>${slug.replace(/_+/g, '_')}</code>`);
        }
        
        // Display feedback
        if (isValid && slugPattern.test(slug)) {
            slugInput.classList.add('is-valid');
            feedbackDiv.innerHTML = `
                <div class="text-success">
                    <i class="bi bi-check-circle me-1"></i>
                    ${translations.validSlugFormat}
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
            code.title = translations.clickToUseThisSuggestion;
            code.addEventListener('click', function() {
                slugInput.value = this.textContent;
                slugInput.dataset.manual = 'true';
                validateSlug(this.textContent);
                slugInput.focus();
            });
        });
    }
    
    // Parent-toggle functionality for cards only
    const parentToggles = document.querySelectorAll('.parent-toggle');
    
    function handleParentToggle(checkbox) {
        // Handle card toggles (dynamic cards from systemSettings)
        const cardId = checkbox.id + '_card';
        const cardElement = document.getElementById(cardId);
        if (cardElement) {
            cardElement.style.display = checkbox.checked ? 'block' : 'none';
        }
    }
    
    parentToggles.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            handleParentToggle(this);
        });
        // Set initial state
        handleParentToggle(checkbox);
    });
    
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
                               placeholder="${translations.settingKeyPlaceholder}">
                    </div>
                    <div class="col-6">
                        <input type="text" class="form-control form-control-sm" 
                               name="custom_settings[${customSettingIndex}][value]" 
                               value="${value}" 
                               placeholder="${translations.settingValuePlaceholder}">
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

/* Sub-section styles */
.sub-section {
    background-color: #f8f9fa;
    border-left: 3px solid #dee2e6 !important;
    border-radius: 0.375rem;
    padding: 0.75rem !important;
    margin-top: 0.75rem;
    transition: all 0.3s ease;
}

.sub-section:hover {
    border-left-color: #0d6efd !important;
    background-color: #f0f7ff;
}

.sub-section .form-label {
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 0.375rem;
}

.sub-section .form-control-sm {
    font-size: 0.875rem;
}

.sub-section .form-text {
    font-size: 0.75rem;
    margin-top: 0.25rem;
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
    background-color: var(--bs-gray-100);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='m2.3 6.73.4-.4 1.4-1.4.7-.7-.7-.7L2.7 2.23 2.3 1.83 1.23 2.9 0 4.14l.4.4 1.4 1.4.5.9Z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.form-control.is-invalid {
    border-color: #dc3545;
    background-color: var(--bs-gray-100);
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 5.8 4.4 4.4m0-4.4-4.4 4.4'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.custom-setting-item .form-control {
    background-color: #fff;
}
</style>
@endpush
