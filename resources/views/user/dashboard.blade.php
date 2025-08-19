@extends('layouts.app')

@section('title', '내 대시보드')

@section('page-header')
<div class="text-center">
    <h1 class="h2 mb-3 text-white">
        안녕하세요, {{ $user->name }}님!
    </h1>
    <p class="lead mb-0 opacity-90">
        회원 정보와 활동 내역을 확인하세요
    </p>
</div>
@endsection

@section('content')
<div class="row">
    <!-- 프로필 카드 -->
    <div class="col-lg-4 mb-4">
        <div class="profile-card">
            <img src="{{ $user->profile_photo ? asset('storage/' . $user->profile_photo) : 'https://via.placeholder.com/100' }}" 
                 alt="프로필" class="profile-avatar">
            <h3 class="profile-name">{{ $user->name }}</h3>
            <p class="profile-email">{{ $user->email }}</p>
            <span class="profile-level">{{ $user->level_name ?? '일반회원' }}</span>
            
            <div class="mt-4">
                <a href="{{ route('user.profile.edit') }}" class="btn btn-user btn-user-outline me-2">
                    <i class="fas fa-edit me-1"></i>프로필 수정
                </a>
                <a href="{{ route('user.password.change') }}" class="btn btn-user btn-user-outline">
                    <i class="fas fa-key me-1"></i>비밀번호 변경
                </a>
            </div>
        </div>
    </div>

    <!-- 정보 섹션 -->
    <div class="col-lg-8 mb-4">
        <div class="row">
            <!-- 내 정보 -->
            <div class="col-md-6 mb-4">
                <div class="user-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-user me-2"></i>
                            내 정보
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="info-item">
                            <span class="info-label">이름</span>
                            <span class="info-value">{{ $user->name }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">이메일</span>
                            <span class="info-value">{{ $user->email }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">전화번호</span>
                            <span class="info-value">{{ $user->phone ?? '미등록' }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">가입일</span>
                            <span class="info-value">{{ $user->created_at->format('Y년 m월 d일') }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">상태</span>
                            <span class="info-value">
                                <span class="status-badge {{ $user->active ? 'active' : 'inactive' }}">
                                    {{ $user->active ? '활성' : '비활성' }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 내 그룹 -->
            <div class="col-md-6 mb-4">
                <div class="user-card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">
                                <i class="fas fa-users me-2"></i>
                                내 그룹
                            </h5>
                            <a href="{{ route('user.groups') }}" class="btn btn-sm btn-user-outline">
                                전체 보기
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($user->groups && $user->groups->count() > 0)
                            @foreach($user->groups->take(3) as $group)
                                <span class="group-badge">{{ $group->name }}</span>
                            @endforeach
                            @if($user->groups->count() > 3)
                                <span class="group-badge">+{{ $user->groups->count() - 3 }}개 더</span>
                            @endif
                        @else
                            <p class="text-muted small mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                가입된 그룹이 없습니다.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- 활동 통계 -->
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="stats-widget">
                    <div class="stats-icon text-primary">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stats-number">{{ $user->groups->count() }}</div>
                    <div class="stats-label">가입 그룹</div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="stats-widget">
                    <div class="stats-icon text-success">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stats-number">{{ $user->created_at->diffInDays() }}</div>
                    <div class="stats-label">가입 경과일</div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="stats-widget">
                    <div class="stats-icon text-info">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stats-number">{{ $user->updated_at->diffInDays() }}</div>
                    <div class="stats-label">최근 업데이트</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 최근 활동 -->
<div class="row">
    <div class="col-12">
        <div class="user-card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-history me-2"></i>
                    최근 활동
                </h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">프로필 업데이트</h6>
                            <p class="timeline-text text-muted">
                                프로필 정보를 수정했습니다.
                            </p>
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                {{ $user->updated_at->diffForHumans() }}
                            </small>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">계정 생성</h6>
                            <p class="timeline-text text-muted">
                                사이트에 가입했습니다.
                            </p>
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                {{ $user->created_at->diffForHumans() }}
                            </small>
                        </div>
                    </div>
                </div>

                @if($user->groups->isEmpty())
                <div class="alert alert-info mt-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle me-3 fa-2x"></i>
                        <div>
                            <h6 class="alert-heading">그룹에 참여해보세요!</h6>
                            <p class="mb-0">다양한 그룹에 참여하여 더 많은 활동을 즐겨보세요.</p>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
/* 타임라인 스타일 */
.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0.875rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 2rem;
}

.timeline-marker {
    position: absolute;
    left: -2.125rem;
    top: 0.25rem;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    border: 2px solid white;
}

.timeline-content {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 0.5rem;
    border-left: 3px solid #e9ecef;
}

.timeline-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.timeline-text {
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}
</style>
@endpush
