@extends('sitemanager::layouts.sitemanager')

@section('title', t('Language Management'))

@section('content')
<div class="content-header">
    <h1>
        <a href="{{ route('sitemanager.languages.index') }}">
            <i class="bi bi-translate opacity-75"></i> {{ t('Language Management') }}
        </a>

        <span class="count">{{ number_format($languages->total()) }}</span>
    </h1>

    <div class="d-flex gap-2">
        <div class="form-check form-switch pt-1">
            <input class="form-check-input" type="checkbox" id="traceToggle">
            <label class="form-check-label" for="traceToggle">
                {{ t('Trace Mode') }}
            </label>
        </div>
        @if(auth()->user()->level === 255)
        <button class="btn-default" onclick="clearLocations()">
            <i class="bi bi-geo"></i> {{ t('Clear Locations') }}
        </button>
        @endif
        <button class="btn-default" onclick="cleanupLanguages()">
            <i class="bi bi-arrow-clockwise"></i> {{ t('Cleanup Unused') }}
        </button>
    </div>
</div>

<!-- Search Form -->
<form method="GET" action="{{ route('sitemanager.languages.index') }}" class="search-form">
    <input type="text" name="search" class="form-control" 
            placeholder="{{ t('Search by key, translation, or location...') }}" 
            value="{{ request('search') }}"
            title="{{ t('Press Enter to search') }}">

    <input type="text" name="location" class="form-control" 
            placeholder="{{ t('Filter by location...') }}" 
            value="{{ request('location') }}"
            list="location-suggestions"
            title="{{ t('Select from suggestions or press Enter to search') }}">
    <datalist id="location-suggestions">
        @foreach($existingLocations as $location)
            <option value="{{ $location }}">
        @endforeach
    </datalist>

    <select name="status" class="form-select">
        <option value="">{{ t('All Keys') }}</option>
        <option value="translated" {{ request('status') == 'translated' ? 'selected' : '' }}>{{ t('Fully Translated') }}</option>
        <option value="partial" {{ request('status') == 'partial' ? 'selected' : '' }}>{{ t('Partially Translated') }}</option>
        <option value="untranslated" {{ request('status') == 'untranslated' ? 'selected' : '' }}>{{ t('Untranslated') }}</option>
        <option value="no_location" {{ request('status') == 'no_location' ? 'selected' : '' }}>{{ t('No Location') }}</option>
    </select>

    <button type="submit" class="btn btn-primary flex-fill" title="{{ t('Search (Press Enter)') }}">
        <i class="bi bi-search me-2"></i>{{ t('Search') }}
    </button>
    @if(request()->hasAny(['search', 'location', 'status']))
        <a href="{{ route('sitemanager.languages.index') }}" class="btn btn-outline-secondary" title="{{ t('Clear all filters') }}">
            <i class="bi bi-x-circle"></i>
        </a>
    @endif
</form>

@if(request()->hasAny(['search', 'location', 'status']))
    <div class="alert alert-info mb-3">
        {{ t('Filtered') }}: 
        @if(request('search'))
            {{ t('search') }}: "{{ request('search') }}"
        @endif
        @if(request('location'))
            @if(request('search')), @endif
            {{ t('location') }}: "{{ request('location') }}"
        @endif
        @if(request('status'))
            @if(request('search') || request('location')), @endif
            {{ t('status') }}: {{ request('status') }}
        @endif
    </div>
@endif

