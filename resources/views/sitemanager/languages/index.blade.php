@extends('sitemanager::layouts.sitemanager')

@section('title', t('Language Management'))

@section('content')
<div class="container">

    <!-- Header Section - Responsive -->
    <div class="mb-4">
        <!-- Desktop Header -->
        <div class="d-none d-md-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0">
                <a href="{{ route('sitemanager.languages.index') }}" class="text-decoration-none text-dark">
                    <i class="bi bi-translate opacity-75"></i> {{ t('Language Management') }}
                    <span class="ms-2">({{ number_format($languages->total()) }})</span>
                </a>
            </h1>
            <div class="d-flex gap-2">
                <button class="btn btn-warning text-white" onclick="cleanupLanguages()">
                    <i class="bi bi-arrow-clockwise"></i> {{ t('Cleanup Unused') }}
                </button>
            </div>
        </div>

        <!-- Mobile Header -->
        <div class="d-md-none">
            <h4 class="mb-3">
                <a href="{{ route('sitemanager.languages.index') }}" class="text-decoration-none text-dark">
                    <i class="bi bi-translate opacity-75"></i> {{ t('Language Management') }}
                    <span class="ms-2">({{ number_format($languages->total()) }})</span>
                </a>
            </h4>
            <div class="d-grid mb-3">
                <button class="btn btn-warning text-white" onclick="cleanupLanguages()">
                    <i class="bi bi-arrow-clockwise me-2"></i>{{ t('Cleanup Unused') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Search Form -->
    <div class="row mb-4">
        <div class="col-12">
            <form method="GET" action="{{ route('sitemanager.languages.index') }}" class="search-form">
                <div class="row">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="{{ t('Search by key or translation text...') }}" 
                                   value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-3 mb-3 mb-md-0">
                        <select name="status" class="form-select">
                            <option value="">{{ t('All Keys') }}</option>
                            <option value="translated" {{ request('status') == 'translated' ? 'selected' : '' }}>{{ t('Fully Translated') }}</option>
                            <option value="partial" {{ request('status') == 'partial' ? 'selected' : '' }}>{{ t('Partially Translated') }}</option>
                            <option value="untranslated" {{ request('status') == 'untranslated' ? 'selected' : '' }}>{{ t('Untranslated') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="bi bi-search me-2"></i>{{ t('Search') }}
                            </button>
                            @if(request()->hasAny(['search', 'status']))
                                <a href="{{ route('sitemanager.languages.index') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Success Alert -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Main Content Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom-0 py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-primary">
                    <i class="bi bi-list-ul me-2"></i>{{ t('Translation Keys') }}
                </h5>
                <small class="text-muted">{{ t('Total') }}: {{ number_format($languages->total()) }}</small>
            </div>
        </div>
        <div class="card-body p-0">
            <!-- Desktop Table -->
            <div class="d-none d-lg-block">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 border-0">{{ t('Key') }} (English)</th>
                                @foreach(array_slice($availableLanguages, 1) as $code => $name)
                                    <th class="border-0">{{ $name }}</th>
                                @endforeach
                                <th class="pe-4 border-0 text-center">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($languages as $language)
                                <tr>
                                    <td class="ps-4 align-middle">
                                        <code class="bg-light px-2 py-1 rounded">{{ $language->key }}</code>
                                    </td>
                                    @foreach(array_slice($availableLanguages, 1) as $code => $name)
                                        <td class="align-middle">
                                            <input type="text" 
                                                   class="form-control form-control-sm translation-input border-0 bg-light" 
                                                   value="{{ $language->{$code} }}"
                                                   data-language-id="{{ $language->id }}"
                                                   data-field="{{ $code }}"
                                                   placeholder="{{ t('Enter translation') }}"
                                                   style="min-width: 150px;">
                                        </td>
                                    @endforeach
                                    <td class="pe-4 align-middle text-center">
                                        <div class="d-flex justify-content-center gap-1">
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
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($availableLanguages) + 1 }}" class="text-center text-muted py-5">
                                        <i class="bi bi-inbox display-1 text-muted opacity-25"></i>
                                        <p class="mt-3 mb-0">{{ t('No language keys found') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Cards -->
            <div class="d-lg-none p-3">
                @forelse($languages as $language)
                    <div class="card mb-3 border">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h6 class="card-title mb-0">
                                    <code class="bg-light px-2 py-1 rounded">{{ $language->key }}</code>
                                </h6>
                                <div class="d-flex gap-1">
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
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label text-muted small">English (Default)</label>
                                <div class="text-muted">{{ $language->key }}</div>
                            </div>
                            
                            @foreach(array_slice($availableLanguages, 1) as $code => $name)
                                <div class="mb-2">
                                    <label class="form-label text-muted small">{{ $name }}</label>
                                    <input type="text" 
                                           class="form-control form-control-sm translation-input" 
                                           value="{{ $language->{$code} }}"
                                           data-language-id="{{ $language->id }}"
                                           data-field="{{ $code }}"
                                           placeholder="{{ t('Enter translation') }}">
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox display-1 text-muted opacity-25"></i>
                        <p class="mt-3 mb-0">{{ t('No language keys found') }}</p>
                    </div>
                @endforelse
            </div>

            <!-- Pagination -->
            @if($languages->hasPages())
                <div class="card-footer bg-white border-0">
                    <div class="d-flex justify-content-center">
                        {{ $languages->appends(request()->query())->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

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
    const inputs = document.querySelectorAll(`[data-language-id="${languageId}"]`);
    const data = { _token: '{{ csrf_token() }}', _method: 'PUT' };
    
    inputs.forEach(input => {
        data[input.dataset.field] = input.value;
    });
    
    // Show loading state
    const saveButton = document.querySelector(`button[onclick="saveTranslation(${languageId})"]`);
    const originalContent = saveButton.innerHTML;
    saveButton.innerHTML = '<i class="bi bi-arrow-repeat spin"></i>';
    saveButton.disabled = true;
    
    fetch(`{{ url('/sitemanager/languages') }}/${languageId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessToast('{{ t("Translation saved successfully") }}');
        } else {
            showErrorToast(data.message || '{{ t("Error saving translation") }}');
        }
    })
    .catch(error => {
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
</style>
`);

// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.querySelector('.search-form');
    const statusSelect = searchForm.querySelector('select[name="status"]');
    const searchInput = searchForm.querySelector('input[name="search"]');
    
    // Auto-submit when status filter changes
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            searchForm.submit();
        });
    }
    
    // Debounced search for input field
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                if (searchInput.value.length >= 2 || searchInput.value.length === 0) {
                    searchForm.submit();
                }
            }, 500); // 500ms delay
        });
    }
});
</script>
@endsection
