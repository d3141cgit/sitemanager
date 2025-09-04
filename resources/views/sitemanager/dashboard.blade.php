@extends('sitemanager::layouts.sitemanager')

@section('title', t('Dashboard'))
@section('page-title', t('Dashboard'))

@section('content')
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <a href="{{ route('sitemanager.boards.index') }}" class="text-decoration-none">
            <div class="card text-center shadow-sm h-100 card-hover">
                <div class="card-body py-3">
                    <div class="mb-2">
                        <i class="bi bi-journals fs-2 text-primary opacity-75"></i>
                    </div>
                    <div class="fw-bold text-dark small">{{ t('Boards') }}</div>
                    <div class="h5 mb-0 text-primary">{{ number_format($stats['total_boards']) }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center shadow-sm h-100">
            <div class="card-body py-3">
                <div class="mb-2">
                    <i class="bi bi-file-text fs-2 text-success opacity-75"></i>
                </div>
                <div class="fw-bold text-dark small">{{ t('Posts') }}</div>
                <div class="h5 mb-0 text-success">{{ number_format($stats['total_posts']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card text-center shadow-sm h-100">
            <div class="card-body py-3">
                <div class="mb-2">
                    <i class="bi bi-chat-dots fs-2 text-info opacity-75"></i>
                </div>
                <div class="fw-bold text-dark small">{{ t('Comments') }}</div>
                <div class="h5 mb-0 text-info">{{ number_format($stats['total_comments']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <a href="{{ route('sitemanager.files.board-attachments') }}" class="text-decoration-none">
            <div class="card text-center shadow-sm h-100 card-hover">
                <div class="card-body py-3">
                    <div class="mb-2">
                        <i class="bi bi-paperclip fs-2 text-warning opacity-75"></i>
                    </div>
                    <div class="fw-bold text-dark small">{{ t('Attachments') }}</div>
                    <div class="h5 mb-0 text-warning">{{ number_format($stats['total_attachments']) }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <a href="{{ route('sitemanager.files.editor-images') }}" class="text-decoration-none">
            <div class="card text-center shadow-sm h-100 card-hover">
                <div class="card-body py-3">
                    <div class="mb-2">
                        <i class="bi bi-images fs-2 text-purple opacity-75"></i>
                    </div>
                    <div class="fw-bold text-dark small">{{ t('Images') }}</div>
                    <div class="h5 mb-0 text-purple">{{ number_format($stats['total_editor_images']) }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <a href="{{ route('sitemanager.members.index') }}" class="text-decoration-none">
            <div class="card text-center shadow-sm h-100 card-hover">
                <div class="card-body py-3">
                    <div class="mb-2">
                        <i class="bi bi-people fs-2 text-secondary opacity-75"></i>
                    </div>
                    <div class="fw-bold text-dark small">{{ t('Members') }}</div>
                    <div class="h5 mb-0 text-secondary">{{ number_format($stats['total_members']) }}</div>
                </div>
            </div>
        </a>
    </div>
</div>

@if(isset($invalidRouteMenus) && count($invalidRouteMenus) > 0)
    <div class="mb-4">
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-exclamation-triangle fs-5 me-2"></i>
                <h6 class="mb-0 fw-bold">{{ t('Menu System Alert') }}</h6>
            </div>
            <p class="mb-2">
                <strong>{{ count($invalidRouteMenus) }} {{ t('menu(s)') }}</strong> {{ t('contain routes that no longer exist in the application.') }}
                {{ t('These menus will not function properly and need attention.') }}
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
                <a href="{{ route('sitemanager.menus.index') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-list me-1"></i>{{ t('Manage Menus') }}
                </a>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    </div>
@endif

<div class="row">
    <!-- 최근 게시글 -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title flex-grow-1 mb-0">
                        <i class="bi bi-file-text me-1"></i>
                        {{ t('Recent Posts') }}
                    </h5>
                    <a href="{{ route('sitemanager.boards.index') }}" class="text-decoration-none small">
                        {{ t('View All') }}
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                @if($recent_posts->count() > 0)
                    <div class="list-group list-group-flush">
                        @foreach($recent_posts as $post)
                            <div class="list-group-item d-flex justify-content-between align-items-start bg-transparent">
                                <div class="flex-grow-1">
                                    <div class="fw-bold">
                                        @if($post->board && $post->board->slug)
                                            <a href="{{ route('board.show', [$post->board->slug, $post->slug ?: $post->id]) }}" 
                                                target="_blank" class="text-decoration-none">
                                                {{ Str::limit($post->title, 50) }}
                                                <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.75rem;"></i>
                                            </a>
                                        @else
                                            {{ Str::limit($post->title, 50) }}
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <span class="badge bg-primary">{{ $post->board->name }}</span>
                                        <small class="text-muted">{{ $post->author_name }}</small>
                                        <small class="text-muted">{{ $post->created_at->diffForHumans() }}</small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="d-flex gap-2 text-muted small">
                                        <span><i class="bi bi-eye"></i> {{ number_format($post->view_count) }}</span>
                                        <span><i class="bi bi-chat"></i> {{ number_format($post->comment_count) }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="bi bi-file-text text-muted mb-3" style="font-size: 3rem;"></i>
                        <p class="text-muted">{{ t('No posts yet.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- 최근 댓글 -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title flex-grow-1 mb-0">
                        <i class="bi bi-chat-dots me-1"></i>
                        {{ t('Recent Comments') }}
                    </h5>
                    <a href="{{ route('sitemanager.comments.index') }}" class="text-decoration-none small">
                        {{ t('View All') }}
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                @if($recent_comments->count() > 0)
                    <div class="list-group list-group-flush">
                        @foreach($recent_comments as $comment)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start bg-transparent">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold">{{ $comment->author_name }}</div>
                                        <div class="text-muted small mb-1">
                                            @if($comment->board && $comment->board->slug && $comment->post_id)
                                                @php
                                                    $postModelClass = \SiteManager\Models\BoardPost::forBoard($comment->board->slug);
                                                    $post = $postModelClass::find($comment->post_id);
                                                @endphp
                                                @if($post)
                                                    <a href="{{ route('board.show', [$comment->board->slug, $post->slug ?: $post->id]) }}#comment-{{ $comment->id }}" 
                                                        target="_blank" class="text-decoration-none">
                                                        {{ Str::limit(strip_tags($comment->content), 60) }}
                                                        <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.75rem;"></i>
                                                    </a>
                                                @else
                                                    {{ Str::limit(strip_tags($comment->content), 60) }}
                                                @endif
                                            @else
                                                {{ Str::limit(strip_tags($comment->content), 60) }}
                                            @endif
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-info">{{ $comment->board->name }}</span>
                                            <span class="badge {{ $comment->status === 'approved' ? 'bg-success' : 'bg-warning' }}">
                                                {{ $comment->status === 'approved' ? t('Approved') : t('Pending') }}
                                            </span>
                                            <small class="text-muted">{{ $comment->created_at->diffForHumans() }}</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="bi bi-chat-dots text-muted mb-3" style="font-size: 3rem;"></i>
                        <p class="text-muted">{{ t('No comments yet.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- 게시판 통계 -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-bar-chart me-1"></i>
                    {{ t('Board Statistics') }}
                </h5>
            </div>
            <div class="card-body p-0">
                @if($board_stats->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>{{ t('Board') }}</th>
                                    <th class="text-center">{{ t('Posts') }}</th>
                                    <th class="text-center">{{ t('Comments') }}</th>
                                    <th class="text-end">{{ t('Last Post') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($board_stats as $stat)
                                    <tr>
                                        <td>
                                            <div class="fw-bold">{{ $stat['board']->name }}</div>
                                            <small class="text-muted">{{ $stat['board']->description }}</small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary rounded-pill">{{ number_format($stat['posts_count']) }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info rounded-pill">{{ number_format($stat['comments_count']) }}</span>
                                        </td>
                                        <td class="text-end text-muted small">
                                            {{ $stat['recent_post_date'] ? $stat['recent_post_date']->format('Y-m-d') : t('No posts') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="bi bi-journals text-muted mb-3" style="font-size: 3rem;"></i>
                        <p class="text-muted">{{ t('No boards created yet.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- 빠른 작업 & 최근 파일 -->
    <div class="col-lg-4 mb-4">
        <div class="d-flex flex-column h-100">
            <!-- 빠른 작업 -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning"></i>
                        {{ t('Quick Actions') }}
                    </h5>
                </div>
                <div class="card-body p-2">
                    <ul class="card-list mb-0">
                        <li>
                            <a href="{{ route('sitemanager.boards.create') }}">
                                <i class="bi bi-journal-plus me-1"></i>
                                {{ t('Create New Board') }}
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('sitemanager.members.create') }}">
                                <i class="bi bi-person-plus me-1"></i>
                                {{ t('Add New Member') }}
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('sitemanager.menus.create') }}">
                                <i class="bi bi-plus me-1"></i>
                                {{ t('Add New Menu') }}
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('sitemanager.languages.index') }}">
                                <i class="bi bi-translate me-1"></i>
                                {{ t('Manage Translations') }}
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('sitemanager.settings') }}">
                                <i class="bi bi-gear me-1"></i>
                                {{ t('System Settings') }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- 최근 파일 업로드 -->
            <div class="card mt-3 flex-fill">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-cloud-upload me-1"></i>
                        {{ t('Recent Files') }}
                    </h5>
                </div>
                <div class="card-body p-2">
                    @if($recent_files->count() > 0)
                        <ul class="card-list mb-0 p-2">
                            @foreach($recent_files as $file)
                                <li class="d-flex justify-content-between align-items-center border-bottom m-0 p-2">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold small">
                                            @if($file->board && $file->board->slug && $file->post_id)
                                                @php
                                                    $postModelClass = \SiteManager\Models\BoardPost::forBoard($file->board->slug);
                                                    $post = $postModelClass::find($file->post_id);
                                                @endphp
                                                @if($post)
                                                    <a href="{{ route('board.show', [$file->board->slug, $post->slug ?: $post->id]) }}" 
                                                        target="_blank" class="text-decoration-none">
                                                        {{ Str::limit($file->original_name, 25) }}
                                                        <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.65rem;"></i>
                                                    </a>
                                                @else
                                                    {{ Str::limit($file->original_name, 25) }}
                                                @endif
                                            @else
                                                <a href="{{ $file->download_url }}" target="_blank" class="text-decoration-none">
                                                    {{ Str::limit($file->original_name, 25) }}
                                                    <i class="bi bi-download ms-1" style="font-size: 0.65rem;"></i>
                                                </a>
                                            @endif
                                        </div>
                                        <div class="text-muted small">
                                            {{ $file->board ? $file->board->name : t('Unknown Board') }} • 
                                            {{ $file->human_size }}
                                        </div>
                                    </div>
                                    <small class="text-muted">{{ $file->created_at->diffForHumans() }}</small>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="text-center py-3">
                            <i class="bi bi-cloud-upload text-muted mb-2" style="font-size: 2rem;"></i>
                            <p class="text-muted small mb-0">{{ t('No files uploaded yet.') }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- 시스템 정보 -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        {{ t('System Information') }}
                    </h5>
                </div>
                <div class="card-body p-2">
                    <ul class="card-list sys-info m-0">
                        <li>
                            <span>{{ t('Laravel Version') }}</span>
                            <strong>{{ app()->version() }}</strong>
                        </li>
                        <li>
                            <span>{{ t('PHP Version') }}</span>
                            <strong>{{ PHP_VERSION }}</strong>
                        </li>
                        <li>
                            <span>{{ t('Environment') }}</span>
                            <strong>{{ strtoupper(app()->environment()) }}</strong>
                        </li>
                        <li>
                            <span>{{ t('Server Time') }}</span>
                            <strong>{{ now()->format('Y-m-d H:i:s') }}</strong>
                        </li>
                        <li>
                            <span>{{ t('MySQL Time') }}</span>
                            <strong>{{ \DB::selectOne('SELECT NOW() as now')->now ?? 'N/A' }}</strong>
                        </li>
                        <li>
                            <span>{{ t('MySQL Version') }}</span>
                            <strong>{{ \DB::selectOne('SELECT VERSION() as version')->version ?? 'N/A' }}</strong>
                        </li>
                        <li>
                            <span>{{ t('Timezone') }}</span>
                            <strong>{{ config('app.timezone') }}</strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