<div class="table-responsive">
    <table class="table table-hover table-striped">
        <thead>
            <tr>
                <th>{{ t('Key') }} (English)</th>
                @foreach(array_slice($availableLanguages, 1) as $code => $name)
                    <th>{{ $name }}</th>
                @endforeach
                <th>{{ t('Location') }}</th>
                <th class="text-end">{{ t('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($languages as $language)
                <tr>
                    <td>
                        <code>{{ $language->key }}</code>
                    </td>
                    @foreach(array_slice($availableLanguages, 1) as $code => $name)
                        <td width="25%">
                            <input type="text" 
                                    class="form-control form-control-sm translation-input" 
                                    value="{{ $language->{$code} }}"
                                    data-language-id="{{ $language->id }}"
                                    data-field="{{ $code }}"
                                    placeholder="{{ t('Enter translation') }}"
                                    style="min-width: 150px;">
                        </td>
                    @endforeach
                    <td>
                        <div class="location-display" data-language-id="{{ $language->id }}">
                            @if($language->location)
                                <div class="location-badges mb-1">
                                    @foreach($language->getLocationArray() as $loc)
                                        <span class="badge bg-info me-1 mb-1">{{ $loc }}</span>
                                    @endforeach
                                </div>
                                <button class="btn btn-sm btn-outline-secondary rounded-circle location-edit-btn" 
                                        onclick="toggleLocationEdit({{ $language->id }})"
                                        title="{{ t('Edit location') }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            @else
                                <button class="btn btn-sm btn-outline-primary location-add-btn" 
                                        onclick="toggleLocationEdit({{ $language->id }})"
                                        title="{{ t('Add location') }}">
                                    <i class="bi bi-plus"></i> {{ t('Add location') }}
                                </button>
                            @endif
                            
                            <div class="location-edit-form" style="display: none;">
                                <div class="input-group input-group-sm" nowrap>
                                    <input type="text" 
                                            class="form-control location-input" 
                                            value="{{ $language->location }}"
                                            data-language-id="{{ $language->id }}"
                                            placeholder="{{ t('Enter location') }}">
                                    <button class="btn btn-success" onclick="saveLocation({{ $language->id }})" title="{{ t('Save') }}">
                                        <i class="bi bi-check"></i>
                                    </button>
                                    <button class="btn btn-secondary" onclick="cancelLocationEdit({{ $language->id }})" title="{{ t('Cancel') }}">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                                <div class="location-preview mt-1"></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-end actions" nowrap>
                        <button class="btn btn-sm btn-outline-success" 
                                onclick="saveTranslation({{ $language->id }})"
                                title="{{ t('Save') }}">
                            <i class="bi bi-check"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" 
                                onclick="deleteLanguage({{ $language->id }})"
                                title="{{ t('Delete') }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($availableLanguages) + 2 }}" class="text-center text-muted py-5">
                        <i class="bi bi-inbox display-1 text-muted opacity-25"></i>
                        <p class="mt-3 mb-0">{{ t('No language keys found') }}</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Pagination -->
@if($languages->hasPages())
    {{ $languages->appends(request()->query())->links('sitemanager::pagination.default') }}
@endif

<!-- Toast Notifications -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="success-toast" class="toast align-items-center text-white bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi bi-check-circle me-2"></i><span id="success-message"></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
    <div id="error-toast" class="toast align-items-center text-white bg-danger border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi bi-exclamation-circle me-2"></i><span id="error-message"></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
// Toast functions
function showSuccessToast(message) {
    document.getElementById('success-message').textContent = message;
    const toast = new bootstrap.Toast(document.getElementById('success-toast'));
    toast.show();
}

function showErrorToast(message) {
    document.getElementById('error-message').textContent = message;
    const toast = new bootstrap.Toast(document.getElementById('error-toast'));
    toast.show();
}

function saveTranslation(languageId) {
    const inputs = document.querySelectorAll(`[data-language-id="${languageId}"].translation-input`);
    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('_method', 'PUT');
    
    inputs.forEach(input => {
        if (input.dataset.field) {
            formData.append(input.dataset.field, input.value);
        }
    });
    
    // Show loading state
    const saveButton = document.querySelector(`button[onclick="saveTranslation(${languageId})"]`);
    const originalContent = saveButton.innerHTML;
    saveButton.innerHTML = '<i class="bi bi-arrow-repeat spin"></i>';
    saveButton.disabled = true;
    
    fetch(`{{ url('/sitemanager/languages') }}/${languageId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                // If response is not JSON, check if it's a redirect (success)
                if (response.status === 200 || response.redirected) {
                    return { success: true };
                }
                throw new Error('Invalid response format');
            }
        });
    })
    .then(data => {
        if (data.success !== false) {
            showSuccessToast('{{ t("Translation saved successfully") }}');
        } else {
            showErrorToast(data.message || '{{ t("Error saving translation") }}');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorToast('{{ t("Error saving translation") }}');
    })
    .finally(() => {
        saveButton.innerHTML = originalContent;
        saveButton.disabled = false;
    });
}

function deleteLanguage(languageId) {
    if (confirm('{{ t("Are you sure you want to delete this language key?") }}')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `{{ url('/sitemanager/languages') }}/${languageId}`;
        form.innerHTML = `
            @csrf
            @method('DELETE')
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function cleanupLanguages() {
    if (confirm('{{ t("This will remove unused language keys. Are you sure?") }}')) {
        const button = document.querySelector('button[onclick="cleanupLanguages()"]');
        const originalContent = button.innerHTML;
        button.innerHTML = '<i class="bi bi-arrow-repeat spin me-2"></i>{{ t("Processing") }}...';
        button.disabled = true;
        
        fetch('{{ route("sitemanager.languages.cleanup") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccessToast(data.message || '{{ t("Cleanup completed") }}');
                setTimeout(() => location.reload(), 1000);
            } else {
                showErrorToast(data.message || '{{ t("Error during cleanup") }}');
            }
        })
        .catch(error => {
            showErrorToast('{{ t("Error during cleanup") }}');
        })
        .finally(() => {
            button.innerHTML = originalContent;
            button.disabled = false;
        });
    }
}

// Add spinning animation for loading indicators
document.head.insertAdjacentHTML('beforeend', `
<style>
.spin {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.location-display {
    min-width: 200px;
}
.location-edit-form {
    margin-top: 8px;
}
.location-input {
    transition: border-color 0.2s ease;
}
.location-input:focus {
    border-color: #0d6efd !important;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}
.location-badges {
    max-width: 300px;
    word-wrap: break-word;
}
.location-preview {
    margin-top: 4px;
}
.location-add-btn, .location-edit-btn {
    font-size: 0.75rem;
}
</style>
`);

// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.querySelector('.search-form');
    const statusSelect = searchForm.querySelector('select[name="status"]');
    const searchInput = searchForm.querySelector('input[name="search"]');
    const locationInput = searchForm.querySelector('input[name="location"]');
    const traceToggle = document.getElementById('traceToggle');
    
    // Load trace status on page load
    loadTraceStatus();
    
    // Auto-submit when status filter changes
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            searchForm.submit();
        });
    }
    
    // Enter key to submit search form
    function setupEnterKeySearch(inputElement) {
        if (!inputElement) return;
        
        inputElement.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchForm.submit();
            }
        });
    }
    
    // Optional: Auto-submit when selecting from datalist (location suggestions)
    if (locationInput) {
        locationInput.addEventListener('change', function(e) {
            // 자동완성에서 값을 선택했을 때만 자동 검색
            const datalist = document.getElementById('location-suggestions');
            const options = Array.from(datalist.options).map(option => option.value);
            
            if (options.includes(e.target.value)) {
                searchForm.submit();
            }
        });
    }
    
    setupEnterKeySearch(searchInput);
    setupEnterKeySearch(locationInput);
    
    // Trace toggle functionality
    if (traceToggle) {
        traceToggle.addEventListener('change', function() {
            toggleTrace(this.checked);
        });
    }
    
    // Location input real-time preview
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('location-input')) {
            updateLocationPreview(e.target);
        }
    });
    
    // Enter key to save location
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.classList.contains('location-input')) {
            e.preventDefault();
            const languageId = e.target.getAttribute('data-language-id');
            if (languageId) {
                saveLocation(parseInt(languageId));
            }
        }
        if (e.key === 'Escape' && e.target.classList.contains('location-input')) {
            e.preventDefault();
            const languageId = e.target.getAttribute('data-language-id');
            if (languageId) {
                cancelLocationEdit(parseInt(languageId));
            }
        }
    });
});

