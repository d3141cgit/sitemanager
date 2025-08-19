@extends('layouts.admin')

@section('title', 'Board Management')

@section('content')
<div class="container">

    <!-- Header Section - Responsive -->
    <div class="mb-4">
        <!-- Desktop Header -->
        <div class="d-none d-md-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0">
                <a href="{{ route('admin.boards.index') }}" class="text-decoration-none text-dark">
                    <i class="bi bi-list-ul opacity-75"></i> Board Management
                </a>
            </h1>
            <a href="{{ route('admin.boards.create') }}" class="btn btn-primary text-white">
                <i class="bi bi-plus-circle"></i> Add New Board
            </a>
        </div>

        <!-- Mobile Header -->
        <div class="d-md-none">
            <h4 class="mb-3">
                <a href="{{ route('admin.boards.index') }}" class="text-decoration-none text-dark">
                    <i class="bi bi-list-ul opacity-75"></i> Board Management
                </a>
            </h4>
            <div class="d-grid mb-3">
                <a href="{{ route('admin.boards.create') }}" class="btn btn-primary text-white">
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
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Board Name</th>
                    <th>Slug</th>
                    <th>Connected Menu</th>
                    <th class="text-center">Posts</th>
                    <th class="text-center">Comments</th>
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
                        <a href="{{ route('admin.boards.show', $board) }}" class="text-decoration-none">
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
                    <td class="text-center text-primary">
                        {{ !empty($board->posts_count) ?? $board->getPostsCount() }}
                    </td>
                    <td class="text-center text-success">
                        {{ !empty($board->comments_count) ?? $board->getCommentsCount() }}
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
                        <a href="{{ route('admin.boards.edit', $board) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="{{ route('admin.boards.toggle-status', $board) }}" class="d-inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-sm btn-outline-{{ $board->status === 'active' ? 'warning' : 'success' }}" 
                                    title="{{ $board->status === 'active' ? 'Deactivate' : 'Activate' }}"
                                    onclick="return confirm('Change status?')">
                                <i class="bi bi-{{ $board->status === 'active' ? 'pause' : 'play' }}"></i>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.boards.destroy', $board) }}" class="d-inline">
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
                    <td colspan="9" class="text-center py-5">
                        <i class="bi bi-list-ul display-1 text-muted mb-3"></i>
                        <h5 class="text-muted">No boards found</h5>
                        <p class="text-muted">Please create a new board.</p>
                        <a href="{{ route('admin.boards.create') }}" class="btn btn-primary">
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

