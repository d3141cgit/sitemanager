@extends('sitemanager::layouts.sitemanager')

@section('title', isset($menu) ? t('Edit Menu') . ' - ' . $menu->title : t('Add New Menu'))

@section('content')
<div class="card default-form default-form">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h4>
            @if(isset($menu))
                <i class="bi bi-pencil"></i> {{ t('Edit Menu') }}
            @else
                <i class="bi bi-plus"></i> {{ t('Add New Menu') }}
            @endif
        </h4>
        <a href="{{ route('sitemanager.menus.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> {{ t('Back to List') }}
        </a>
    </div>

    <form method="POST" action="{{ isset($menu) ? route('sitemanager.menus.update', $menu) : route('sitemanager.menus.store') }}" enctype="multipart/form-data">
        @csrf
        @if(isset($menu))
            @method('PUT')
        @endif
        
        <div class="card-body">
            <div class="row">
                <!-- Left Column: Menu Information -->
                <div class="col">
                    <h6 class="mb-3 text-primary">
                        <i class="bi bi-info-circle me-2"></i>{{ t('Menu Information') }}
                    </h6>
                    
                    <div class="form-group">
                        <label for="title" class="form-label">{{ t('Menu Title') }} *</label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', isset($menu) ? $menu->title : '') }}" required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="description" class="form-label">{{ t('Description') }}</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="{{ t('Enter a short description') }}">{{ old('description', isset($menu) ? $menu->description : '') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            {{ t('Optional. This description helps identify the menu\'s purpose and may be used as the SEO description.') }}
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="type" class="form-label">{{ t('Menu Type') }} *</label>
                        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                            <option value="">{{ t('Select menu type') }}</option>
                            @foreach(\SiteManager\Models\Menu::getAvailableTypes() as $typeValue => $typeLabel)
                                <option value="{{ $typeValue }}" {{ old('type', isset($menu) ? $menu->type : '') == $typeValue ? 'selected' : '' }}>{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Route Selection (for route type) -->
                    <div class="form-group" id="route-select-container" style="display: none;">
                        <label for="route-select" class="form-label">{{ t('Available Routes') }}</label>
                        <select class="form-select" id="route-select">
                            <option value="">{{ t('Select a route') }}</option>
                            @if(isset($availableRoutes) && count($availableRoutes) > 0)
                                @foreach($availableRoutes as $route)
                                    @php
                                        $isSelected = false;
                                        if (isset($menu) && $menu->target) {
                                            // 일반 라우트 이름과 직접 비교
                                            if ($route['name'] === $menu->target) {
                                                $isSelected = true;
                                            }
                                            // 커스텀 경로의 경우 기본 라우트 이름과 비교
                                            elseif (isset($baseRouteName) && $route['name'] === $baseRouteName) {
                                                $isSelected = true;
                                            }
                                        }
                                    @endphp
                                    <option value="{{ $route['name'] }}" 
                                            data-uri="{{ $route['uri'] }}"
                                            {{ $isSelected ? 'selected' : '' }}>
                                        {{ $route['description'] }} ({{ $route['name'] }})
                                    </option>
                                @endforeach
                            @else
                                <option value="" disabled>{{ t('No routes available') }}</option>
                            @endif
                        </select>
                        <div class="form-text">
                            {{ t('Choose from available Laravel routes above.') }}
                            @if(isset($availableRoutes))
                                <small class="text-muted">({{ count($availableRoutes) }} {{ t('routes found') }})</small>
                            @endif
                        </div>
                        
                        @if(isset($menu) && $menu->type === 'route' && $menu->target)
                            @if(isset($currentRouteExists) && !$currentRouteExists)
                                <div class="alert alert-warning mt-2" id="invalid-route-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>{{ t('Warning') }}:</strong> {{ t('The current route') }} "<code>{{ $menu->target }}</code>" {{ t('no longer exists in the application.') }}
                                    <br>
                                    <small class="text-muted">
                                        {{ t('This route may have been removed or renamed. Please select a new route from the list above.') }}
                                    </small>
                                </div>
                            @endif
                        @endif
                    </div>

                    <div class="form-group" id="target-container">
                        <label for="target" class="form-label">{{ t('Target') }}</label>
                        <input type="text" class="form-control @error('target') is-invalid @enderror" id="target" name="target" value="{{ old('target', isset($menu) ? $menu->target : '') }}" placeholder="">
                        @error('target')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text" id="target-help">{{ t('Please select menu type first.') }}</div>
                        
                        @if(isset($menu) && $menu->type === 'route' && $menu->target)
                            @if(isset($currentRouteExists) && !$currentRouteExists)
                                <div class="alert alert-danger mt-2" id="target-invalid-route-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>{{ t('Invalid Route') }}:</strong> "<code>{{ $menu->target }}</code>" {{ t('does not exist.') }}
                                    <br>
                                    <small>
                                        {{ t('This menu will not function properly. Please switch to route type and select a valid route, or change the menu type to URL and provide a full URL.') }}
                                    </small>
                                </div>
                            @endif
                        @endif
                    </div>

                    <div class="form-group">
                        <label for="parent_id" class="form-label">{{ t('Parent Menu') }}</label>
                        <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id">
                            <option value="">{{ t('Root Menu (Creates New Section)') }}</option>
                            @php
                                $allMenus = \SiteManager\Models\Menu::orderBy('section')->orderBy('_lft')->get();
                                $currentMenuId = isset($menu) ? $menu->id : null;
                            @endphp
                            @foreach($allMenus as $parentMenu)
                                @if($currentMenuId != $parentMenu->id)
                                    <option value="{{ $parentMenu->id }}" 
                                            data-section="{{ $parentMenu->section }}"
                                            {{ old('parent_id', isset($menu) ? $menu->parent_id : '') == $parentMenu->id ? 'selected' : '' }}>
                                        {!! str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', $parentMenu->depth) !!}{{ $parentMenu->title }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        @error('parent_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            {{ t('Select a parent menu to inherit its section, or leave empty to create a new section.') }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="hidden" class="form-label">{{ t('Hidden Menu') }}</label>
                        <div class="form-check form-switch">
                            <!-- Hidden field to ensure unchecked checkbox sends 0 value -->
                            <input type="hidden" name="hidden" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="hidden" name="hidden" value="1" {{ old('hidden', isset($menu) ? $menu->hidden : false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="hidden">
                                {{ t('Hidden') }}
                            </label>
                        </div>
                        <div class="form-text">{{ t('Hidden menus will not be displayed in navigation.') }}</div>
                    </div>

                    <!-- Images Section -->
                    <div class="form-group">
                        <label class="form-label">{{ t('Menu Images') }}</label>
                        <div id="images-container">
                            @php
                                $imageCategories = \SiteManager\Models\Menu::getImageCategories();
                                $existingImages = old('images', isset($menu) ? $menu->images : []);
                            @endphp
                            @if($existingImages)
                                @foreach($existingImages as $category => $imageData)
                                    <div class="image-item mb-3 border p-2 rounded">
                                        <div class="input-group input-group-sm">
                                            <select class="form-select form-select-sm" name="images[{{ $loop->index }}][category]">
                                                @foreach($imageCategories as $catKey => $catLabel)
                                                    <option value="{{ $catKey }}" {{ $category == $catKey ? 'selected' : '' }}>{{ $catLabel }}</option>
                                                @endforeach
                                            </select>

                                            <input type="file" class="form-control form-control-sm image-upload" 
                                                    name="images[{{ $loop->index }}][file]" 
                                                    accept="image/*">
                                        
                                            <button type="button" class="btn btn-light btn-sm text-danger remove-image">
                                                <i class="bi bi-trash"></i> Remove
                                            </button>
                                        </div>

                                        @if(!empty($imageData['url']))
                                            <input type="hidden" name="images[{{ $loop->index }}][existing_url]" value="{{ $imageData['url'] }}">
                                            <div class="mt-2">
                                                @php
                                                    // FileUploadService를 사용하여 S3/로컬을 자동으로 구분
                                                    $imageUrl = \SiteManager\Services\FileUploadService::url($imageData['url']);
                                                @endphp
                                                <img src="{{ $imageUrl }}" alt="{{ $category }}" class="img-thumbnail existing-preview">

                                                <small class="text-muted ms-2">{{ basename($imageData['url']) }}</small>
                                            </div>
                                        @endif

                                        <div class="image-preview mt-2" style="display: none;">
                                            <img class="img-thumbnail">
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        <button type="button" class="btn-default btn-default-sm" id="add-image">
                            <i class="bi bi-plus"></i> {{ t('Add Image') }}
                        </button>
                        <div class="form-text">{{ t('Upload images for different purposes (thumbnail, header, SEO, etc.)') }}</div>
                    </div>
                </div>

                <!-- Right Column: Permissions -->
                <div class="col">
                    @if(isset($menu))
                    <h6 class="mb-3 text-primary">
                        <i class="bi bi-shield-lock me-2"></i>{{ t('Permissions') }}
                    </h6>
                    
                    <!-- Basic Permission -->
                    <div class="form-group">
                        <label class="form-label">{{ t('Basic Permission') }}</label>
                        <div class="permission-list">
                            @php
                                $basicPermissions = config('permissions.menu');
                                $currentPermission = isset($menu) ? $menu->permission : 0;
                            @endphp
                            @foreach($basicPermissions as $value => $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                            name="permission[]" value="{{ $value }}"
                                            {{ ($currentPermission & $value) ? 'checked' : '' }}>
                                    <label class="form-check-label">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Level Permission -->
                    <div class="form-group">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label">{{ t('Level Permission') }}</label>
                            <button type="button" class="btn-default btn-default-sm" onclick="addPermLevel()">
                                + {{ t('Add Level') }}
                            </button>
                        </div>
                        <div id="level-wrap">
                            @if(isset($menuPermissions['levels']))
                                @foreach($menuPermissions['levels'] as $index => $levelData)
                                    <div id="level-perm-{{ $index }}" class="permission-group mb-3 p-3 border rounded">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong>{{ t('Level') }} {{ $levelData['level'] }}</strong>
                                            <button type="button" class="btn-outline-default btn-default-sm" 
                                                    onclick="removePermLevel({{ $index }})">
                                                - {{ t('Delete') }}
                                            </button>
                                        </div>
                                        <input type="hidden" name="level_permissions[{{ $index }}][level]" value="{{ $levelData['level'] }}">
                                        <div class="permission-list">
                                            @foreach($basicPermissions as $value => $label)
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" 
                                                            name="level_permissions[{{ $index }}][permissions][]" value="{{ $value }}"
                                                            {{ ($levelData['permission'] & $value) ? 'checked' : '' }}>
                                                    <label class="form-check-label">{{ $label }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    <!-- Group Permission -->
                    @if(\SiteManager\Models\Group::count() > 0)
                    <div class="form-group">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">{{ t('Group Permission') }}</label>
                            <button type="button" class="btn-default btn-default-sm" onclick="addPermGroup()">
                                + {{ t('Add Group') }}
                            </button>
                        </div>
                        <div id="group-wrap">
                            @if(isset($menuPermissions['groups']))
                                @foreach($menuPermissions['groups'] as $index => $groupData)
                                    <div id="group-perm-{{ $index }}" class="permission-group mb-3 p-3 border rounded">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong>{{ $groupData['name'] }}</strong>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="removePermGroup({{ $index }})">
                                                - {{ t('Delete') }}
                                            </button>
                                        </div>
                                        <input type="hidden" name="group_permissions[{{ $index }}][group_id]" value="{{ $groupData['group_id'] }}">
                                        <div class="permission-list">
                                            @foreach($basicPermissions as $value => $label)
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" 
                                                            name="group_permissions[{{ $index }}][permissions][]" value="{{ $value }}"
                                                            {{ ($groupData['permission'] & $value) ? 'checked' : '' }}>
                                                    <label class="form-check-label">{{ $label }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                    @endif

                    <!-- Administrator Permission -->
                    <div class="form-group">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">{{ t('Administrator Permission') }}</label>
                            <button type="button" class="btn-default btn-default-sm" onclick="addPermAdmin()">
                                + {{ t('Add Administrator') }}
                            </button>
                        </div>
                        <div id="admin-wrap">
                            @if(isset($menuPermissions['admins']))
                                @foreach($menuPermissions['admins'] as $index => $adminData)
                                    <div id="admin-perm-{{ $index }}" class="permission-group mb-3 p-3 border rounded">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <strong>{{ $adminData['name'] }} ({{ $adminData['username'] }})</strong>
                                                <small class="text-muted d-block">{{ t('All permissions') }} ({{ implode(', ', config('permissions.menu')) }})</small>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="removePermAdmin({{ $index }})">
                                                - {{ t('Delete') }}
                                            </button>
                                        </div>
                                        <input type="hidden" name="admin_permissions[{{ $index }}][member_id]" value="{{ $adminData['member_id'] }}">
                                        <input type="hidden" name="admin_permissions[{{ $index }}][permissions][]" value="255">
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    @else
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        {{ t('Permissions can only be set for existing menus. Save the menu first to configure permissions.') }}
                    </div>
                    @endif


                    <div class="form-group border-top pt-3 mt-3">
                        <label for="search_content" class="form-label">{{ t('Search Content') }}</label>
                        <textarea class="form-control @error('search_content') is-invalid @enderror" id="search_content" name="search_content" rows="12" placeholder="{{ t('Content for search indexing. Leave empty to auto-extract from view file.') }}">{{ old('search_content', isset($menu) ? $menu->search_content : '') }}</textarea>
                        @error('search_content')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            {{ t('Optional. Text content used for site search. If empty, it will be automatically extracted from the linked view file.') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer">
            <a href="{{ route('sitemanager.menus.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle"></i> {{ t('Cancel') }}
            </a>

            @if(isset($menu))
                @php
                    $hasChildren = $menu->children()->count() > 0;
                @endphp
                
                @if(!$hasChildren)
                    <button type="button" 
                            class="btn btn-outline-danger"
                            id="menu-delete-btn" 
                            data-delete-url="{{ route('sitemanager.menus.destroy', $menu->id) }}"
                            title="{{ t('Delete Menu') }}">
                        <i class="bi bi-trash"></i> {{ t('Delete') }}
                    </button>
                @endif
            @endif

            <button type="submit" class="btn btn-danger">
                <i class="bi bi-check-circle"></i> 
                @if(isset($menu))
                    {{ t('Update') }}
                @else
                    {{ t('Create') }}
                @endif
            </button>
        </div>
    </form>
</div>

<!-- Member Selector Modal -->
<div class="modal fade" id="memberSelectorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ t('Select Administrator') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <input type="text" id="memberSearch" class="form-control" 
                           placeholder="{{ t('Search by name or ID...') }}" 
                           onkeyup="searchMembers()">
                </div>
                <div id="memberList" class="member-list" style="max-height: 300px; overflow-y: auto;">
                    <div class="text-muted">{{ t('Please enter search terms.') }}</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ t('Cancel') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
@endpush

@push('scripts')
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteBtn = document.getElementById('menu-delete-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            Swal.fire({
                title: '{{ t("Menu Delete Confirmation") }}',
                text: '{{ t("Are you sure you want to delete this menu?") }}',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '{{ t("Delete") }}',
                cancelButtonText: '{{ t("Cancel") }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    const url = this.dataset.deleteUrl;
                    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                    // 동적으로 폼 생성해서 제출 (중첩 폼 문제 회피)
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = url;
                    form.style.display = 'none';

                    const tokenInput = document.createElement('input');
                    tokenInput.type = 'hidden';
                    tokenInput.name = '_token';
                    tokenInput.value = csrf;
                    form.appendChild(tokenInput);

                    const methodInput = document.createElement('input');
                    methodInput.type = 'hidden';
                    methodInput.name = '_method';
                    methodInput.value = 'DELETE';
                    form.appendChild(methodInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    }

    // Initialize Select2 for route and parent menu selects
    $('#route-select').select2({
        theme: 'bootstrap-5',
        placeholder: '{{ t("Search for a route...") }}',
        allowClear: true,
        width: '100%'
    });
    
    $('#parent_id').select2({
        theme: 'bootstrap-5',
        placeholder: '{{ t("Search for parent menu or leave empty for new section...") }}',
        allowClear: true,
        width: '100%',
        templateResult: function(option) {
            if (!option.id) return option.text;
            
            // Extract section and depth info from data attributes
            const $option = $(option.element);
            const text = option.text;
            
            // Create formatted display
            return $('<span>' + text + '</span>');
        }
    });

    // Get elements
    const typeSelect = document.getElementById('type');
    const targetField = document.getElementById('target');
    const targetContainer = document.getElementById('target-container');
    const routeSelect = document.getElementById('route-select');
    const routeContainer = document.getElementById('route-select-container');
    const targetHelp = document.getElementById('target-help');
    
    if (!typeSelect) {
        return;
    }
    
    function updateTargetField() {
        const selectedType = typeSelect.value;
        
        if (!routeContainer || !targetContainer) {
            return;
        }
        
        // Hide all warning messages first
        const routeWarning = document.getElementById('invalid-route-warning');
        const targetWarning = document.getElementById('target-invalid-route-warning');
        
        switch(selectedType) {
            case 'route':
                routeContainer.style.display = 'block';
                
                // Check if a route is already selected and if it supports custom ID
                const selectedRouteValue = $('#route-select').val();
                if (selectedRouteValue) {
                    const supportsCustomId = @json($availableRoutes ?? []).find(route => route.name === selectedRouteValue)?.supports_custom_id;
                    targetContainer.style.display = supportsCustomId ? 'block' : 'none';
                } else {
                    targetContainer.style.display = 'none';
                }
                
                if (routeWarning) routeWarning.style.display = 'block';
                if (targetWarning) targetWarning.style.display = 'none';
                break;
            case 'url':
                routeContainer.style.display = 'none';
                targetContainer.style.display = 'block';
                if (targetField) {
                    targetField.placeholder = 'https://example.com';
                    targetField.required = true;
                }
                if (targetHelp) {
                    targetHelp.textContent = '{{ t("Enter full URL (e.g., https://example.com)") }}';
                }
                if (routeWarning) routeWarning.style.display = 'none';
                if (targetWarning) targetWarning.style.display = 'none';
                break;
            case 'text':
                routeContainer.style.display = 'none';
                targetContainer.style.display = 'block';
                if (targetField) {
                    targetField.placeholder = '';
                    targetField.required = false;
                }
                if (targetHelp) {
                    targetHelp.textContent = '{{ t("Text menus do not require a target.") }}';
                }
                if (routeWarning) routeWarning.style.display = 'none';
                if (targetWarning) targetWarning.style.display = 'none';
                break;
            default:
                routeContainer.style.display = 'none';
                targetContainer.style.display = 'block';
                if (targetField) {
                    targetField.placeholder = '';
                    targetField.required = false;
                }
                if (targetHelp) {
                    targetHelp.textContent = '{{ t("Please select menu type first.") }}';
                }
                if (routeWarning) routeWarning.style.display = 'none';
                if (targetWarning) targetWarning.style.display = 'block';
        }
    }
    
    // Add event listener for type change
    typeSelect.addEventListener('change', function() {
        updateTargetField();
    });
    
    // Route selection event (Select2)
    $('#route-select').on('select2:select', function(e) {
        const selectedData = e.params.data;
        const selectedOption = e.target.selectedOptions[0];
        
        if (selectedData.id && targetField) {
            // Check if this route supports custom ID
            const supportsCustomId = @json($availableRoutes ?? []).find(route => route.name === selectedData.id)?.supports_custom_id;
            
            if (supportsCustomId) {
                // For routes that support custom ID, show target field for editing
                targetContainer.style.display = 'block';
                targetField.value = selectedData.id; // Set default route name
                
                // Get the actual route URI pattern from the selected option
                const routeUri = selectedOption.dataset.uri || '';
                const examplePath = routeUri ? `/${routeUri.replace('{menuId?}', 'sunday1team')}` : '/music/sunday1team';
                
                targetField.placeholder = `e.g., ${selectedData.id} or ${examplePath}`;
                targetField.required = true;
                if (targetHelp) {
                    targetHelp.innerHTML = '<i class="bi bi-info-circle me-1"></i>' + 
                        '{{ t("This route supports custom menu ID. You can use the route name (") }}' + selectedData.id + 
                        '{{ t(") or specify a custom path like ") }}' + examplePath + '"';
                }
                
                // Add custom ID notice
                const customIdNotice = targetContainer.querySelector('.custom-id-notice') || document.createElement('div');
                customIdNotice.className = 'custom-id-notice alert alert-info mt-2 small';
                customIdNotice.innerHTML = `
                    <i class="bi bi-lightbulb me-2"></i>
                    <strong>{{ t("Custom Menu ID Support") }}:</strong><br>
                    • {{ t("Use route name") }}: <code>${selectedData.id}</code> {{ t("(default behavior)") }}<br>
                    • {{ t("Use custom path") }}: <code>${examplePath}</code> {{ t("(for team-specific menus)") }}<br>
                    • {{ t("Route pattern") }}: <code>/${routeUri}</code>
                `;
                if (!targetContainer.querySelector('.custom-id-notice')) {
                    targetContainer.appendChild(customIdNotice);
                }
            } else {
                // For regular routes, hide target field and set value automatically
                targetField.value = selectedData.id;
                targetContainer.style.display = 'none';
                
                // Remove custom ID notice if exists
                const customIdNotice = targetContainer.querySelector('.custom-id-notice');
                if (customIdNotice) {
                    customIdNotice.remove();
                }
            }
            
            // Check if board.index route is selected and validate board connection
            if (selectedData.id === 'board.index') {
                checkBoardConnection();
            } else {
                // Remove any existing board warning
                const existingWarning = routeContainer.querySelector('.board-warning');
                if (existingWarning) {
                    existingWarning.remove();
                }
            }
            
            // Display selected route's URI information
            const uri = selectedOption.dataset.uri;
            if (uri) {
                const routeInfo = routeContainer.querySelector('.route-info') || document.createElement('div');
                routeInfo.className = 'route-info form-text mt-2 text-success';
                routeInfo.innerHTML = `<i class="bi bi-check-circle me-1"></i>{{ t("Selected") }}: <code>${selectedData.id}</code> → <code>/${uri}</code>`;
                if (!routeContainer.querySelector('.route-info')) {
                    routeContainer.appendChild(routeInfo);
                }
            }
            
            // Hide invalid route warning when valid route is selected
            const routeWarning = document.getElementById('invalid-route-warning');
            if (routeWarning) {
                routeWarning.style.display = 'none';
            }
        }
    });
    
    // Function to check board connection for board.index route
    function checkBoardConnection() {
        // Get current menu ID if editing
        const menuId = @json(isset($menu) ? $menu->id : null);
        
        // AJAX call to check board connection
        fetch('{{ route("sitemanager.menus.check-board-connection") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                menu_id: menuId
            })
        })
        .then(response => response.json())
        .then(data => {
            // Remove existing board warning
            const existingWarning = routeContainer.querySelector('.board-warning');
            if (existingWarning) {
                existingWarning.remove();
            }
            
            if (!data.hasBoard) {
                // Show warning if no board is connected
                const warningDiv = document.createElement('div');
                warningDiv.className = 'board-warning alert alert-warning mt-2';
                warningDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>{{ t("No Board Connected") }}:</strong> {{ t("The board.index route requires a board to be connected to this menu.") }}
                    <br>
                    <small class="text-muted">
                        {{ t("Please create a board and connect it to this menu, or this menu will not function properly.") }}
                        <br>
                        <a href="{{ route('sitemanager.boards.create') }}" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                            <i class="bi bi-plus"></i> {{ t("Create New Board") }}
                        </a>
                    </small>
                `;
                routeContainer.appendChild(warningDiv);
            } else {
                // Show success message if board is connected
                const successDiv = document.createElement('div');
                successDiv.className = 'board-warning alert alert-success mt-2';
                successDiv.innerHTML = `
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>{{ t("Board Connected") }}:</strong> {{ t("Board") }} "${data.boardName}" {{ t("is connected to this menu.") }}
                    <br>
                    <small class="text-muted">
                        {{ t("This menu will redirect to the board index page when clicked.") }}
                        <br>
                        <a href="{{ route('sitemanager.boards.index') }}" target="_blank" class="btn btn-sm btn-outline-info mt-1">
                            <i class="bi bi-list"></i> {{ t("Manage Boards") }}
                        </a>
                    </small>
                `;
                routeContainer.appendChild(successDiv);
            }
        })
        .catch(error => {
            console.error('Error checking board connection:', error);
        });
    }
    
    $('#route-select').on('select2:clear', function(e) {
        if (targetField) {
            targetField.value = '';
            const routeInfo = routeContainer.querySelector('.route-info');
            if (routeInfo) {
                routeInfo.remove();
            }
            
            // Remove custom ID notice if exists
            const customIdNotice = targetContainer.querySelector('.custom-id-notice');
            if (customIdNotice) {
                customIdNotice.remove();
            }
            
            // Hide target container
            targetContainer.style.display = 'block';
            if (targetHelp) {
                targetHelp.textContent = '{{ t("Please select a route first.") }}';
            }
        }
    });
    
    // Initial setup
    updateTargetField();
    
    // If editing existing menu with route type, show selected route info
    if (typeSelect.value === 'route') {
        const routeSelectValue = $('#route-select').val();
        if (routeSelectValue) {
            const selectedOption = document.querySelector('#route-select option[value="' + routeSelectValue + '"]');
            if (selectedOption) {
                const uri = selectedOption.dataset.uri;
                if (uri) {
                    const routeInfo = document.createElement('div');
                    routeInfo.className = 'route-info form-text mt-2 text-success';
                    routeInfo.innerHTML = `<i class="bi bi-check-circle me-1"></i>Selected: <code>${routeSelectValue}</code> → <code>/${uri}</code>`;
                    routeContainer.appendChild(routeInfo);
                }
            }
            
            // Check if this route supports custom ID and show target field if needed
            const supportsCustomId = @json($availableRoutes ?? []).find(route => route.name === routeSelectValue)?.supports_custom_id;
            if (supportsCustomId) {
                targetContainer.style.display = 'block';
                
                // Get the actual route URI pattern
                const routeUri = selectedOption ? selectedOption.dataset.uri : '';
                const examplePath = routeUri ? `/${routeUri.replace('{menuId?}', 'sunday1team')}` : '/music/sunday1team';
                
                // Check if current target is a custom path (starts with /)
                const isCustomPath = targetField.value && targetField.value.startsWith('/');
                
                if (targetHelp) {
                    targetHelp.innerHTML = '<i class="bi bi-info-circle me-1"></i>' + 
                        '{{ t("This route supports custom menu ID. You can use the route name (") }}' + routeSelectValue + 
                        '{{ t(") or specify a custom path like ") }}' + examplePath + '"';
                }
                
                // Add custom ID notice for existing menu
                const customIdNotice = document.createElement('div');
                customIdNotice.className = 'custom-id-notice alert alert-info mt-2 small';
                customIdNotice.innerHTML = `
                    <i class="bi bi-lightbulb me-2"></i>
                    <strong>{{ t("Custom Menu ID Support") }}:</strong><br>
                    • {{ t("Use route name") }}: <code>${routeSelectValue}</code> {{ t("(default behavior)") }}<br>
                    • {{ t("Use custom path") }}: <code>${examplePath}</code> {{ t("(for team-specific menus)") }}<br>
                    • {{ t("Route pattern") }}: <code>/${routeUri}</code>
                    ${isCustomPath ? '<br><strong>{{ t("Current") }}:</strong> {{ t("Using custom path") }} <code>' + targetField.value + '</code>' : ''}
                `;
                targetContainer.appendChild(customIdNotice);
            }
            
            // Check board connection if board.index is already selected
            if (routeSelectValue === 'board.index') {
                checkBoardConnection();
            }
        }
    }
});

// Permission management functions
let levelPermIndex = {{ isset($menuPermissions['levels']) ? count($menuPermissions['levels']) : 0 }};
let groupPermIndex = {{ isset($menuPermissions['groups']) ? count($menuPermissions['groups']) : 0 }};
const permissions = @json(config('permissions.menu'));

function addPermLevel() {
    const levels = @json(config('member.levels'));
    const levelWrap = document.getElementById('level-wrap');
    
    // 중복 체크: 이미 추가된 레벨들 확인 (선택된 값 기준)
    const existingLevels = [];
    levelWrap.querySelectorAll('select[name*="[level]"]').forEach(select => {
        if (select.value) {
            existingLevels.push(select.value);
        }
    });
    
    // 기존 메뉴 수정 시 이미 설정된 레벨들도 확인
    levelWrap.querySelectorAll('input[type="hidden"][name*="[level]"]').forEach(input => {
        if (input.value) {
            existingLevels.push(input.value);
        }
    });
    
    // 사용 가능한 레벨만 필터링
    const availableLevels = Object.entries(levels).filter(([value, label]) => !existingLevels.includes(value));
    
    if (availableLevels.length === 0) {
        alert('{{ t("All levels have already been added.") }}');
        return;
    }
    
    // Create level select options (사용 가능한 레벨만)
    let levelOptions = '<option value="">{{ t("Please select a level") }}</option>';
    availableLevels.forEach(([value, label]) => {
        levelOptions += `<option value="${value}">${label} (${value})</option>`;
    });
    
    const permissionHtml = `
        <div id="level-perm-${levelPermIndex}" class="permission-group mb-3 p-3 border rounded">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <label class="form-label mb-0">{{ t("Level") }}:</label>
                    <select name="level_permissions[${levelPermIndex}][level]" class="form-select form-select-sm d-inline-block w-auto ms-2" onchange="checkLevelDuplicate(this)" required>
                        ${levelOptions}
                    </select>
                </div>
                <button type="button" class="btn-outline-default btn-default-sm" onclick="removePermLevel(${levelPermIndex})">
                    - {{ t("Delete") }}
                </button>
            </div>
            <div class="permission-list">
                ${Object.entries(permissions).map(([value, label]) => `
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="level_permissions[${levelPermIndex}][permissions][]" value="${value}">
                        <label class="form-check-label">${label}</label>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
    
    levelWrap.insertAdjacentHTML('beforeend', permissionHtml);
    levelPermIndex++;
}

function removePermLevel(index) {
    const element = document.getElementById(`level-perm-${index}`);
    if (element) {
        element.remove();
    }
}

function addPermGroup() {
    const groups = @json(\SiteManager\Models\Group::all());
    
    // 그룹이 하나도 없으면 경고 메시지 표시
    if (groups.length === 0) {
        alert('{{ t("No groups are registered. Please create a group first.") }}');
        return;
    }
    
    const groupWrap = document.getElementById('group-wrap');
    
    // 중복 체크: 이미 추가된 그룹들 확인 (선택된 값 기준)
    const existingGroups = [];
    groupWrap.querySelectorAll('select[name*="[group_id]"]').forEach(select => {
        if (select.value) {
            existingGroups.push(select.value);
        }
    });
    
    // 기존 메뉴 수정 시 이미 설정된 그룹들도 확인
    groupWrap.querySelectorAll('input[type="hidden"][name*="[group_id]"]').forEach(input => {
        if (input.value) {
            existingGroups.push(input.value);
        }
    });
    
    // 사용 가능한 그룹만 필터링
    const availableGroups = groups.filter(group => !existingGroups.includes(group.id.toString()));
    
    if (availableGroups.length === 0) {
        alert('{{ t("All groups have already been added.") }}');
        return;
    }
    
    // Create group select options (사용 가능한 그룹만)
    let groupOptions = '<option value="">{{ t("Please select a group") }}</option>';
    availableGroups.forEach(group => {
        groupOptions += `<option value="${group.id}">${group.name}</option>`;
    });
    
    const permissionHtml = `
        <div id="group-perm-${groupPermIndex}" class="permission-group mb-3 p-3 border rounded">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <label class="form-label mb-0">{{ t("Group") }}:</label>
                    <select name="group_permissions[${groupPermIndex}][group_id]" class="form-select form-select-sm d-inline-block w-auto ms-2" onchange="checkGroupDuplicate(this)" required>
                        ${groupOptions}
                    </select>
                </div>
                <button type="button" class="btn-outline-default btn-default-sm" onclick="removePermGroup(${groupPermIndex})">
                    - {{ t("Delete") }}
                </button>
            </div>
            <div class="permission-list">
                ${Object.entries(permissions).map(([value, label]) => `
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="group_permissions[${groupPermIndex}][permissions][]" value="${value}">
                        <label class="form-check-label">${label}</label>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
    
    groupWrap.insertAdjacentHTML('beforeend', permissionHtml);
    groupPermIndex++;
}

function removePermGroup(index) {
    const element = document.getElementById(`group-perm-${index}`);
    if (element) {
        element.remove();
    }
}

// 중복 체크 함수들
function checkLevelDuplicate(selectElement) {
    const selectedValue = selectElement.value;
    const levelWrap = document.getElementById('level-wrap');
    const allLevelSelects = levelWrap.querySelectorAll('select[name*="[level]"]');
    
    let duplicateCount = 0;
    allLevelSelects.forEach(select => {
        if (select.value === selectedValue) {
            duplicateCount++;
        }
    });
    
    if (duplicateCount > 1) {
        alert('{{ t("This level has already been selected. Please select a different level.") }}');
        selectElement.selectedIndex = 0; // 첫 번째 옵션으로 리셋
    }
}

function checkGroupDuplicate(selectElement) {
    const selectedValue = selectElement.value;
    if (!selectedValue) return;
    
    const groupWrap = document.getElementById('group-wrap');
    const currentSelectId = selectElement.closest('.permission-group').id;
    
    // 같은 값이 선택된 다른 셀렉트가 있는지 확인
    const duplicateSelects = Array.from(groupWrap.querySelectorAll('select[name*="[group_id]"]')).filter(otherSelect => {
        const otherGroupId = otherSelect.closest('.permission-group').id;
        return otherGroupId !== currentSelectId && otherSelect.value === selectedValue;
    });
    
    // 기존 메뉴 수정 시 이미 설정된 그룹들과도 비교
    const existingHiddenInputs = Array.from(groupWrap.querySelectorAll('input[type="hidden"][name*="[group_id]"]')).filter(input => {
        return input.value === selectedValue;
    });
    
    if (duplicateSelects.length > 0 || existingHiddenInputs.length > 0) {
        alert('{{ t("This group has already been selected.") }}');
        selectElement.value = '';
        return;
    }
}

// Administrator Permission 관련 변수 및 함수
let adminPermIndex = 0;

function addPermAdmin() {
    // Member selector modal 표시
    $('#memberSelectorModal').modal('show');
}

function selectMember(memberId, memberName, memberUsername) {
    const adminWrap = document.getElementById('admin-wrap');
    
    // 중복 체크: 이미 추가된 관리자들 확인
    const existingAdmins = [];
    adminWrap.querySelectorAll('input[type="hidden"][name*="[member_id]"]').forEach(input => {
        if (input.value) {
            existingAdmins.push(input.value);
        }
    });
    
    if (existingAdmins.includes(memberId.toString())) {
        alert('{{ t("This administrator has already been added.") }}');
        $('#memberSelectorModal').modal('hide');
        return;
    }
    
    const permissionHtml = `
        <div id="admin-perm-${adminPermIndex}" class="permission-group mb-3 p-3 border rounded">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <strong>${memberName} (${memberUsername})</strong>
                    <small class="text-muted d-block">{{ t("All permissions") }} (${Object.values(permissions).join(', ')})</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePermAdmin(${adminPermIndex})">
                    - {{ t("Delete") }}
                </button>
            </div>
            <input type="hidden" name="admin_permissions[${adminPermIndex}][member_id]" value="${memberId}">
            <input type="hidden" name="admin_permissions[${adminPermIndex}][permissions][]" value="255">
        </div>
    `;
    
    adminWrap.insertAdjacentHTML('beforeend', permissionHtml);
    adminPermIndex++;
    
    // Modal 닫기
    $('#memberSelectorModal').modal('hide');
}

function removePermAdmin(index) {
    const element = document.getElementById(`admin-perm-${index}`);
    if (element) {
        element.remove();
    }
}

function searchMembers() {
    const query = document.getElementById('memberSearch').value;
    if (query.length < 2) {
        document.getElementById('memberList').innerHTML = '<div class="text-muted">{{ t("Please enter at least 2 characters.") }}</div>';
        return;
    }
    
    // AJAX로 멤버 검색
    fetch(`{{ route('sitemanager.members.search') }}?q=${encodeURIComponent(query)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            let html = '';
            if (data.length === 0) {
                html = '<div class="text-muted">{{ t("No search results found.") }}</div>';
            } else {
                data.forEach(member => {
                    html += `
                        <div class="member-item p-2 border-bottom" style="cursor: pointer;" 
                             onclick="selectMember(${member.id}, '${member.name.replace(/'/g, "\\'")}', '${member.username}')">
                            <strong>${member.name}</strong> (${member.username})
                            <small class="text-muted d-block">${member.email || ''}</small>
                        </div>
                    `;
                });
            }
            document.getElementById('memberList').innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('memberList').innerHTML = '<div class="text-danger">{{ t("An error occurred during search.") }}</div>';
        });
}

// Image management functions
let imageIndex = {{ isset($menu) && $menu->images ? count($menu->images) : 0 }};

document.getElementById('add-image').addEventListener('click', function() {
    const container = document.getElementById('images-container');
    const imageCategories = @json(\SiteManager\Models\Menu::getImageCategories());
    
    let optionsHtml = '';
    Object.keys(imageCategories).forEach(key => {
        optionsHtml += `<option value="${key}">${imageCategories[key]}</option>`;
    });
    
    const newImageHtml = `
        <div class="image-item mb-3 border p-2 rounded">
            <div class="input-group input-group-sm">
                <select class="form-select form-select-sm" name="images[${imageIndex}][category]">
                    ${optionsHtml}
                </select>

                <input type="file" class="form-control form-control-sm image-upload" 
                       name="images[${imageIndex}][file]" 
                       accept="image/*">
               
                <button type="button" class="btn btn-light btn-sm text-danger remove-image">
                    <i class="bi bi-trash"></i> {{ t("Remove") }}
                </button>
            </div>

            <div class="image-preview mt-2" style="display: none;">
                <img class="img-thumbnail" style="max-width: 150px; max-height: 100px;">
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', newImageHtml);
    imageIndex++;
});

// Remove image item
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-image') || e.target.closest('.remove-image')) {
        const imageItem = e.target.closest('.image-item');
        if (imageItem) {
            imageItem.remove();
        }
    }
});

// Preview image when file is selected
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('image-upload')) {
        const file = e.target.files[0];
        const imageItem = e.target.closest('.image-item');
        const previewContainer = imageItem.querySelector('.image-preview');
        const previewImg = previewContainer.querySelector('img');
        const existingPreview = imageItem.querySelector('.existing-preview');
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewContainer.style.display = 'block';
                // Hide existing preview if new file is selected
                if (existingPreview) {
                    existingPreview.style.display = 'none';
                }
            };
            reader.readAsDataURL(file);
        } else {
            previewContainer.style.display = 'none';
            // Show existing preview if file is cleared
            if (existingPreview) {
                existingPreview.style.display = 'block';
            }
        }
    }
});
</script>
@endpush
