@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', $board->name)

@push('head')
    {!! resource('sitemanager::js/image-optimizer.js') !!}
    {!! resource('sitemanager::css/board.default.css') !!}
    {!! resource('sitemanager::css/pagination.css') !!}
@endpush

@section('content')
<main class="board">
    <div class="container">
        <div class="board-header">
            <h1>
                {{ $board->name }}
                <span class="total-count">{{ $board->getSetting('enable_notice', false)  ? number_format($posts->total() + $notices->count()) : number_format($posts->total()) }}</span>
            </h1>
            
            @if ($board->getSetting('enable_search', false))
            <form method="GET" class="board-search-form">
                <div class="input-group input-group-sm">
                    @if($usesCategories)
                        <select class="form-select" name="category" onchange="this.form.submit()">
                            <option value="">
                                All Categories ({{ number_format($totalPosts) }})
                            </option>
                            @foreach($categoriesWithCounts as $categoryData)
                                <option value="{{ $categoryData['name'] }}" {{ $currentCategory === $categoryData['name'] ? 'selected' : '' }}>
                                    {{ $categoryData['name'] }} ({{ number_format($categoryData['count']) }})
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <input type="text" name="search" class="form-control" placeholder="Search {{ $board->name }}" value="{{ $currentSearch ?? '' }}">
                    <button type="submit" class="btn btn-outline-dark"><i class="bi bi-search"></i></button>

                    @if($hasAnyFilter)
                        <a href="{{ route('board.index', $board->slug) }}" class="btn btn-outline-danger" title="Clear Filters">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    @endif
                </div>
            </form>
            @endif
        </div>

        @if($posts->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle board-posts-table">
                    <thead>
                        <tr>
                            <th width="80"> # </th>
                            <th> Title </th>
                            @if ($board->getSetting('show_name', false))
                            <th class="text-center" width="200"> Author </th>
                            @endif
                            <th class="text-center" width="100"> Views </th>
                            <th class="text-center" width="100"> Comments </th>
                            @if ($board->getSetting('enable_likes', false))
                            <th class="text-center" width="100"> Likes </th>
                            @endif
                            <th class="text-center" width="140"> Date </th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($board->getSetting('enable_notice', false) && isset($notices) && $notices->count() > 0)
                            @foreach($notices as $post)
                            <tr class="notice">
                                <td><i class="bi bi-flag-fill"></i></td>

                                <td class="text-start">
                                    <div class="post-title">
                                        @if($post->category)
                                            <div class="post-categories">
                                                @foreach($post->categories as $category)
                                                    <span>{{ $category }}</span>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if($post->isSecret())
                                            <i class="bi bi-lock-fill text-warning me-1"></i>
                                        @endif

                                        <a href="{{ route('board.show', [$board->slug, $post->slug ?: $post->id]) }}">
                                            {{ $post->title }}
                                        </a>
                                    </div>
                                </td>

                                @if ($board->getSetting('show_name', false))
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center">
                                        @if($post->author_profile_photo)
                                            <img src="{{ $post->author_profile_photo }}" 
                                                    alt="{{ $post->author }}" 
                                                    class="rounded-circle me-2"
                                                    style="width: 24px; height: 24px; object-fit: cover;">
                                        @endif
                                        <span class="text-secondary fw-medium small">{{ $post->author }}</span>
                                    </div>
                                </td>
                                @endif

                                <td class="text-center">
                                    <span class="text-muted small">
                                        {{ $post->view_count ? number_format($post->view_count):'-' }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    <span class="text-muted small">
                                        {{ $post->comment_count ? number_format($post->comment_count) : '-' }}
                                    </span>
                                </td>

                                @if ($board->getSetting('enable_likes', false))
                                <td class="text-center">
                                    <span class="text-muted small">
                                        {{ $post->like_count ? number_format($post->like_count) : '-' }}
                                    </span>
                                </td>
                                @endif
                                
                                <td class="text-center">
                                    <time class="text-muted small">{{ $post->created_at->format('M j, Y') }}</time>
                                </td>
                            </tr>
                            @endforeach
                        @endif

                        @foreach($posts as $post)
                            <tr>
                                <td>
                                    {{ $post->id }}
                                </td>

                                <td class="text-start">
                                    <div class="post-title">
                                        @if($post->category)
                                            <div class="post-categories">
                                                @foreach($post->categories as $category)
                                                    <span>{{ $category }}</span>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if($post->isSecret())
                                            <i class="bi bi-lock-fill text-warning me-1"></i>
                                        @endif

                                        <a href="{{ route('board.show', [$board->slug, $post->slug ?: $post->id]) }}">
                                            {{ $post->title }}
                                        </a>
                                    </div>
                                </td>

                                @if ($board->getSetting('show_name', false))
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center">
                                        @if($post->author_profile_photo)
                                            <img src="{{ $post->author_profile_photo }}" 
                                                    alt="{{ $post->author }}" 
                                                    class="rounded-circle me-2"
                                                    style="width: 24px; height: 24px; object-fit: cover;">
                                        @endif
                                        <span class="text-secondary fw-medium small">{{ $post->author }}</span>
                                    </div>
                                </td>
                                @endif

                                <td class="text-center">
                                    <span class="text-muted small">
                                        {{ $post->view_count ? number_format($post->view_count):'-' }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    <span class="text-muted small">
                                        {{ $post->comment_count ? number_format($post->comment_count) : '-' }}
                                    </span>
                                </td>

                                @if ($board->getSetting('enable_likes', false))
                                <td class="text-center">
                                    <span class="text-muted small">
                                        {{ $post->like_count ? number_format($post->like_count) : '-' }}
                                    </span>
                                </td>
                                @endif

                                <td class="text-center">
                                    <time class="text-muted small">{{ $post->created_at->format('M j, Y') }}</time>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($posts->hasPages())
                <div class="d-none d-md-block">
                {{ $posts->appends(request()->query())->onEachSide(1)->links('sitemanager::pagination.default') }}
                </div>
                <div class="d-md-none">
                    {{ $posts->appends(request()->query())->links('sitemanager::pagination.simple') }}
                </div>
            @endif
        @endif

        @if(can('write', $board))
            <div class="board-footer">
                <a href="{{ route('board.create', $board->slug) }}" class="btn btn-primary rounded-pill text-nowrap px-4 shadow-sm">
                    <i class="bi bi-plus-lg me-2"></i> New Post
                </a>
            </div>
        @endif
    </div>
</main>
@endsection
