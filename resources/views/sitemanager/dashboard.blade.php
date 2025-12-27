@extends('sitemanager::layouts.sitemanager')

@section('title', t('Dashboard'))

@section('content')
<div class="content-header">
    <h1>
        <i class="bi bi-speedometer2 opacity-75"></i>
        {{ t('Dashboard') }}
    </h1>
</div>

{{-- Statistics --}}
<div class="dashboard-stats">
    <a href="{{ route('sitemanager.boards.index') }}" class="stat-card">
        <div class="stat-icon text-primary">
            <i class="bi bi-journals"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">{{ t('Boards') }}</div>
            <div class="stat-value">{{ number_format($stats['total_boards']) }}</div>
        </div>
    </a>
    <div class="stat-card no-hover">
        <div class="stat-icon text-success">
            <i class="bi bi-file-text"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">{{ t('Posts') }}</div>
            <div class="stat-value">{{ number_format($stats['total_posts']) }}</div>
        </div>
    </div>
    <a href="{{ route('sitemanager.comments.index') }}" class="stat-card">
        <div class="stat-icon text-info">
            <i class="bi bi-chat-dots"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">{{ t('Comments') }}</div>
            <div class="stat-value">{{ number_format($stats['total_comments']) }}</div>
        </div>
    </a>
    <a href="{{ route('sitemanager.files.board-attachments') }}" class="stat-card">
        <div class="stat-icon text-warning">
            <i class="bi bi-paperclip"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">{{ t('Attachments') }}</div>
            <div class="stat-value">{{ number_format($stats['total_attachments']) }}</div>
        </div>
    </a>
    <a href="{{ route('sitemanager.files.editor-images') }}" class="stat-card">
        <div class="stat-icon text-purple">
            <i class="bi bi-images"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">{{ t('Images') }}</div>
            <div class="stat-value">{{ number_format($stats['total_editor_images']) }}</div>
        </div>
    </a>
    <a href="{{ route('sitemanager.members.index') }}" class="stat-card">
        <div class="stat-icon text-secondary">
            <i class="bi bi-people"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">{{ t('Members') }}</div>
            <div class="stat-value">{{ number_format($stats['total_members']) }}</div>
        </div>
    </a>
</div>

@if(isset($invalidRouteMenus) && count($invalidRouteMenus) > 0)
<div class="alert alert-warning mb-4">
    <div class="d-flex align-items-center mb-2">
        <i class="bi bi-exclamation-triangle fs-5 me-2"></i>
        <strong>{{ t('Menu System Alert') }}</strong>
    </div>
    <p class="mb-2">
        <strong>{{ count($invalidRouteMenus) }} {{ t('menu(s)') }}</strong> {{ t('contain routes that no longer exist in the application.') }}
    </p>
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach($invalidRouteMenus as $invalidMenu)
            <span class="badge bg-white text-dark border">
                {{ $invalidMenu['title'] }}
                <small class="text-muted">({{ $invalidMenu['target'] }})</small>
            </span>
        @endforeach
    </div>
    <a href="{{ route('sitemanager.menus.index') }}" class="btn btn-sm btn-outline-dark">
        <i class="bi bi-list me-1"></i>{{ t('Manage Menus') }}
    </a>
</div>
@endif

