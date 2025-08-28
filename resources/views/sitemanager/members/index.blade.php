@extends('sitemanager::layouts.sitemanager')

@section('title', 'Members List')

@section('content')
<div class="container">

    <!-- Header Section - Responsive -->
    <div class="mb-4">
        <!-- Desktop Header -->
        <div class="d-none d-md-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0">
                <a href="{{ route('sitemanager.members.index') }}" class="text-decoration-none text-dark">
                    <i class="bi bi-people opacity-75"></i> Members List
                    <span class="ms-2">({{ number_format($members->total()) }})</span>
                </a>
            </h1>
            <a href="{{ route('sitemanager.members.create') }}" class="btn btn-primary text-white">
                <i class="bi bi-person-plus"></i> Add New Member
            </a>
        </div>

        <!-- Mobile Header -->
        <div class="d-md-none">
            <h4 class="mb-3">
                <a href="{{ route('sitemanager.members.index') }}" class="text-decoration-none text-dark">
                    <i class="bi bi-people opacity-75"></i> Members List
                    <span class="ms-2">({{ number_format($members->total()) }})</span>
                </a>
            </h4>
            <div class="d-grid mb-3">
                <a href="{{ route('sitemanager.members.create') }}" class="btn btn-primary text-white">
                    <i class="bi bi-person-plus me-2"></i>Add New Member
                </a>
            </div>
        </div>
    </div>

    <!-- Search Form -->
    <div class="row mb-4">
        <div class="col-12">
            <form method="GET" action="{{ route('sitemanager.members.index') }}">
                <!-- Desktop Search Layout -->
                <div class="d-none d-md-flex">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by name, username, or email..." 
                               value="{{ request('search') }}">
                        <select name="level" class="form-select" style="max-width: 150px;">
                            <option value="">All Levels</option>
                            @foreach($levels as $levelValue => $levelName)
                                <option value="{{ $levelValue }}" {{ request('level') == $levelValue ? 'selected' : '' }}>
                                    {{ $levelValue }} - {{ $levelName }}
                                </option>
                            @endforeach
                        </select>
                        <select name="group_id" class="form-select" style="max-width: 150px;">
                            <option value="">All Groups</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" {{ request('group_id') == $group->id ? 'selected' : '' }}>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                        <select name="status" class="form-select" style="max-width: 120px;">
                            <option value="active" {{ (request('status', 'active') == 'active') ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>All Status</option>
                            <option value="deleted" {{ request('status') == 'deleted' ? 'selected' : '' }}>Deleted</option>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Search
                        </button>
                        @if(request()->hasAny(['search', 'level', 'group_id', 'status']))
                            <a href="{{ route('sitemanager.members.index') }}" class="btn btn-outline-secondary">
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
                                   placeholder="Search by name, username, or email..." 
                                   value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <select name="level" class="form-select">
                                <option value="">All Levels</option>
                                @foreach($levels as $levelValue => $levelName)
                                    <option value="{{ $levelValue }}" {{ request('level') == $levelValue ? 'selected' : '' }}>
                                        {{ $levelValue }} - {{ $levelName }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <select name="group_id" class="form-select">
                                <option value="">All Groups</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}" {{ request('group_id') == $group->id ? 'selected' : '' }}>
                                        {{ $group->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12">
                            <select name="status" class="form-select">
                                <option value="active" {{ (request('status', 'active') == 'active') ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>All Status</option>
                                <option value="deleted" {{ request('status') == 'deleted' ? 'selected' : '' }}>Deleted</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-2"></i>Search
                        </button>
                        @if(request()->hasAny(['search', 'level', 'group_id', 'status']))
                            <a href="{{ route('sitemanager.members.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-2"></i>Clear
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Photo</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Groups</th>
                    <th>Level</th>
                    <th>Status</th>
                    <th>Join Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($members as $member)
                    <tr class="{{ $member->trashed() ? 'table-secondary' : '' }}">
                        <td>{{ $member->id }}</td>
                        <td>
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
                            <strong>{{ $member->name }}</strong>
                            <span class="text-muted">{{ $member->title }}</span>
                            @if($member->isAdmin())
                                <span class="badge bg-danger ms-1">Admin</span>
                            @endif
                            @if($member->trashed())
                                <span class="badge bg-secondary ms-1">Deleted</span>
                            @endif
                        </td>
                        <td>{{ $member->username }}</td>
                        <td>{{ $member->email ?? 'N/A' }}</td>
                        <td>
                            @if($member->groups->count() > 0)
                                @foreach($member->groups as $group)
                                    <span class="badge bg-info text-dark me-1 mb-1">{{ $group->name }}</span>
                                @endforeach
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $member->isAdmin() ? 'danger' : ($member->isStaff() ? 'warning' : 'secondary') }}">
                                {{ $member->level_display }}
                            </span>
                        </td>
                        <td>
                            @if($member->trashed())
                                <span class="badge bg-secondary">Deleted</span>
                            @elseif(Auth::user()->isAdmin() && Auth::user()->id !== $member->id)
                                <button class="badge bg-{{ $member->active ? 'success' : 'danger' }} border-0 member-status-toggle" 
                                        data-member-id="{{ $member->id }}" 
                                        data-current-status="{{ $member->active ? 'true' : 'false' }}"
                                        title="Click to {{ $member->active ? 'deactivate' : 'activate' }}">
                                    {{ $member->active ? 'Active' : 'Inactive' }}
                                </button>
                            @else
                                <span class="badge bg-{{ $member->active ? 'success' : 'danger' }}">
                                    {{ $member->active ? 'Active' : 'Inactive' }}
                                </span>
                            @endif
                        </td>
                        <td nowrap>{{ $member->created_at->format('Y-m-d') }}</td>
                        <td class="text-end" nowrap>
                            @if($member->trashed())
                                @if(Auth::user()->isAdmin())
                                    <form method="POST" action="{{ route('sitemanager.members.restore', $member->id) }}" 
                                            class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Restore Member">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('sitemanager.members.force-delete', $member->id) }}" 
                                            class="d-inline delete-member-form"
                                            data-type="force">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Force Delete">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                @endif
                            @else
                                @if(Auth::user()->id === $member->id || Auth::user()->isAdmin())
                                    <a href="{{ route('sitemanager.members.edit', $member) }}" 
                                        class="btn btn-sm btn-outline-primary" title="Edit Member">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endif

                                @if(Auth::user()->isAdmin() && Auth::user()->id !== $member->id)
                                    <form method="POST" action="{{ route('sitemanager.members.destroy', $member) }}" 
                                            class="d-inline delete-member-form"
                                            data-type="soft">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Member">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center">No members found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $members->links() }}

</div>
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
                title = '영구 삭제 확인';
                text = '정말로 이 회원을 영구적으로 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.';
                confirmText = '영구 삭제';
            } else {
                title = '회원 삭제 확인';
                text = '이 회원을 삭제하시겠습니까?';
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

    // 멤버 상태 토글 처리
    document.querySelectorAll('.member-status-toggle').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const memberId = this.dataset.memberId;
            const currentStatus = this.dataset.currentStatus === 'true';
            const newStatus = !currentStatus;
            const actionText = newStatus ? 'activate' : 'deactivate';
            
            Swal.fire({
                title: `Confirm ${actionText}`,
                text: `Are you sure you want to ${actionText} this member?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: newStatus ? '#198754' : '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Yes, ${actionText}`,
                cancelButtonText: 'Cancel'
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
                                this.textContent = newStatus ? 'Active' : 'Inactive';
                                this.className = `badge bg-${newStatus ? 'success' : 'danger'} border-0 member-status-toggle`;
                                this.dataset.currentStatus = newStatus.toString();
                                this.title = `Click to ${newStatus ? 'deactivate' : 'activate'}`;
                            }
                            
                            // 성공 메시지
                            Swal.fire({
                                title: 'Success!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire('Error!', 'Failed to update member status.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Error!', 'Network error occurred.', 'error');
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
@endpush