// Load trace status
function loadTraceStatus() {
    fetch('/sitemanager/languages/trace-status', {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.enabled !== undefined) {
            const traceToggle = document.getElementById('traceToggle');
            if (traceToggle) {
                traceToggle.checked = data.enabled;
            }
        }
    })
    .catch(error => {
        console.error('Error loading trace status:', error);
    });
}

// Toggle trace mode
function toggleTrace(enabled) {
    fetch('/sitemanager/languages/toggle-trace', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ enabled: enabled })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessToast(data.message || '{{ t("Trace mode updated successfully") }}');
        } else {
            showErrorToast(data.message || '{{ t("Failed to toggle trace mode") }}');
            // 실패시 토글 상태 원복
            const traceToggle = document.getElementById('traceToggle');
            if (traceToggle) {
                traceToggle.checked = !enabled;
            }
        }
    })
    .catch(error => {
        showErrorToast('{{ t("Error occurred while toggling trace mode") }}');
        console.error('Error:', error);
        // 에러시 토글 상태 원복
        const traceToggle = document.getElementById('traceToggle');
        if (traceToggle) {
            traceToggle.checked = !enabled;
        }
    });
}

// Clear all locations
function clearLocations() {
    if (!confirm('{{ t("Are you sure you want to clear all location information?") }}')) {
        return;
    }
    
    fetch('/sitemanager/languages/clear-locations', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessToast(data.message || '{{ t("All locations cleared successfully") }}');
            setTimeout(() => location.reload(), 1000);
        } else {
            showErrorToast(data.message || '{{ t("Failed to clear locations") }}');
        }
    })
    .catch(error => {
        showErrorToast('{{ t("Error occurred while clearing locations") }}');
        console.error('Error:', error);
    });
}

