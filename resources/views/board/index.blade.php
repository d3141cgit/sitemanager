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
            @include('sitemanager::board.partials.posts', ['posts' => $posts, 'notices' => $notices, 'board' => $board])
        @endif

        @if(can('write', $board))
            <div class="board-footer">
                <a href="{{ route('board.create', $board->slug) }}" class="btn btn-dark text-nowrap">
                    <i class="bi bi-plus-lg me-2"></i> New Post
                </a>
            </div>
        @endif
    </div>
</main>
@endsection
