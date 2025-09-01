@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', $board->name)

@section('content')
<div class="container py-4">
    <!-- Board Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h1 class="h3 mb-1">{{ $board->name }}</h1>
                </div>
                <div class="d-flex gap-2">
                    @if(can('write', $board))
                        <a href="{{ route('board.create', $board->slug) }}" class="btn btn-primary">
                            <i class="bi bi-pencil-square"></i> New Post
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body py-3">
                    <form method="GET" class="d-flex gap-3 align-items-center flex-wrap">
                        <!-- Category Filter -->
                        @if($board->getSetting('use_categories', false) && count($board->getCategoryOptions()) > 0)
                            @php
                                $categoriesWithCounts = $board->getCategoryOptionsWithCounts();
                                $totalPosts = $board->getPostsCount();
                                $currentCategory = request('category');
                                $currentSearch = request('search');
                            @endphp
                            <div class="d-flex align-items-center">
                                <label class="form-label me-2 mb-0">Category:</label>
                                <select name="category" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                    <option value="">
                                        All Categories ({{ number_format($totalPosts) }})
                                    </option>
                                    @foreach($categoriesWithCounts as $categoryData)
                                        <option value="{{ $categoryData['name'] }}" {{ $currentCategory === $categoryData['name'] ? 'selected' : '' }}>
                                            {{ $categoryData['name'] }} ({{ number_format($categoryData['count']) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <!-- Search -->
                        <div class="d-flex align-items-center flex-grow-1" style="max-width: 400px;">
                            <label class="form-label me-2 mb-0">Search:</label>
                            <div class="input-group input-group-sm">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search posts..." value="{{ request('search') }}">
                                <button type="submit" class="btn btn-outline-secondary">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>

                        @if(request('category') || request('search'))
                            <a href="{{ route('board.index', $board->slug) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Clear
                            </a>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Notices -->
    @if(isset($notices) && $notices->count() > 0)
        <div class="row mb-3">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning bg-opacity-10">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-megaphone text-warning"></i> Notices
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            @foreach($notices as $notice)
                                <a href="{{ route('board.show', [$board->slug, $notice->slug ?: $notice->id]) }}" 
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="flex-grow-1">
                                            <span class="badge bg-warning text-dark me-2">Notice</span>
                                            @if($notice->isSecret())
                                                <i class="bi bi-lock-fill text-warning me-1"></i>
                                                <strong>{{ $notice->title }}</strong>
                                                <small class="text-muted">(비밀글)</small>
                                            @else
                                                <strong>{{ $notice->title }}</strong>
                                            @endif
                                        </div>
                                        <div class="text-muted small d-none d-md-block">
                                            {{ $notice->created_at->format('M j') }}
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Posts List -->
    <div class="row">
        <div class="col-12">
            @if($posts->count() > 0)
                <div class="card">
                    <div class="card-body p-0">
                        <!-- Desktop Table View -->
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="60">#</th>
                                        <th>Title</th>
                                        <th width="120" class="text-center">Author</th>
                                        <th width="80" class="text-center">Views</th>
                                        <th width="80" class="text-center">Comments</th>
                                        <th width="120" class="text-center">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($posts as $post)
                                        <tr>
                                            <td class="text-muted">{{ $post->id }}</td>
                                            <td>
                                                <a href="{{ route('board.show', [$board->slug, $post->slug ?: $post->id]) }}" 
                                                   class="text-decoration-none">
                                                    @if($post->isSecret())
                                                        <i class="bi bi-lock-fill text-warning me-1"></i>
                                                        {{ $post->title }}
                                                        <small class="text-muted">(비밀글)</small>
                                                    @else
                                                        {{ $post->title }}
                                                    @endif
                                                    @if($post->comment_count > 0)
                                                        <span class="badge bg-primary rounded-pill ms-1">{{ $post->comment_count }}</span>
                                                    @endif
                                                </a>
                                                @if($post->category)
                                                    <br><small class="badge bg-secondary">{{ $post->category }}</small>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <small>
                                                    @if($post->author_profile_photo)
                                                        <img src="{{ $post->author_profile_photo }}" 
                                                             alt="{{ $post->author }}" 
                                                             class="profile-photo me-1"
                                                             style="width: 18px; height: 18px; border-radius: 50%; object-fit: cover;">
                                                    @endif
                                                    {{ $post->author }}
                                                </small>
                                            </td>
                                            <td class="text-center text-muted">
                                                <small>{{ number_format($post->view_count) }}</small>
                                            </td>
                                            <td class="text-center text-muted">
                                                <small>{{ $post->comment_count }}</small>
                                            </td>
                                            <td class="text-center text-muted">
                                                <small>{{ $post->created_at->format('M j') }}</small>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Card View -->
                        <div class="d-md-none">
                            @foreach($posts as $post)
                                <div class="border-bottom p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <a href="{{ route('board.show', [$board->slug, $post->slug ?: $post->id]) }}" 
                                           class="text-decoration-none flex-grow-1">
                                            <h6 class="mb-1">
                                                @if($post->isSecret())
                                                    <i class="bi bi-lock-fill text-warning me-1"></i>
                                                    {{ $post->title }}
                                                    <small class="text-muted">(비밀글)</small>
                                                @else
                                                    {{ $post->title }}
                                                @endif
                                            </h6>
                                        </a>
                                        @if($post->comment_count > 0)
                                            <span class="badge bg-primary rounded-pill ms-2">{{ $post->comment_count }}</span>
                                        @endif
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center text-muted small">
                                        <div>
                                            <span>
                                                @if($post->author_profile_photo)
                                                    <img src="{{ $post->author_profile_photo }}" 
                                                         alt="{{ $post->author }}" 
                                                         class="profile-photo me-1"
                                                         style="width: 18px; height: 18px; border-radius: 50%; object-fit: cover;">
                                                @endif
                                                {{ $post->author }}
                                            </span>
                                            @if($post->category)
                                                <span class="badge bg-secondary ms-1">{{ $post->category }}</span>
                                            @endif
                                        </div>
                                        <div>
                                            <i class="bi bi-eye"></i> {{ number_format($post->view_count) }}
                                            <span class="ms-2">{{ $post->created_at->format('M j') }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                @if($posts->hasPages())
                    <div class="d-flex justify-content-center mt-4">
                        {{ $posts->links() }}
                    </div>
                @endif
            @else
                <!-- Empty State -->
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-journal-text display-1 text-muted mb-3"></i>
                        <h5 class="text-muted">No posts found</h5>
                        <p class="text-muted">
                            @if(request('search') || request('category'))
                                No posts match your search criteria.
                            @else
                                This board doesn't have any posts yet.
                            @endif
                        </p>
                        @if(can('write', $board))
                            <a href="{{ route('board.create', $board->slug) }}" class="btn btn-primary">
                                <i class="bi bi-pencil-square"></i> Write First Post
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
@media (max-width: 768px) {
    .container {
        padding-left: 15px;
        padding-right: 15px;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .btn {
        font-size: 0.875rem;
    }
}

.table th {
    border-top: none;
    font-weight: 600;
    font-size: 0.875rem;
}

.table td {
    vertical-align: middle;
}

.badge {
    font-size: 0.75em;
}

.list-group-item:hover {
    background-color: var(--bs-gray-50);
}
</style>
@endpush