// Update location badges after save
function updateLocationBadges(languageId) {
    const locationDisplay = document.querySelector(`.location-display[data-language-id="${languageId}"]`);
    if (!locationDisplay) return;
    
    const locationInput = locationDisplay.querySelector('.location-input');
    if (!locationInput) return;
    
    const locationValue = locationInput.value.trim();
    const badgesContainer = locationDisplay.querySelector('.location-badges');
    const addBtn = locationDisplay.querySelector('.location-add-btn');
    const editBtn = locationDisplay.querySelector('.location-edit-btn');
    
    if (locationValue) {
        const locations = locationValue.split(',').map(loc => loc.trim()).filter(loc => loc);
        
        if (badgesContainer) {
            badgesContainer.innerHTML = locations.map(loc => 
                `<span class="badge bg-info me-1 mb-1">${loc}</span>`
            ).join('');
            badgesContainer.style.display = 'block';
        } else {
            // 새로 badges container 생성
            const newBadgesContainer = document.createElement('div');
            newBadgesContainer.className = 'location-badges mb-1';
            newBadgesContainer.innerHTML = locations.map(loc => 
                `<span class="badge bg-info me-1 mb-1">${loc}</span>`
            ).join('');
            locationDisplay.insertBefore(newBadgesContainer, locationDisplay.firstChild);
        }
        
        // Add 버튼을 Edit 버튼으로 변경
        if (addBtn) {
            addBtn.style.display = 'none';
        }
        if (editBtn) {
            editBtn.style.display = 'inline-block';
        } else {
            // Edit 버튼이 없으면 새로 생성
            const newEditBtn = document.createElement('button');
            newEditBtn.className = 'btn btn-sm btn-outline-secondary location-edit-btn';
            newEditBtn.onclick = () => toggleLocationEdit(languageId);
            newEditBtn.title = '{{ t("Edit location") }}';
            newEditBtn.innerHTML = '<i class="bi bi-pencil"></i>';
            locationDisplay.appendChild(newEditBtn);
        }
    } else {
        // 빈 값이면 badges 숨기고 Add 버튼 표시
        if (badgesContainer) {
            badgesContainer.style.display = 'none';
        }
        if (addBtn) {
            addBtn.style.display = 'inline-block';
        }
        if (editBtn) {
            editBtn.style.display = 'none';
        }
    }
}

