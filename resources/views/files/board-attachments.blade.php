@extends('sitemanager::layouts.sitemanager')

@section('title', 'Board Attachments Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-paperclip me-2"></i>Board Attachments Management
                    </h5>
                </div>

                <div class="card-body">
                    <!-- 필터 -->
                    <form method="GET" class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="board_slug" class="form-label">Board</label>
                            <select name="board_slug" id="board_slug" class="form-select">
                                <option value="">All Boards</option>
                                @foreach($boards as $board)
                                    <option value="{{ $board->slug }}" 
                                        {{ request('board_slug') === $board->slug ? 'selected' : '' }}>
                                        {{ $board->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" name="search" id="search" class="form-control" 
                                   placeholder="Search by filename..." value="{{ request('search') }}">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- 첨부파일 목록 -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">Type</th>
                                    <th>Filename</th>
                                    <th>Board</th>
                                    <th>Post</th>
                                    <th>Size</th>
                                    <th>Upload Date</th>
                                    <th width="200">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($attachments as $attachment)
                                    <tr>
                                        <td>
                                            @php
                                                $extension = strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));
                                                $iconClass = match($extension) {
                                                    'pdf' => 'bi bi-file-earmark-pdf text-danger',
                                                    'doc', 'docx' => 'bi bi-file-earmark-word text-primary',
                                                    'xls', 'xlsx' => 'bi bi-file-earmark-excel text-success',
                                                    'ppt', 'pptx' => 'bi bi-file-earmark-ppt text-warning',
                                                    'zip', 'rar', '7z' => 'bi bi-file-earmark-zip text-info',
                                                    'jpg', 'jpeg', 'png', 'gif', 'webp' => 'bi bi-file-earmark-image text-success',
                                                    'txt' => 'bi bi-file-earmark-text text-secondary',
                                                    'mp4', 'avi', 'mov' => 'bi bi-file-earmark-play text-primary',
                                                    'mp3', 'wav' => 'bi bi-file-earmark-music text-info',
                                                    default => 'bi bi-file-earmark text-secondary'
                                                };
                                            @endphp
                                            <i class="{{ $iconClass }}"></i>
                                        </td>
                                        <td>
                                            <div class="fw-bold">{{ $attachment->original_name }}</div>
                                            <small class="text-muted">{{ $attachment->filename }}</small>
                                        </td>
                                        <td>
                                            @if($attachment->board)
                                                <span class="badge bg-info">{{ $attachment->board->name }}</span>
                                            @else
                                                <span class="badge bg-secondary">Unknown</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($attachment->post)
                                                <a href="{{ route('board.show', [$attachment->board->slug, $attachment->post->slug ?: $attachment->post->id]) }}" 
                                                   target="_blank" class="text-decoration-none">
                                                    {{ Str::limit($attachment->post->title, 30) }}
                                                    <i class="bi bi-box-arrow-up-right" style="font-size: 0.75rem;"></i>
                                                </a>
                                            @else
                                                <span class="text-muted">Unknown Post</span>
                                            @endif
                                        </td>
                                        <td>{{ $attachment->human_size }}</td>
                                        <td>{{ $attachment->created_at->format('Y-m-d H:i') }}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <!-- 파일 다운로드 -->
                                                <a href="{{ $attachment->download_url }}" class="btn btn-outline-primary" 
                                                   download="{{ $attachment->original_name }}">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                                
                                                <!-- 파일 교체 -->
                                                <button type="button" class="btn btn-outline-warning" 
                                                        onclick="showReplaceModal({{ $attachment->id }}, '{{ $attachment->original_name }}')">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                                
                                                <!-- 파일 삭제 -->
                                                <form method="POST" action="{{ route('sitemanager.files.board-attachments.delete', $attachment) }}" 
                                                      style="display: inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger" 
                                                            onclick="return confirm('Are you sure you want to delete this attachment?')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="bi bi-paperclip" style="font-size: 3rem; color: #6c757d;"></i>
                                            <p class="text-muted mt-3">No board attachments found.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- 페이지네이션 -->
                    <div class="d-flex justify-content-center">
                        {{ $attachments->withQueryString()->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 파일 교체 모달 -->
<div class="modal fade" id="replaceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Replace Attachment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="replaceForm" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <p>Replace file: <strong id="replaceFileName"></strong></p>
                    <div class="mb-3">
                        <label for="replacement_file" class="form-label">Choose new file</label>
                        <input type="file" name="replacement_file" id="replacement_file" 
                               class="form-control" required>
                        <div class="form-text">Max file size: 50MB</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Replace File</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function showReplaceModal(attachmentId, fileName) {
    document.getElementById('replaceFileName').textContent = fileName;
    document.getElementById('replaceForm').action = `/sitemanager/files/board-attachments/${attachmentId}/replace`;
    new bootstrap.Modal(document.getElementById('replaceModal')).show();
}
</script>
@endpush
