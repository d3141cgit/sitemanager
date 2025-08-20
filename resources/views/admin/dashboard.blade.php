@extends('sitemanager::layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-3 col-6">
            <a href="{{ route('admin.members.index') }}" class="text-decoration-none">
                <div class="card text-center shadow-sm h-100 card-hover">
                    <div class="card-body py-4">
                        <div class="mb-2">
                            <i class="bi bi-people fs-2 text-primary opacity-75"></i>
                        </div>
                        <div class="fw-bold text-dark">Total Members</div>
                        <div class="h4 mb-0 text-primary">{{ number_format($stats['total_members']) }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="{{ route('admin.members.index', ['status' => 'active']) }}" class="text-decoration-none">
                <div class="card text-center shadow-sm h-100 card-hover">
                    <div class="card-body py-4">
                        <div class="mb-2">
                            <i class="bi bi-person-check fs-2 text-success opacity-75"></i>
                        </div>
                        <div class="fw-bold text-dark">Active Members</div>
                        <div class="h4 mb-0 text-success">{{ number_format($stats['active_members']) }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="{{ route('admin.groups.index') }}" class="text-decoration-none">
                <div class="card text-center shadow-sm h-100 card-hover">
                    <div class="card-body py-4">
                        <div class="mb-2">
                            <i class="bi bi-collection fs-2 text-info opacity-75"></i>
                        </div>
                        <div class="fw-bold text-dark">Total Groups</div>
                        <div class="h4 mb-0 text-info">{{ number_format($stats['total_groups']) }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="{{ route('admin.menus.index') }}" class="text-decoration-none">
                <div class="card text-center shadow-sm h-100 card-hover">
                    <div class="card-body py-4">
                        <div class="mb-2">
                            <i class="bi bi-list fs-2 text-warning opacity-75"></i>
                        </div>
                        <div class="fw-bold text-dark">Total Menus</div>
                        <div class="h4 mb-0 text-warning">{{ number_format($stats['total_menus']) }}</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    @if(isset($invalidRouteMenus) && count($invalidRouteMenus) > 0)
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-exclamation-triangle fs-5 me-2"></i>
                        <h6 class="mb-0 fw-bold">Menu System Alert</h6>
                    </div>
                    <p class="mb-2">
                        <strong>{{ count($invalidRouteMenus) }} menu(s)</strong> contain routes that no longer exist in the application.
                        These menus will not function properly and need attention.
                    </p>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        @foreach($invalidRouteMenus as $invalidMenu)
                            <span class="badge bg-white text-dark border">
                                {{ $invalidMenu['title'] }} 
                                <small class="text-muted">({{ $invalidMenu['target'] }})</small>
                            </span>
                        @endforeach
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.menus.index') }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-list me-1"></i>Manage Menus
                        </a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <!-- 최근 가입 회원 -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title">
                            <i class="bi bi-person-plus me-1"></i>
                            Recent Members
                        </h5>
                        <a href="{{ route('admin.members.index') }}">
                            View All
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    @if($recent_members->count() > 0)
                    <div class="admin-table" style="overflow-x: auto;">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th class="text-end">Join Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recent_members as $member)
                                <tr>
                                    <td nowrap>
                                        <a href="{{ route('admin.members.edit', $member) }}" title="Edit">{{ $member->name }}</a>
                                    </td>
                                    <td>
                                        {{ $member->email }}
                                    </td>
                                    <td>
                                        <span class="badge {{ $member->active ? 'bg-success' : 'bg-danger' }}">
                                            {{ $member->active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="text-end" nowrap>{{ $member->created_at->format('Y-m-d') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-5">
                        <i class="bi bi-people text-muted mb-3" style="font-size: 3rem;"></i>
                        <p class="text-muted">No members registered yet.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- 빠른 작업 -->
        <div class="col-lg-4 mb-4">
            <div class="d-flex flex-column h-100">
                <div class="card flex-fill">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-lightning"></i>
                            Quick Actions
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="card-list">
                            <li>
                                <a href="{{ route('admin.members.create') }}">
                                    <i class="bi bi-person-plus me-1"></i>
                                    Add New Member
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('admin.menus.create') }}">
                                    <i class="bi bi-plus me-1"></i>
                                    Add New Menu
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('admin.settings') }}">
                                    <i class="bi bi-gear me-1"></i>
                                    System Settings
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- 시스템 정보 -->
                <div class="card mt-4 flex-fill">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-info-circle me-2"></i>
                            System Information
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="card-list sys-info">
                            <li>
                                <span>Laravel Version</span>
                                <strong>{{ app()->version() }}</strong>
                            </li>
                            <li>
                                <span>PHP Version</span>
                                <strong>{{ PHP_VERSION }}</strong>
                            </li>
                            <li>
                                <span>Environment</span>
                                <strong>{{ strtoupper(app()->environment()) }}</strong>
                            </li>
                            <li>
                                <span>Server Time</span>
                                <strong>{{ now()->format('Y-m-d H:i:s') }}</strong>
                            </li>
                            <li>
                                <span>MySQL Time</span>
                                <strong>{{ \DB::selectOne('SELECT NOW() as now')->now ?? 'N/A' }}</strong>
                            </li>
                            <li>
                                <span>MySQL Version</span>
                                <strong>{{ \DB::selectOne('SELECT VERSION() as version')->version ?? 'N/A' }}</strong>
                            </li>
                            <li>
                                <span>Timezone</span>
                                <strong>{{ config('app.timezone') }}</strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 통계 요약 섹션 -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="bi bi-graph-up me-1"></i>
                        Recent Member Trends
                    </h5>
                </div>
                <div class="card-body d-flex align-items-center">
                    <div class="row text-center w-100">
                        <div class="col-4">
                            <h3 class="text-primary">{{ $memberStats['thisMonth'] ?? 0 }}</h3>
                            <small class="text-muted">This Month</small>
                        </div>
                        <div class="col-4">
                            <h3 class="text-info">{{ $memberStats['lastMonth'] ?? 0 }}</h3>
                            <small class="text-muted">Last Month</small>
                        </div>
                        <div class="col-4">
                            <h3 class="text-success">{{ $memberStats['growth'] ?? 0 }}%</h3>
                            <small class="text-muted">Growth Rate</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="bi bi-pie-chart me-1"></i>
                        Member Group Distribution
                    </h5>
                </div>
                <div class="card-body">
                    @if($groupStats ?? [])
                    @foreach($groupStats as $group)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>{{ $group['name'] }}</span>
                        <div>
                            <span class="badge bg-primary">{{ $group['count'] }} members</span>
                        </div>
                    </div>
                    @endforeach
                    @else
                    <p class="text-muted text-center">No group data available.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
