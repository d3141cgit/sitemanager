@extends('sitemanager::layouts.sitemanager')

@section('title', t('Groups List'))

@section('content')
<div class="container">

    <!-- Header Section - Responsive -->
    <div class="mb-4">
        <!-- Desktop Header -->
        <div class="d-none d-md-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0">
                <a href="{{ route('sitemanager.groups.index') }}" class="text-decoration-none text-dark">
                    <i class="bi bi-collection opacity-75"></i> {{ t('Groups List') }}
                </a>
            </h1>
            <a href="{{ route('sitemanager.groups.create') }}" class="btn btn-primary text-white">
                <i class="bi bi-plus-circle"></i> {{ t('Add New Group') }}
            </a>
        </div>

        <!-- Mobile Header -->
        <div class="d-md-none">
            <h4 class="mb-3">
                <a href="{{ route('sitemanager.groups.index') }}" class="text-decoration-none text-dark">
                    <i class="bi bi-collection opacity-75"></i> {{ t('Groups List') }}
                </a>
            </h4>
            <div class="d-grid mb-3">
                <a href="{{ route('sitemanager.groups.create') }}" class="btn btn-primary text-white">
                    <i class="bi bi-plus-circle me-2"></i>{{ t('Add New Group') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Search Form -->
    <div class="row mb-4">
        <div class="col-12">
            <form method="GET" action="{{ route('sitemanager.groups.index') }}">
                <!-- Desktop Search Layout -->
                <div class="d-none d-md-flex">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" 
                               placeholder="{{ t('Search by name or description...') }}" 
                               value="{{ request('search') }}">
                        <select name="status" class="form-select" style="max-width: 120px;">
                            <option value="">{{ t('All Status') }}</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ t('Active') }}</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>{{ t('Inactive') }}</option>
                            <option value="deleted" {{ request('status') == 'deleted' ? 'selected' : '' }}>{{ t('Deleted') }}</option>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> {{ t('Search') }}
                        </button>
                        @if(request()->hasAny(['search', 'status']))
                            <a href="{{ route('sitemanager.groups.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> {{ t('Clear') }}
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
                                   placeholder="{{ t('Search by name or description...') }}" 
                                   value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <select name="status" class="form-select">
                            <option value="">{{ t('All Status') }}</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ t('Active') }}</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>{{ t('Inactive') }}</option>
                            <option value="deleted" {{ request('status') == 'deleted' ? 'selected' : '' }}>{{ t('Deleted') }}</option>
                        </select>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-2"></i>{{ t('Search') }}
                        </button>
                        @if(request()->hasAny(['search', 'status']))
                            <a href="{{ route('sitemanager.groups.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-2"></i>{{ t('Clear') }}
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
                    <th>{{ t('ID') }}</th>
                    <th>{{ t('Name') }}</th>
                    <th>{{ t('Description') }}</th>
                    <th>{{ t('Members') }}</th>
                    <th>{{ t('Status') }}</th>
                    <th>{{ t('Created Date') }}</th>
                    <th class="text-end">{{ t('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($groups as $group)
                    <tr class="{{ $group->trashed() ? 'table-secondary' : '' }}">
                        <td>{{ $group->id }}</td>
                        <td>
                            <strong>{{ $group->name }}</strong>
                            @if($group->trashed())
                                <span class="badge bg-secondary ms-1">{{ t('Deleted') }}</span>
                            @endif
                        </td>
                        <td>{{ Str::limit($group->description, 50) ?: 'N/A' }}</td>
                        <td>
                            <span class="badge bg-info">{{ $group->members_count }} {{ t('members') }}</span>
                        </td>
                        <td>
                            @if($group->trashed())
                                <span class="badge bg-secondary">{{ t('Deleted') }}</span>
                            @elseif($group->active)
                                <span class="badge bg-success">{{ t('Active') }}</span>
                            @else
                                <span class="badge bg-danger">{{ t('Inactive') }}</span>
                            @endif
                        </td>
                        <td>{{ $group->created_at->format('Y-m-d') }}</td>
                        <td class="text-end">
                            @if($group->trashed())
                                @if(Auth::user()->isAdmin())
                                    <form method="POST" action="{{ route('sitemanager.groups.restore', $group->id) }}" 
                                            class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="{{ t('Restore Group') }}">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('sitemanager.groups.force-delete', $group->id) }}" 
                                            class="d-inline delete-group-form"
                                            data-type="force">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ t('Force Delete') }}">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                @endif
                            @else
                                <a href="{{ route('sitemanager.groups.edit', $group) }}" 
                                    class="btn btn-sm btn-outline-primary" title="{{ t('Edit Group') }}">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                @if(Auth::user()->isAdmin())
                                    <form method="POST" action="{{ route('sitemanager.groups.destroy', $group) }}" 
                                            class="d-inline delete-group-form"
                                            data-type="soft">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ t('Delete Group') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">{{ t('No groups found.') }}</td>
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
                title = '{{ t("Permanent Delete Confirmation") }}';
                text = '{{ t("Are you sure you want to permanently delete this group? This action cannot be undone.") }}';
                confirmText = '{{ t("Permanent Delete") }}';
            } else {
                title = '{{ t("Group Delete Confirmation") }}';
                text = '{{ t("Are you sure you want to delete this group?") }}';
                confirmText = '{{ t("Delete") }}';
            }
            
            Swal.fire({
                title: title,
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: confirmText,
                cancelButtonText: '{{ t("Cancel") }}'
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