// Real-time location preview
function updateLocationPreview(inputElement) {
    const locationValue = inputElement.value.trim();
    const previewContainer = inputElement.closest('.location-edit-form')?.querySelector('.location-preview');
    
    if (previewContainer) {
        if (locationValue) {
            const locations = locationValue.split(',').map(loc => loc.trim()).filter(loc => loc);
            previewContainer.innerHTML = locations.map(loc => 
                `<span class="badge bg-secondary me-1 mb-1">${loc}</span>`
            ).join('');
            previewContainer.style.display = 'block';
        } else {
            previewContainer.innerHTML = '';
            previewContainer.style.display = 'none';
        }
    }
}

// Toggle location edit form
function toggleLocationEdit(languageId) {
    const locationDisplay = document.querySelector(`.location-display[data-language-id="${languageId}"]`);
    if (!locationDisplay) return;
    
    const editForm = locationDisplay.querySelector('.location-edit-form');
    const addBtn = locationDisplay.querySelector('.location-add-btn');
    const editBtn = locationDisplay.querySelector('.location-edit-btn');
    const badges = locationDisplay.querySelector('.location-badges');
    
    if (editForm.style.display === 'none') {
        editForm.style.display = 'block';
        if (addBtn) addBtn.style.display = 'none';
        if (editBtn) editBtn.style.display = 'none';
        if (badges) badges.style.display = 'none';
        
        // 입력 필드에 포커스
        const input = editForm.querySelector('.location-input');
        if (input) {
            input.focus();
            updateLocationPreview(input);
        }
    } else {
        cancelLocationEdit(languageId);
    }
}

// Cancel location edit
function cancelLocationEdit(languageId) {
    const locationDisplay = document.querySelector(`.location-display[data-language-id="${languageId}"]`);
    if (!locationDisplay) return;
    
    const editForm = locationDisplay.querySelector('.location-edit-form');
    const addBtn = locationDisplay.querySelector('.location-add-btn');
    const editBtn = locationDisplay.querySelector('.location-edit-btn');
    const badges = locationDisplay.querySelector('.location-badges');
    const input = editForm.querySelector('.location-input');
    
    editForm.style.display = 'none';
    
    // 원래 값으로 복원
    if (input) {
        input.value = input.getAttribute('value') || '';
    }
    
    // 버튼과 badges 상태 복원
    const hasLocation = badges && badges.innerHTML.trim();
    if (hasLocation) {
        if (badges) badges.style.display = 'block';
        if (editBtn) editBtn.style.display = 'inline-block';
        if (addBtn) addBtn.style.display = 'none';
    } else {
        if (addBtn) addBtn.style.display = 'inline-block';
        if (editBtn) editBtn.style.display = 'none';
    }
}

// Save location
function saveLocation(languageId) {
    const locationDisplay = document.querySelector(`.location-display[data-language-id="${languageId}"]`);
    if (!locationDisplay) return;
    
    const input = locationDisplay.querySelector('.location-input');
    if (!input) return;
    
    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('_method', 'PUT');
    formData.append('location', input.value.trim());
    
    // 로딩 상태
    const saveBtn = locationDisplay.querySelector('.location-edit-form .btn-success');
    const originalContent = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i>';
    saveBtn.disabled = true;
    
    fetch(`{{ url('/sitemanager/languages') }}/${languageId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                if (response.status === 200 || response.redirected) {
                    return { success: true };
                }
                throw new Error('Invalid response format');
            }
        });
    })
    .then(data => {
        if (data.success !== false) {
            showSuccessToast('{{ t("Location saved successfully") }}');
            
            // input의 value 속성 업데이트
            input.setAttribute('value', input.value);
            
            // UI 업데이트
            updateLocationBadges(languageId);
            cancelLocationEdit(languageId);
        } else {
            showErrorToast(data.message || '{{ t("Error saving location") }}');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorToast('{{ t("Error saving location") }}');
    })
    .finally(() => {
        saveBtn.innerHTML = originalContent;
        saveBtn.disabled = false;
    });
}
</script>
@endsection
