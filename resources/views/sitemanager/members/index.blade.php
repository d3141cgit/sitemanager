@extends('sitemanager::layouts.sitemanager')

@section('title', t('Members List'))

@section('content')
<div class="content-header">
    <h1>
        <a href="{{ route('sitemanager.members.index') }}">
            <i class="bi bi-people opacity-75"></i> {{ t('Members List') }}
        </a>

        <span class="count">{{ number_format($members->total()) }}</span>
    </h1>

    <a href="{{ route('sitemanager.members.create') }}" class="btn-default">
        <i class="bi bi-person-plus"></i> {{ t('Add New Member') }}
    </a>
</div>

<form method="GET" action="{{ route('sitemanager.members.index') }}" class="search-form">  
    <input type="text" name="search" class="form-control" placeholder="{{ t('Search by name, username, or email...') }}" value="{{ request('search') }}">
    
    <select name="level" class="form-select">
        <option value="">{{ t('All Levels') }}</option>
        @foreach($levels as $levelValue => $levelName)
            <option value="{{ $levelValue }}" {{ request('level') == $levelValue ? 'selected' : '' }}>
                {{ $levelValue }} - {{ $levelName }}
            </option>
        @endforeach
    </select>

    <select name="group_id" class="form-select">
        <option value="">{{ t('All Groups') }}</option>
        @foreach($groups as $group)
            <option value="{{ $group->id }}" {{ request('group_id') == $group->id ? 'selected' : '' }}>
                {{ $group->name }}
            </option>
        @endforeach
    </select>

    <select name="status" class="form-select">
        <option value="active" {{ (request('status', 'active') == 'active') ? 'selected' : '' }}>{{ t('Active') }}</option>
        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>{{ t('Inactive') }}</option>
        <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>{{ t('All Status') }}</option>
        <option value="deleted" {{ request('status') == 'deleted' ? 'selected' : '' }}>{{ t('Deleted') }}</option>
    </select>

    <button type="submit" class="btn btn-outline-secondary">
        <i class="bi bi-search me-2"></i>{{ t('Search') }}
    </button>

    @if(request()->hasAny(['search', 'level', 'group_id', 'status']))
        <a href="{{ route('sitemanager.members.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-x-circle"></i>
        </a>
    @endif
