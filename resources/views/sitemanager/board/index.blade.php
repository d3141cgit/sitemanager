@extends('sitemanager::layouts.sitemanager')

@section('title', t('Board Management'))

@section('content')
<div class="content-header">
    <h1>
        <a href="{{ route('sitemanager.boards.index') }}">
            <i class="bi bi-list-ul opacity-75"></i> {{ t('Board Management') }}
        </a>

        <span class="count">{{ number_format($boards->total()) }}</span>
    </h1>

    <a href="{{ route('sitemanager.boards.create') }}" class="btn-default">
        <i class="bi bi-plus-circle"></i> {{ t('Add New Board') }}
    </a>
</div>


<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th class="right">{{ t('ID') }}</th>
                <th>{{ t('Board Name') }}</th>
                <th>{{ t('Slug') }}</th>
                <th>{{ t('Skin') }}</th>
                <th>{{ t('Connected Menu') }}</th>
                <th class="text-center">{{ t('Posts') }}</th>
                <th class="text-center">{{ t('Comments') }}</th>
                <th class="text-center">{{ t('Files') }}</th>
                <th>{{ t('Status') }}</th>
                <th>{{ t('Created Date') }}</th>
                <th class="text-end">{{ t('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($boards as $board)
            <tr>
                <td class="number right">{{ $board->id }}</td>
                <td>
                    <a href="{{ route('sitemanager.boards.show', $board) }}">
                        {{ $board->name }}
                    </a>
                </td>
                <td>
                    <code>{{ $board->slug }}</code>
                </td>
                <td>
                    {{ $board->skin ?? 'default' }}
                </td>
                <td>
                    @if($board->menu)
                        <span class="text-info">{{ $board->menu->title }}</span>
                    @else
                        -
                    @endif
                </td>
                <td class="text-center number">
                    @php
                        $postsCount = $board->posts_count ?? $board->getPostsCount();
                        $deletedPostsCount = $board->deleted_posts_count ?? $board->getDeletedPostsCount();
                    @endphp
                    
                    @if($postsCount == 0 && $deletedPostsCount == 0)
                        -
                    @else
                        {{ $postsCount }}
                        @if($deletedPostsCount > 0)
                            <span class="text-muted"> / {{ $deletedPostsCount }}</span>
                        @endif
                    @endif
                </td>
                <td class="text-center number comments">
                    @php
                        $commentsCount = $board->comments_count ?? $board->getCommentsCount();
                        $deletedCommentsCount = $board->deleted_comments_count ?? $board->getDeletedCommentsCount();
                        $pendingCommentsCount = $board->pending_comments_count ?? $board->getPendingCommentsCount();
                    @endphp
                    
                    @if($commentsCount + $pendingCommentsCount + $deletedCommentsCount == 0)
                        -
                    @else
                        @if($commentsCount > 0)
                        <a href="{{ route('sitemanager.comments.index', ['board_id' => $board->id]) }}" class="btn btn-sm btn-outline-success" title="{{ t('Manage comments') }}">
                            {{ $commentsCount }}
                        </a>
                        @endif
                        @if($pendingCommentsCount > 0)
                        <a href="{{ route('sitemanager.comments.index', ['board_id' => $board->id, 'status' => 'pending']) }}" 
                            class="btn btn-sm btn-outline-warning" title="{{ t('Manage pending comments') }}">
                            {{ $pendingCommentsCount }}
                        </a>
                        @endif
                        @if($deletedCommentsCount > 0)
                            <a href="{{ route('sitemanager.comments.index', ['board_id' => $board->id, 'status' =>'deleted']) }}" class="btn btn-sm btn-outline-danger" title="{{ t('Manage comments') }}">{{ $deletedCommentsCount }}</a></span>
                        @endif
                    @endif
                </td>
                <td class="text-center number">
                    @php
                        $attachmentsCount = $board->attachments_count ?? $board->getAttachmentsCount();
                    @endphp
                    
                    @if($attachmentsCount == 0)
                        -
                    @else
                        {{ $attachmentsCount }}
                    @endif
                </td>
                <td>
                    @if($board->status === 'active')
                        <span class="badge bg-success">{{ t('Active') }}</span>
                    @else
                        <span class="badge bg-secondary">{{ t('Inactive') }}</span>
                    @endif
                </td>
                <td class="number">{{ $board->created_at->format('Y-m-d') }}</td>
                <td class="text-end actions">
                    <a href="{{ route('sitemanager.boards.edit', $board) }}" class="btn btn-sm btn-outline-primary" title="{{ t('Edit') }}">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <form method="POST" action="{{ route('sitemanager.boards.toggle-status', $board) }}" class="d-inline">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-sm btn-outline-{{ $board->status === 'active' ? 'warning' : 'success' }}" 
                                title="{{ $board->status === 'active' ? t('Deactivate') : t('Activate') }}"
                                onclick="return confirm('{{ t('Change status?') }}')">
                            <i class="bi bi-{{ $board->status === 'active' ? 'pause' : 'play' }}"></i>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('sitemanager.boards.destroy', $board) }}" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ t('Delete') }}"
                                onclick="return confirm('{{ t('Delete this board?') }}\\n\\n⚠️ {{ t('Warning: All posts and comments will be deleted!') }}')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="11" class="text-center py-5">
                    <i class="bi bi-list-ul display-1 text-muted mb-3"></i>
                    <h5 class="text-muted">{{ t('No boards found') }}</h5>
                    <p class="text-muted">{{ t('Please create a new board.') }}</p>
                    <a href="{{ route('sitemanager.boards.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> {{ t('Create Board') }}
                    </a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($boards->hasPages())
    {{ $boards->links('sitemanager::pagination.default') }}
@endif

@endsection

