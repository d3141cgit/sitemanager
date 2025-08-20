@extends('sitemanager::layouts.admin')

@section('title', 'Menu Management')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.7.2/dist/axios.min.js"></script>

{!! resource('css/admin/tree.css') !!}
{!! resource('js/admin/tree.js') !!}
@endpush

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <!-- Header Section - Responsive -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3 d-none d-md-flex">
                    <h2 class="mb-0">
                        <i class="bi bi-list me-2"></i>Menu Management
                    </h2>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="rebuild-tree-btn">
                            <i class="bi bi-arrow-clockwise me-1"></i>Rebuild Tree
                        </button>
                        <a href="{{ route('admin.menus.create') }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus me-1"></i>Add New Menu
                        </a>
                    </div>
                </div>
                
                <!-- Mobile Header -->
                <div class="d-md-none">
                    <h4 class="mb-3">
                        <i class="bi bi-list me-2"></i>Menu Management
                    </h4>
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.menus.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus me-2"></i>Add New Menu
                        </a>
                        <button type="button" class="btn btn-outline-secondary" id="rebuild-tree-btn-mobile">
                            <i class="bi bi-arrow-clockwise me-2"></i>Rebuild Tree
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bootstrap Grid Layout for Sections -->
            @if($menus->count() > 0)                
                <!-- Legend Section - Responsive -->
                <div class="alert alert-sm alert-light mb-3">
                    <!-- Desktop Legend -->
                    <div class="d-none d-md-block">
                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                            <span class="text-muted small">Drag to reorder. Hold Command (Mac) or Ctrl (Win) while dragging to create a sub-menu.</span>

                            <div class="d-flex flex-wrap gap-3 align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                                    <small class="text-muted">Invalid Route</small>
                                </div>
                                <div class="d-flex align-items-center menu-legend-hidden">
                                    <span class="legend-sample me-1">HIDDEN</span>
                                    <small class="text-muted">Hidden Menu</small>
                                </div>
                                <div class="d-flex align-items-center menu-legend-no-access">
                                    <span class="legend-sample me-1">NO ACCESS</span>
                                    <small class="text-muted">No Access Permission</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mobile Legend -->
                    <div class="d-md-none">
                        <div class="text-center mb-2">
                            <small class="text-muted">Drag to reorder menus</small>
                        </div>
                        <div class="d-flex justify-content-center flex-wrap gap-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                                <small class="text-muted">Invalid</small>
                            </div>
                            <div class="d-flex align-items-center menu-legend-hidden">
                                <span class="legend-sample me-1" style="font-size: 0.7rem;">HIDDEN</span>
                                <small class="text-muted">Hidden</small>
                            </div>
                            <div class="d-flex align-items-center menu-legend-no-access">
                                <span class="legend-sample me-1" style="font-size: 0.7rem;">NO ACCESS</span>
                                <small class="text-muted">No Access</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="menu-tree" class="sortable-tree"></div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-list" style="font-size: 3rem;" class="text-muted mb-3"></i>
                    <p class="text-muted">No menus registered.</p>
                    <a href="{{ route('admin.menus.create') }}" class="btn btn-primary">
                        Add Your First Menu
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {

    @if($menus->count() > 0)
    const treeManager = new MenuTreeManager({
        menuData: @json($menus),
        invalidRouteMenus: @json($invalidRouteMenus ?? []),
        moveUrl: '{{ route('admin.menus.move') }}',
        treeUrl: '{{ route('admin.menus.index') }}',
        editBaseUrl: '{{ route("admin.menus.index") }}',
        csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    });
    @endif

        // Rebuild tree with confirmation (Desktop)
    document.querySelector('#rebuild-tree-btn').addEventListener('click', function() {
        showRebuildConfirmation();
    });

    // Rebuild tree with confirmation (Mobile)
    document.querySelector('#rebuild-tree-btn-mobile').addEventListener('click', function() {
        showRebuildConfirmation();
    });

    function showRebuildConfirmation() {
        Swal.fire({
            title: '트리 구조 재구성',
            text: '정말로 트리 구조를 재구성하시겠습니까?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '재구성',
            cancelButtonText: '취소'
        }).then((result) => {
            if (result.isConfirmed) {
                rebuildTree();
            }
        });
    }

    // Rebuild tree function
    function rebuildTree() {
        Swal.fire({
            title: '처리 중...',
            text: '트리 구조를 재구성하고 있습니다.',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('{{ route("admin.menus.rebuild-tree") }}', {
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
                    title: '완료!',
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
                    title: '오류!',
                    text: data.message || 'Rebuild operation failed.'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: '오류!',
                text: 'An error occurred during rebuild.'
            });
        });
    }
});
</script>
@endpush
