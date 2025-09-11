@extends('sitemanager::layouts.sitemanager')

@section('title', t('Menu Management'))

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.7.2/dist/axios.min.js"></script>

{!! resource('sitemanager::css/sitemanager/tree.css') !!}
{!! resource('sitemanager::js/sitemanager/tree.js') !!}
@endpush

@section('content')
<div class="content-header">
    <h1>
        <a href="{{ route('sitemanager.menus.index') }}">
            <i class="bi bi-list opacity-75"></i> {{ t('Menu Management') }}
        </a>

        @if ($totalCount > 0)
        <span class="count">{{ number_format($totalCount) }}</span>
        @endif
    </h1>

    <div class="d-flex gap-1">
        <button type="button" class="btn-outline-default" id="update-search-content-btn">
            <i class="bi bi-search me-1"></i>{{ t('Update Search Content') }}
        </button>
        <button type="button" class="btn-outline-default" id="rebuild-tree-btn">
            <i class="bi bi-arrow-clockwise me-1"></i>{{ t('Rebuild Tree') }}
        </button>
        <a href="{{ route('sitemanager.menus.create') }}" class="btn-default">
            <i class="bi bi-plus me-1"></i>{{ t('Add New Menu') }}
        </a>
    </div>
</div>

<!-- Bootstrap Grid Layout for Sections -->
@if($menusWithUrls->count() > 0)                
    <!-- Legend Section - Responsive -->
    <div class="alert alert-sm alert-light mb-3">
        <!-- Desktop Legend -->
        <div class="d-none d-md-block">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <span class="text-muted small">{{ t('Drag to reorder. Hold Command (Mac) or Ctrl (Win) while dragging to create a sub-menu.') }}</span>

                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                        <small class="text-muted">{{ t('Invalid Route') }}</small>
                    </div>
                    <div class="d-flex align-items-center menu-legend-hidden">
                        <span class="legend-sample me-1">{{ t('HIDDEN') }}</span>
                        <small class="text-muted">{{ t('Hidden Menu') }}</small>
                    </div>
                    <div class="d-flex align-items-center menu-legend-no-access">
                        <span class="legend-sample me-1">{{ t('NO ACCESS') }}</span>
                        <small class="text-muted">{{ t('No Access Permission') }}</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mobile Legend -->
        <div class="d-md-none">
            <div class="text-center mb-2">
                <small class="text-muted">{{ t('Drag to reorder menus') }}</small>
            </div>
            <div class="d-flex justify-content-center flex-wrap gap-2">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                    <small class="text-muted">{{ t('Invalid') }}</small>
                </div>
                <div class="d-flex align-items-center menu-legend-hidden">
                    <span class="legend-sample me-1" style="font-size: 0.7rem;">{{ t('HIDDEN') }}</span>
                    <small class="text-muted">{{ t('Hidden') }}</small>
                </div>
                <div class="d-flex align-items-center menu-legend-no-access">
                    <span class="legend-sample me-1" style="font-size: 0.7rem;">{{ t('NO ACCESS') }}</span>
                    <small class="text-muted">{{ t('No Access') }}</small>
                </div>
            </div>
        </div>
    </div>

    <div id="menu-tree" class="sortable-tree"></div>
@else
    <div class="text-center py-5">
        <i class="bi bi-list" style="font-size: 3rem;" class="text-muted mb-3"></i>
        <p class="text-muted">{{ t('No menus registered.') }}</p>
        <a href="{{ route('sitemanager.menus.create') }}" class="btn btn-primary">
            {{ t('Add Your First Menu') }}
        </a>
    </div>
@endif

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {

    @if($menusWithUrls->count() > 0)
    const treeManager = new MenuTreeManager({
        menuData: @json($menusWithUrls),
        invalidRouteMenus: @json($invalidRouteMenus ?? []),
        moveUrl: '{{ route('sitemanager.menus.move') }}',
        treeUrl: '{{ route('sitemanager.menus.index') }}',
        editBaseUrl: '{{ route("sitemanager.menus.index") }}',
        csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    });
    @endif

    // Update search content with confirmation - 안전한 요소 확인
    const updateSearchContentBtn = document.querySelector('#update-search-content-btn');
    if (updateSearchContentBtn) {
        updateSearchContentBtn.addEventListener('click', function() {
            showUpdateSearchContentConfirmation();
        });
    }

    function showUpdateSearchContentConfirmation() {
        Swal.fire({
            title: '{{ t("Update Search Content") }}',
            text: '{{ t("This will extract text content from all menu-linked view files and update search database. Continue?") }}',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '{{ t("Update") }}',
            cancelButtonText: '{{ t("Cancel") }}'
        }).then((result) => {
            if (result.isConfirmed) {
                updateSearchContent();
            }
        });
    }

    // Update search content function
    function updateSearchContent() {
        Swal.fire({
            title: '{{ t("Processing...") }}',
            text: '{{ t("Extracting content from view files and updating search database.") }}',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('{{ route("sitemanager.menus.update-search-content") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '{{ t("Complete!") }}',
                    text: data.message,
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: true
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '{{ t("Error!") }}',
                    text: data.message || '{{ t("Search content update failed.") }}'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: '{{ t("Error!") }}',
                text: '{{ t("An error occurred during search content update.") }}'
            });
        });
    }

        // Rebuild tree with confirmation (Desktop) - 안전한 요소 확인
    const rebuildTreeBtn = document.querySelector('#rebuild-tree-btn');
    if (rebuildTreeBtn) {
        rebuildTreeBtn.addEventListener('click', function() {
            showRebuildConfirmation();
        });
    }

    // Rebuild tree with confirmation (Mobile) - 안전한 요소 확인
    const rebuildBtnMobile = document.querySelector('#rebuild-tree-btn-mobile');
    if (rebuildBtnMobile) {
        rebuildBtnMobile.addEventListener('click', function() {
            showRebuildConfirmation();
        });
    }

    function showRebuildConfirmation() {
        Swal.fire({
            title: '{{ t("Tree Structure Rebuild") }}',
            text: '{{ t("Are you sure you want to rebuild the tree structure?") }}',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '{{ t("Rebuild") }}',
            cancelButtonText: '{{ t("Cancel") }}'
        }).then((result) => {
            if (result.isConfirmed) {
                rebuildTree();
            }
        });
    }

    // Rebuild tree function
    function rebuildTree() {
        Swal.fire({
            title: '{{ t("Processing...") }}',
            text: '{{ t("Rebuilding tree structure.") }}',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('{{ route("sitemanager.menus.rebuild-tree") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '{{ t("Complete!") }}',
                    text: data.message,
                    timer: 500,
                    timerProgressBar: true,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '{{ t("Error!") }}',
                    text: data.message || '{{ t("Rebuild operation failed.") }}'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: '{{ t("Error!") }}',
                text: '{{ t("An error occurred during rebuild.") }}'
            });
        });
    }
});
</script>
@endpush