</form>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th class="right">{{ t('ID') }}</th>
                <th>{{ t('Photo') }}</th>
                <th>{{ t('Name') }}</th>
                <th>{{ t('Username') }}</th>
                <th>{{ t('Email') }}</th>
                <th>{{ t('Groups') }}</th>
                <th>{{ t('Level') }}</th>
                <th class="text-center">{{ t('Status') }}</th>
                <th>{{ t('Join Date') }}</th>
                <th class="text-end">{{ t('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($members as $member)
                <tr class="{{ $member->trashed() ? 'table-secondary' : '' }}">
                    <td class="number right">{{ $member->id }}</td>
                    <td width="50">
                        @if($member->profile_photo)
                            <img src="{{ $member->profile_photo_url }}" 
                                    alt="{{ $member->name }}'s profile photo" 
                                    class="member-profile-photo">
                        @else
                            <div class="member-profile-placeholder">
                                <i class="bi bi-person"></i>
                            </div>
                        @endif
                    </td>
                    <td nowrap>
                        <div class="member-info">
                            <span class="name">{{ $member->name }}</span>
                            <span class="text-muted">{{ $member->title }}</span>
                            @if($member->isAdmin())
                                <span class="badge rounded-pill bg-light text-danger">{{ t('Admin') }}</span>
                            @endif
                            @if($member->trashed())
                                <span class="badge rounded-pill bg-light text-secondary">{{ t('Deleted') }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="number">{{ $member->username }}</td>
                    <td class="number">{{ $member->email ?? 'N/A' }}</td>
                    <td>
                        @if($member->groups->count() > 0)
                            @foreach($member->groups as $group)
                                <span class="badge bg-info text-dark me-1 mb-1">{{ $group->name }}</span>
                            @endforeach
                        @endif
                    </td>
                    <td>
                        <span class="member-level text-{{ $member->isAdmin() ? 'danger' : ($member->isStaff() ? 'warning' : 'secondary') }}">
                            {{ $member->level_display }}
                        </span>
                    </td>
                    <td class="member-status text-center">
                        @if($member->trashed())
                            <span class="text-secondary">{{ t('Deleted') }}</span>
                        @elseif(Auth::user()->isAdmin() && Auth::user()->id !== $member->id)
                            <button class="text-{{ $member->active ? 'success' : 'danger' }} member-status-toggle" 
                                    data-member-id="{{ $member->id }}" 
                                    data-current-status="{{ $member->active ? 'true' : 'false' }}"
                                    title="{{ t('Click to') }} {{ $member->active ? t('deactivate') : t('activate') }}">
                                {{ $member->active ? t('Active') : t('Inactive') }}
                            </button>
                        @else
                            <span class="text-{{ $member->active ? 'success' : 'danger' }}">
                                {{ $member->active ? t('Active') : t('Inactive') }}
                            </span>
                        @endif
                    </td>
                    <td nowrap class="number">{{ $member->created_at->format('Y-m-d') }}</td>
                    <td class="text-end actions" nowrap>
                        @if($member->trashed())
                            @if(Auth::user()->isAdmin())
                                <form method="POST" action="{{ route('sitemanager.members.restore', $member->id) }}" 
                                        class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="{{ t('Restore Member') }}">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('sitemanager.members.force-delete', $member->id) }}" 
                                        class="d-inline delete-member-form"
                                        data-type="force">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ t('Force Delete') }}">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            @endif
                        @else
                            @if(Auth::user()->id === $member->id || Auth::user()->isAdmin())
                                <a href="{{ route('sitemanager.members.edit', $member) }}" 
                                    class="btn btn-sm btn-outline-primary" title="{{ t('Edit Member') }}">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            @endif

                            @if(Auth::user()->isAdmin() && Auth::user()->id !== $member->id)
                                <form method="POST" action="{{ route('sitemanager.members.destroy', $member) }}" 
                                        class="d-inline delete-member-form"
                                        data-type="soft">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ t('Delete Member') }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center">{{ t('No members found.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $members->links() }}

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 회원 삭제 폼 처리
    document.querySelectorAll('.delete-member-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const deleteType = this.dataset.type;
            let title, text, confirmText;
            
            if (deleteType === 'force') {
                title = '{{ t("Permanent Delete Confirmation") }}';
                text = '{{ t("Are you sure you want to permanently delete this member? This action cannot be undone.") }}';
                confirmText = '{{ t("Permanent Delete") }}';
            } else {
                title = '{{ t("Member Delete Confirmation") }}';
                text = '{{ t("Are you sure you want to delete this member?") }}';
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

    // 멤버 상태 토글 처리
    document.querySelectorAll('.member-status-toggle').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const memberId = this.dataset.memberId;
            const currentStatus = this.dataset.currentStatus === 'true';
            const newStatus = !currentStatus;
            const actionText = newStatus ? '{{ t("activate") }}' : '{{ t("deactivate") }}';
            
            Swal.fire({
                title: `{{ t("Confirm") }} ${actionText}`,
                text: `{{ t("Are you sure you want to") }} ${actionText} {{ t("this member?") }}`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: newStatus ? '#198754' : '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `{{ t("Yes") }}, ${actionText}`,
                cancelButtonText: '{{ t("Cancel") }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    // AJAX 요청으로 상태 변경
                    fetch(`/sitemanager/members/${memberId}/toggle-status`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            active: newStatus
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Active 필터가 적용된 상태에서 inactive로 변경하면 행 제거
                            const currentStatusParam = new URLSearchParams(window.location.search).get('status');
                            const isActiveFilter = !currentStatusParam || currentStatusParam === 'active';
                            
                            if (isActiveFilter && !newStatus) {
                                // Active 필터 상태에서 멤버를 inactive로 변경한 경우 행 제거
                                const row = this.closest('tr');
                                row.style.transition = 'opacity 0.3s ease';
                                row.style.opacity = '0';
                                setTimeout(() => {
                                    row.remove();
                                }, 300);
                            } else {
                                // 상태 업데이트
                                this.textContent = newStatus ? '{{ t("Active") }}' : '{{ t("Inactive") }}';
                                this.className = `text-${newStatus ? 'success' : 'danger'} border-0 member-status-toggle`;
                                this.dataset.currentStatus = newStatus.toString();
                                this.title = `{{ t("Click to") }} ${newStatus ? '{{ t("deactivate") }}' : '{{ t("activate") }}'}`;
                            }
                            
                            // 성공 메시지
                            Swal.fire({
                                title: '{{ t("Success!") }}',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire('{{ t("Error!") }}', '{{ t("Failed to update member status.") }}', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('{{ t("Error!") }}', '{{ t("Network error occurred.") }}', 'error');
                    });
                }
            });
        });
    });
});
</script>

<style>
.member-status-toggle {
    cursor: pointer;
    transition: all 0.2s ease;
}

.member-status-toggle:hover {
    transform: scale(1.05);
    opacity: 0.8;
}

.member-status-toggle:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.querySelector('.search-form');
    const selectElements = searchForm.querySelectorAll('select');
    
    // Auto-submit when select options change
    selectElements.forEach(function(select) {
        select.addEventListener('change', function() {
            searchForm.submit();
        });
    });
    
    // Debounced search for input field
    const searchInput = searchForm.querySelector('input[name="search"]');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            if (searchInput.value.length >= 2 || searchInput.value.length === 0) {
                searchForm.submit();
            }
        }, 500); // 500ms delay
    });
});
</script>
@endpush
