@extends('sitemanager::layouts.sitemanager')

@section('title', t('Groups List'))

@section('content')
<div class="content-header">
    <h1>
        <a href="{{ route('sitemanager.groups.index') }}">
            <i class="bi bi-collection opacity-75"></i> {{ t('Groups List') }}
        </a>

        @if ($groups->total() > 0)
        <span class="count">{{ number_format($groups->total()) }}</span>
        @endif
    </h1>

    <a href="{{ route('sitemanager.groups.create') }}" class="btn-default">
        <i class="bi bi-plus-circle"></i> {{ t('Add New Group') }}
    </a>
</div>

<form method="GET" action="{{ route('sitemanager.groups.index') }}" class="search-form">
    <input type="text" name="search" class="form-control" placeholder="{{ t('Search by name or description...') }}" value="{{ request('search') }}">
    
    <select name="status" class="form-select" style="max-width: 120px;"> <option value="">{{ t('All Status') }}</option> <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ t('Active') }}</option> <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>{{ t('Inactive') }}</option> <option value="deleted" {{ request('status') == 'deleted' ? 'selected' : '' }}>{{ t('Deleted') }}</option> </select>
    
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-search"></i> {{ t('Search') }}
    </button>
    
    @if(request()->hasAny(['search', 'status']))
        <a href="{{ route('sitemanager.groups.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-x-circle"></i> {{ t('Clear') }}
        </a>
    @endif
</form>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th class="right">{{ t('ID') }}</th>
                <th>{{ t('Name') }}</th>
                <th>{{ t('Description') }}</th>
                <th class="text-center">{{ t('Members') }}</th>
                <th class="text-center">{{ t('Status') }}</th>
                <th>{{ t('Created Date') }}</th>
                <th class="text-end">{{ t('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groups as $group)
                <tr class="{{ $group->trashed() ? 'table-secondary' : '' }}">
                    <td class="number right">{{ $group->id }}</td>
                    <td nowrap>
                        <div class="member-info">
                            <span class="name">{{ $group->name }}</span>
                            @if($group->trashed())
                                <span class="badge rounded-pill bg-light text-secondary">{{ t('Deleted') }}</span>
                            @endif
                        </div>
                    </td>
                    <td>{{ Str::limit($group->description, 50) ?: '-' }}</td>
                    <td class="number text-center">
                        {{ $group->members_count > 0 ? $group->members_count : '-' }}
                    </td>
                    <td class="text-center">
                        @if($group->trashed())
                            <span class="badge bg-secondary">{{ t('Deleted') }}</span>
                        @elseif($group->active)
                            <span class="badge bg-success">{{ t('Active') }}</span>
                        @else
                            <span class="badge bg-danger">{{ t('Inactive') }}</span>
                        @endif
                    </td>
                    <td class="number">{{ $group->created_at->format('Y-m-d') }}</td>
                    <td class="text-end actions">
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

{{ $groups->links('sitemanager::pagination.default') }}

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