<div class="row">
    {{-- Main Content --}}
    <div class="col-lg-8">
        {{-- Recent Posts --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4><i class="bi bi-file-text"></i> {{ t('Recent Posts') }}</h4>
                <a href="{{ route('sitemanager.boards.index') }}" class="btn btn-sm btn-dark">{{ t('View All') }}</a>
            </div>
            <div class="card-body p-0">
                @if($recent_posts->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>{{ t('Title') }}</th>
                                    <th>{{ t('Board') }}</th>
                                    <th class="text-center">{{ t('Views') }}</th>
                                    <th class="text-center">{{ t('Date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recent_posts as $post)
                                    <tr>
                                        <td>
                                            @if($post->board && $post->board->slug)
                                                <a href="{{ route('board.show', [$post->board->slug, $post->slug ?: $post->id]) }}"
                                                    target="_blank">
                                                    <i class="bi bi-box-arrow-up-right me-2 small opacity-50"></i>
                                                    {{ $post->title ?? '-' }}
                                                </a>
                                            @else
                                                {{ $post->title ?? '-' }}
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('sitemanager.boards.show', $post->board->id) }}">
                                                {{ $post->board->name ?? '-' }}
                                            </a>
                                        </td>
                                        <td class="text-center number text-primary small">{{ number_format($post->view_count) }}</td>
                                        <td class="text-center number text-muted small">{{ $post->created_at->format('m-d H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-file-text fs-1 opacity-50"></i>
                        <p class="mt-2 mb-0">{{ t('No posts yet.') }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Board Statistics --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4><i class="bi bi-bar-chart"></i> {{ t('Board Statistics') }}</h4>
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
                                    <th class="text-center">{{ t('Last Post') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($board_stats as $stat)
                                    <tr>
                                        <td>
                                            <a href="{{ route('sitemanager.boards.show', $stat['board']->id) }}">
                                                {{ $stat['board']->name }}
                                            </a>
                                            @if($stat['board']->description)
                                                <small class="text-muted d-block">{{ $stat['board']->description }}</small>
                                            @endif
                                        </td>
                                        <td class="text-center number text-primary">
                                            {{ $stat['posts_count'] ? number_format($stat['posts_count']) : '-' }}
                                        </td>
                                        <td class="text-center number text-info">
                                            {{ $stat['comments_count'] ? number_format($stat['comments_count']) : '-' }}
                                        </td>
                                        <td class="text-center number text-muted small">
                                            {{ $stat['recent_post_date'] ? $stat['recent_post_date']->format('Y-m-d') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-journals fs-1 opacity-50"></i>
                        <p class="mt-2 mb-0">{{ t('No boards created yet.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="col-lg-4">
        {{-- Quick Actions & System Info --}}
        <div class="card mb-4">
            <div class="card-header">
                <h4><i class="bi bi-lightning"></i> {{ t('Quick Actions') }}</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div class="details">
                            <dl>    
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('sitemanager.boards.create') }}"><i class="bi bi-journal-plus text-primary"></i> {{ t('New Board') }}</a>
                            </dl>
                            <dl>
                                <a class="btn btn-sm btn-outline-success" href="{{ route('sitemanager.members.create') }}"><i class="bi bi-person-plus text-success"></i> {{ t('New Member') }}</a>
                            </dl>
                            <dl>
                                <a class="btn btn-sm btn-outline-info" href="{{ route('sitemanager.menus.create') }}"><i class="bi bi-plus-square text-info"></i> {{ t('New Menu') }}</a>
                            </dl>
                            <dl>
                                <a class="btn btn-sm btn-outline-warning" href="{{ route('sitemanager.languages.index') }}"><i class="bi bi-translate text-warning"></i> {{ t('Translations') }}</a>
                            </dl>
                            <dl>
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('sitemanager.settings') }}"><i class="bi bi-gear text-secondary"></i> {{ t('Settings') }}</a>
                            </dl>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="details">
                            <dl>
                                <dt>{{ t('Laravel') }}</dt>
                                <dd>{{ app()->version() }}</dd>
                            </dl>
                            <dl>
                                <dt>{{ t('PHP') }}</dt>
                                <dd>{{ PHP_VERSION }}</dd>
                            </dl>
                            <dl>
                                <dt>{{ t('Env') }}</dt>
                                <dd><span class="badge bg-{{ app()->environment() === 'production' ? 'danger' : 'warning' }}">{{ strtoupper(app()->environment()) }}</span></dd>
                            </dl>
                            <dl>
                                <dt>{{ t('Timezone') }}</dt>
                                <dd>{{ config('app.timezone') }}</dd>
                            </dl>
                            <dl>
                                <dt>{{ t('MySQL') }}</dt>
                                <dd>{{ \DB::selectOne('SELECT VERSION() as v')->v ?? 'N/A' }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Comments --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4><i class="bi bi-chat-dots"></i> {{ t('Recent Comments') }}</h4>
                <a href="{{ route('sitemanager.comments.index') }}" class="btn btn-sm btn-outline-dark">{{ t('View All') }}</a>
            </div>
            <div class="card-body p-0">
                @if($recent_comments->count() > 0)
                    <ul class="list-group list-group-flush">
                        @foreach($recent_comments->take(5) as $comment)
                            <li class="list-group-item px-3 py-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="small">
                                        @if($comment->board && $comment->board->slug && $comment->post_id)
                                            @php
                                                $postModelClass = \SiteManager\Models\BoardPost::forBoard($comment->board->slug);
                                                $post = $postModelClass::find($comment->post_id);
                                            @endphp
                                            @if($post)
                                                <a href="{{ route('board.show', [$comment->board->slug, $post->slug ?: $post->id]) }}#comment-{{ $comment->id }}"
                                                    target="_blank" class="text-decoration-none">
                                                    {{ Str::limit(strip_tags($comment->content), 50) }}
                                                </a>
                                            @else
                                                {{ Str::limit(strip_tags($comment->content), 50) }}
                                            @endif
                                        @else
                                            {{ Str::limit(strip_tags($comment->content), 50) }}
                                        @endif
                                    </div>
                                    <span class="badge {{ $comment->status === 'approved' ? 'bg-success' : 'bg-warning' }} ms-2">
                                        {{ $comment->status === 'approved' ? t('Approved') : t('Pending') }}
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <small class="text-muted">{{ $comment->author_name }}</small>
                                    <small class="text-muted">{{ $comment->created_at->diffForHumans() }}</small>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-chat-dots fs-2 opacity-50"></i>
                        <p class="small mb-0 mt-2">{{ t('No comments yet.') }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Recent Files --}}
        <div class="card mb-4">
            <div class="card-header">
                <h4><i class="bi bi-cloud-upload"></i> {{ t('Recent Files') }}</h4>
            </div>
            <div class="card-body p-0">
                @if($recent_files->count() > 0)
                    <ul class="list-group list-group-flush">
                        @foreach($recent_files->take(5) as $file)
                            <li class="list-group-item px-3 py-2 d-flex justify-content-between align-items-center">
                                <div>
                                    @if($file->board && $file->board->slug && $file->post_id)
                                        @php
                                            $postModelClass = \SiteManager\Models\BoardPost::forBoard($file->board->slug);
                                            $post = $postModelClass::find($file->post_id);
                                        @endphp
                                        @if($post)
                                            <a href="{{ route('board.show', [$file->board->slug, $post->slug ?: $post->id]) }}"
                                                target="_blank">
                                                {{ $file->original_name ?? '-' }}
                                            </a>
                                        @else
                                            <span class="small">{{ $file->original_name ?? '-' }}</span>
                                        @endif
                                    @else
                                        <a href="{{ $file->download_url }}" target="_blank">
                                            {{ $file->original_name ?? '-' }}
                                        </a>
                                    @endif
                                    <small class="text-muted d-block">{{ $file->human_size }}</small>
                                </div>
                                <small class="text-muted">{{ $file->created_at->diffForHumans() }}</small>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-cloud-upload fs-2 opacity-50"></i>
                        <p class="small mb-0 mt-2">{{ t('No files uploaded yet.') }}</p>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
