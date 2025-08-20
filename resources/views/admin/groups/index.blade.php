@extends('sitemanager::layouts.admin')

@section('title', 'Groups List')

@section('content')
<div class="container">

    <!-- Header Section - Responsive -->
    <div class="mb-4">
        <!-- Desktop Header -->
        <div class="d-none d-md-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0">
                <a href="{{ route('admin.groups.index') }}" class="text-decoration-none text-dark">
                    <i class="bi bi-collection opacity-75"></i> Groups List
                </a>
            </h1>
            <a href="{{ route('admin.groups.create') }}" class="btn btn-primary text-white">
                <i class="bi bi-plus-circle"></i> Add New Group
            </a>
        </div>

        <!-- Mobile Header -->
        <div class="d-md-none">
            <h4 class="mb-3">
                <a href="{{ route('admin.groups.index') }}" class="text-decoration-none text-dark">
                    <i class="bi bi-collection opacity-75"></i> Groups List
                </a>
            </h4>
            <div class="d-grid mb-3">
                <a href="{{ route('admin.groups.create') }}" class="btn btn-primary text-white">
                    <i class="bi bi-plus-circle me-2"></i>Add New Group
                </a>
            </div>
        </div>
    </div>

    <!-- Search Form -->
    <div class="row mb-4">
        <div class="col-12">
            <form method="GET" action="{{ route('admin.groups.index') }}">
                <!-- Desktop Search Layout -->
                <div class="d-none d-md-flex">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by name or description..." 
                               value="{{ request('search') }}">
                        <select name="status" class="form-select" style="max-width: 120px;">
                            <option value="">All Status</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            <option value="deleted" {{ request('status') == 'deleted' ? 'selected' : '' }}>Deleted</option>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Search
                        </button>
                        @if(request()->hasAny(['search', 'status']))
                            <a href="{{ route('admin.groups.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Clear
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Mobile Search Layout -->
                <div class="d-md-none">
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search by name or description..." 
                                   value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            <option value="deleted" {{ request('status') == 'deleted' ? 'selected' : '' }}>Deleted</option>
                        </select>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-2"></i>Search
                        </button>
                        @if(request()->hasAny(['search', 'status']))
                            <a href="{{ route('admin.groups.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-2"></i>Clear
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Members</th>
                    <th>Status</th>
                    <th>Created Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($groups as $group)
                    <tr class="{{ $group->trashed() ? 'table-secondary' : '' }}">
                        <td>{{ $group->id }}</td>
                        <td>
                            <strong>{{ $group->name }}</strong>
                            @if($group->trashed())
                                <span class="badge bg-secondary ms-1">Deleted</span>
                            @endif
                        </td>
                        <td>{{ Str::limit($group->description, 50) ?: 'N/A' }}</td>
                        <td>
                            <span class="badge bg-info">{{ $group->members_count }} members</span>
                        </td>
                        <td>
                            @if($group->trashed())
                                <span class="badge bg-secondary">Deleted</span>
                            @elseif($group->active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-danger">Inactive</span>
                            @endif
                        </td>
                        <td>{{ $group->created_at->format('Y-m-d') }}</td>
                        <td class="text-end">
                            @if($group->trashed())
                                @if(Auth::user()->isAdmin())
                                    <form method="POST" action="{{ route('admin.groups.restore', $group->id) }}" 
                                            class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Restore Group">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.groups.force-delete', $group->id) }}" 
                                            class="d-inline delete-group-form"
                                            data-type="force">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Force Delete">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                @endif
                            @else
                                <a href="{{ route('admin.groups.edit', $group) }}" 
                                    class="btn btn-sm btn-outline-primary" title="Edit Group">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                @if(Auth::user()->isAdmin())
                                    <form method="POST" action="{{ route('admin.groups.destroy', $group) }}" 
                                            class="d-inline delete-group-form"
                                            data-type="soft">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Group">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">No groups found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $groups->links() }}

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 그룹 삭제 폼 처리
    document.querySelectorAll('.delete-group-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const deleteType = this.dataset.type;
            let title, text, confirmText;
            
            if (deleteType === 'force') {
                title = '영구 삭제 확인';
                text = '정말로 이 그룹을 영구적으로 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.';
                confirmText = '영구 삭제';
            } else {
                title = '그룹 삭제 확인';
                text = '이 그룹을 삭제하시겠습니까?';
                confirmText = '삭제';
            }
            
            Swal.fire({
                title: title,
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: confirmText,
                cancelButtonText: '취소'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    });
});
</script>
@endpush
