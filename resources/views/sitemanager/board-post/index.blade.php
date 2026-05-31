@extends('sitemanager::layouts.sitemanager')

@section('title', $board->name . ' Posts')

@section('content')
<div class="content-header">
    <h1>
        <a href="{{ route('sitemanager.boards.index') }}">
            <i class="bi bi-list-ul opacity-75"></i> {{ t('Board Management') }}
        </a>
        <i class="bi bi-chevron-right small opacity-50"></i>
        <a href="{{ route('sitemanager.boards.posts.index', $board) }}">{{ $board->name }} Posts</a>
        <span class="count">{{ number_format($posts->total()) }}</span>
    </h1>

    <div class="d-flex gap-1">
        <a href="{{ route('board.index', $board->slug) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-box-arrow-up-right"></i> Front
        </a>
        <a href="{{ route('sitemanager.boards.posts.create', $board) }}" class="btn-default">
            <i class="bi bi-plus-circle"></i> Add Post
        </a>
    </div>
</div>

<form method="GET" action="{{ route('sitemanager.boards.posts.index', $board) }}" class="search-form mb-3">
    <input type="hidden" name="orderby" value="{{ request('orderby', 'published_at') }}">
    <input type="hidden" name="desc" value="{{ request('desc', '1') }}">

    <div class="card">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div style="min-width:260px;flex:1">
                <label class="form-label">Search</label>
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Title, content, author">
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    @foreach(['published' => 'Published', 'draft' => 'Draft', 'pending' => 'Pending'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if($board->usesCategories() && count($board->getCategoryOptions()) > 0)
                <div>
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All</option>
                        @foreach($board->getCategoryOptions() as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search"></i> Search
            </button>
            <a href="{{ route('sitemanager.boards.posts.index', $board) }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-striped table-hover table-bordered align-middle">
        <thead>
            <tr>
                {!! sortHead('ID', 'id', 'right') !!}
                {!! sortHead('Title', 'title') !!}
                <th>Category</th>
                {!! sortHead('Author', 'author_name') !!}
                {!! sortHead('Status', 'status', 'text-center') !!}
                <th class="text-center">Stats</th>
                {!! sortHead('Published', 'published_at', 'text-center') !!}
                <th class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($posts as $post)
                <tr>
                    <td class="number right">{{ $post->id }}</td>
                    <td>
                        <a href="{{ route('sitemanager.boards.posts.edit', [$board, $post->id]) }}" class="fw-semibold">
                            {{ $post->title ?: '-' }}
                        </a>
                        @if($post->slug)
                            <div class="small text-muted">{{ $post->slug }}</div>
                        @endif
                    </td>
                    <td>
                        @forelse($post->categories as $category)
                            <span class="badge bg-light text-dark border">{{ $category }}</span>
                        @empty
                            <span class="text-muted">-</span>
                        @endforelse
                    </td>
                    <td>{{ $post->author_name ?: '-' }}</td>
                    <td class="text-center">
                        <span @class([
                            'badge',
                            'bg-success' => $post->status === 'published',
                            'bg-secondary' => $post->status === 'draft',
                            'bg-warning text-dark' => $post->status === 'pending',
                        ])>{{ ucfirst($post->status ?? 'draft') }}</span>
                    </td>
                    <td class="text-center small text-muted">
                        <span title="Views"><i class="bi bi-eye"></i> {{ number_format($post->view_count ?? 0) }}</span>
                        <span class="mx-1">·</span>
                        <span title="Comments"><i class="bi bi-chat"></i> {{ number_format($post->comment_count ?? 0) }}</span>
                    </td>
                    <td class="number text-center">{{ $post->published_at?->format('Y-m-d') ?: '-' }}</td>
                    <td class="text-center actions">
                        <a href="{{ route('board.show', [$board->slug, $post->slug ?: $post->id]) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="View">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <a href="{{ route('sitemanager.boards.posts.edit', [$board, $post->id]) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="{{ route('sitemanager.boards.posts.destroy', [$board, $post->id]) }}" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this post?')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <i class="bi bi-journal-text display-1 text-muted mb-3"></i>
                        <h5 class="text-muted">No posts found</h5>
                        <a href="{{ route('sitemanager.boards.posts.create', $board) }}" class="btn btn-primary mt-2">
                            <i class="bi bi-plus-circle"></i> Create Post
                        </a>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($posts->hasPages())
    {{ $posts->links('sitemanager::pagination.default') }}
@endif
@endsection
