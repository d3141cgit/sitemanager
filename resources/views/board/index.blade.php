@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', $board->name)

@section('content')
<div class="container board">
    <!-- Board Header -->
    <div class="d-flex justify-content-between align-items-center pb-4">
        <h1 class="mb-0 text-dark">{{ $board->name }}</h1>
        <div>
            @if(can('write', $board))
                <a href="{{ route('board.create', $board->slug) }}" 
                    class="btn btn-primary rounded-pill px-4 shadow-sm">
                    <i class="bi bi-plus-lg me-2"></i>New Post
                </a>
            @endif
        </div>
    </div>

    <!-- Search & Filter -->
    <form method="GET" class="d-flex gap-4 align-items-center flex-wrap mb-4">
        <!-- Category Filter -->
        @if($usesCategories)
            <div class="d-flex align-items-center">
                <label class="form-label me-3 mb-0 text-secondary">Category</label>
                <select name="category" class="form-select" style="min-width: 180px;" onchange="this.form.submit()">
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
            <label class="form-label me-3 mb-0 text-secondary">Search</label>
            <div class="input-group">
                <input type="text" name="search" class="form-control border-end-0" 
                        placeholder="Search posts..." value="{{ $currentSearch ?? '' }}">
                <button type="submit" class="btn btn-outline-primary border-start-0">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </div>

        @if($hasAnyFilter)
            <a href="{{ route('board.index', $board->slug) }}" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle me-1"></i>Clear
            </a>
        @endif
    </form>

    <!-- Notices -->
    @if(isset($notices) && $notices->count() > 0)
        <div class="notice-section my-4">
            <h5 class="mb-3 fw-bold text-dark">
                <i class="bi bi-megaphone text-warning me-2"></i>Notices
            </h5>
            <div class="notice-list">
                @foreach($notices as $notice)
                    <div class="notice-item border-bottom py-2 hover-bg-light">
                        <a href="{{ route('board.show', [$board->slug, $notice->slug ?: $notice->id]) }}" 
                            class="text-decoration-none d-block">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="flex-grow-1">
                                    <span class="badge bg-warning text-dark rounded-pill me-3 px-3 py-1">Notice</span>
                                    @if($notice->isSecret())
                                        <i class="bi bi-lock-fill text-warning me-2"></i>
                                        <span class="text-dark">{{ $notice->title }}</span>
                                        <small class="text-muted ms-2">(Private)</small>
                                    @else
                                        <span class="text-dark">{{ $notice->title }}</span>
                                    @endif
                                </div>
                                <div class="text-muted small d-none d-md-block">
                                    <i class="bi bi-calendar3 me-1"></i>{{ $notice->created_at->format('M j') }}
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Posts List -->
    @if($posts->count() > 0)
        <div class="posts-section">
            <!-- Desktop Table View -->
            <div class="d-none d-lg-block">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead>
                            <tr>
                                <th width="80">
                                    #
                                </th>
                                <th>
                                    Title
                                </th>
                                <th class="text-center" width="200">
                                    Author
                                </th>
                                <th class="text-center" width="100">
                                    Views
                                </th>
                                <th class="text-center" width="100">
                                    Comments
                                </th>
                                <th class="text-center" width="140">
                                    Date
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($posts as $post)
                                <tr>
                                    <td>
                                        {{ $post->id }}
                                    </td>
                                    <td>
                                        <a href="{{ route('board.show', [$board->slug, $post->slug ?: $post->id]) }}">
                                            <div class="d-flex align-items-center">
                                                @if($post->isSecret())
                                                    <i class="bi bi-lock-fill text-warning me-2"></i>
                                                @endif
                                                
                                                <span class="text-dark">{{ $post->title }}</span>

                                                @if($post->comment_count > 0)
                                                    <span class="badge bg-primary rounded-pill ms-2 px-2">{{ $post->comment_count }}</span>
                                                @endif
                                            </div>
                                            @if($post->category)
                                                <div class="mt-2">
                                                    <span class="badge bg-light text-secondary rounded-pill px-3 py-1">{{ $post->category }}</span>
                                                </div>
                                            @endif
                                        </a>
                                    </td>
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
                                    <td class="text-center">
                                        <span class="text-muted small">
                                            <i class="bi bi-eye me-1"></i>{{ number_format($post->view_count) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-muted small">
                                            <i class="bi bi-chat-dots me-1"></i>{{ $post->comment_count }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-muted small">
                                            <i class="bi bi-calendar3 me-1"></i>{{ $post->created_at->format('M j') }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Mobile Card View -->
            <div class="d-lg-none">
                @foreach($posts as $post)
                    <div class="post-item border-bottom border-light last-border-0">
                        <a href="{{ route('board.show', [$board->slug, $post->slug ?: $post->id]) }}" 
                            class="text-decoration-none d-block">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <h6 class="mb-2 text-dark hover-text-primary">
                                        @if($post->isSecret())
                                            <i class="bi bi-lock-fill text-warning me-2"></i>
                                        @endif
                                        {{ $post->title }}
                                    </h6>
                                    @if($post->category)
                                        <span class="badge bg-light text-secondary rounded-pill px-3 py-1 me-2">{{ $post->category }}</span>
                                    @endif
                                </div>
                                @if($post->comment_count > 0)
                                    <span class="badge bg-primary rounded-pill ms-3 px-2">{{ $post->comment_count }}</span>
                                @endif
                            </div>
                        </a>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                @if($post->author_profile_photo)
                                    <img src="{{ $post->author_profile_photo }}" 
                                            alt="{{ $post->author }}" 
                                            class="rounded-circle me-2"
                                            style="width: 20px; height: 20px; object-fit: cover;">
                                @endif
                                <span class="text-secondary small fw-medium">{{ $post->author }}</span>
                            </div>
                            <div class="text-muted small d-flex align-items-center gap-3">
                                <span><i class="bi bi-eye me-1"></i>{{ number_format($post->view_count) }}</span>
                                <span><i class="bi bi-calendar3 me-1"></i>{{ $post->created_at->format('M j') }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Pagination -->
        @if($posts->hasPages())
            <div class="d-flex justify-content-center my-5">
                <nav aria-label="Page navigation">
                    <div class="d-none d-md-block">
                        {{ $posts->appends(request()->query())->onEachSide(1)->links('pagination::bootstrap-4') }}
                    </div>
                    <div class="d-md-none">
                        {{ $posts->appends(request()->query())->links('pagination::simple-bootstrap-4') }}
                    </div>
                </nav>
            </div>
        @endif
    @else
        <!-- Empty State -->
        <div class="text-center py-5">
            <div>
                <div class="mb-4">
                    <i class="bi bi-journal-text text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
                <h5 class="text-muted mb-3 fw-semibold">No posts found</h5>
                <p class="text-muted mb-4">
                    @if($hasAnyFilter)
                        No posts match your search criteria.
                    @else
                        This board doesn't have any posts yet.
                    @endif
                </p>
                @if(can('write', $board))
                    <a href="{{ route('board.create', $board->slug) }}" 
                        class="btn btn-primary rounded-pill px-4 shadow-sm">
                        <i class="bi bi-plus-lg me-2"></i>Write First Post
                    </a>
                @endif
            </div>
        </div>
    @endif

</div>
@endsection
