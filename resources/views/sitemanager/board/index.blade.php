@extends('sitemanager::layouts.sitemanager')

@section('title', 'Board Management')

@section('content')
<div class="container board">

    <!-- Header Section - Responsive -->
    <div class="mb-4">
        <!-- Desktop Header -->
        <div class="d-none d-md-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0">
                <a href="{{ route('sitemanager.boards.index') }}" class="text-decoration-none text-dark">
                    <i class="bi bi-list-ul opacity-75"></i> Board Management
                </a>
            </h1>
            <a href="{{ route('sitemanager.boards.create') }}" class="btn btn-primary text-white">
                <i class="bi bi-plus-circle"></i> Add New Board
            </a>
        </div>

        <!-- Mobile Header -->
        <div class="d-md-none">
            <h4 class="mb-3">
                <a href="{{ route('sitemanager.boards.index') }}" class="text-decoration-none text-dark">
                    <i class="bi bi-list-ul opacity-75"></i> Board Management
                </a>
            </h4>
            <div class="d-grid mb-3">
                <a href="{{ route('sitemanager.boards.create') }}" class="btn btn-primary text-white">
                    <i class="bi bi-plus-circle me-2"></i>Add New Board
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Board Name</th>
                    <th>Slug</th>
                    <th>Connected Menu</th>
                    <th class="text-center">Posts</th>
                    <th class="text-center">Comments</th>
                    <th class="text-center">Files</th>
                    <th class="text-center">Pending Comments</th>
                    <th>Status</th>
                    <th>Created Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($boards as $board)
                <tr>
                    <td>{{ $board->id }}</td>
                    <td>
                        <a href="{{ route('sitemanager.boards.show', $board) }}" class="text-decoration-none">
                            <strong>{{ $board->name }}</strong>
                        </a>
                    </td>
                    <td>
                        <code>{{ $board->slug }}</code>
                    </td>
                    <td>
                        @if($board->menu)
                            <span class="badge bg-info">{{ $board->menu->title }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @php
                            $postsCount = $board->posts_count ?? $board->getPostsCount();
                            $deletedPostsCount = $board->deleted_posts_count ?? $board->getDeletedPostsCount();
                        @endphp
                        
                        @if($postsCount == 0 && $deletedPostsCount == 0)
                            <span class="text-muted">-</span>
                        @else
                            <span class="text-primary fw-bold">{{ $postsCount }}</span>
                            @if($deletedPostsCount > 0)
                                <span class="text-danger"> / {{ $deletedPostsCount }}</span>
                            @endif
                        @endif
                    </td>
                    <td class="text-center">
                        @php
                            $commentsCount = $board->comments_count ?? $board->getCommentsCount();
                            $deletedCommentsCount = $board->deleted_comments_count ?? $board->getDeletedCommentsCount();
                        @endphp
                        
                        @if($commentsCount == 0 && $deletedCommentsCount == 0)
                            <span class="text-muted">-</span>
                        @else
                            <span class="text-success fw-bold">{{ $commentsCount }}</span>
                            @if($deletedCommentsCount > 0)
                                <span class="text-danger"> / {{ $deletedCommentsCount }}</span>
                            @endif
                        @endif
                    </td>
                    <td class="text-center">
                        @php
                            $attachmentsCount = $board->attachments_count ?? $board->getAttachmentsCount();
                        @endphp
                        
                        @if($attachmentsCount == 0)
                            <span class="text-muted">-</span>
                        @else
                            <span class="text-info fw-bold">{{ $attachmentsCount }}</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($board->pending_comments_count > 0)
                            <a href="{{ route('sitemanager.comments.index', ['board_id' => $board->id, 'status' => 'pending']) }}" 
                               class="badge bg-warning text-decoration-none" title="Manage pending comments">
                                {{ $board->pending_comments_count }}
                            </a>
                        @else
                            <small class="text-muted">-</small>
                        @endif
                    </td>
                    <td>
                        @if($board->status === 'active')
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>{{ $board->created_at->format('Y-m-d') }}</td>
                    <td class="text-end">
                        <a href="{{ route('sitemanager.boards.edit', $board) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="{{ route('sitemanager.boards.toggle-status', $board) }}" class="d-inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-sm btn-outline-{{ $board->status === 'active' ? 'warning' : 'success' }}" 
                                    title="{{ $board->status === 'active' ? 'Deactivate' : 'Activate' }}"
                                    onclick="return confirm('Change status?')">
                                <i class="bi bi-{{ $board->status === 'active' ? 'pause' : 'play' }}"></i>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('sitemanager.boards.destroy', $board) }}" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"
                                    onclick="return confirm('Delete this board?\\n\\n⚠️ Warning: All posts and comments will be deleted!')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="text-center py-5">
                        <i class="bi bi-list-ul display-1 text-muted mb-3"></i>
                        <h5 class="text-muted">No boards found</h5>
                        <p class="text-muted">Please create a new board.</p>
                        <a href="{{ route('sitemanager.boards.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Create Board
                        </a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($boards->hasPages())
        <div class="d-flex justify-content-center mt-4">
            {{ $boards->links() }}
        </div>
    @endif
</div>
@endsection

