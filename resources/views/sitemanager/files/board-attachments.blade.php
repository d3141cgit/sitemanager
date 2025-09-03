@extends('sitemanager::layouts.sitemanager')

@section('title', t('Board Attachments Management'))

@push('styles')
<style>
.cursor-pointer {
    cursor: pointer;
}
.cursor-pointer:hover {
    opacity: 0.8;
    transform: scale(1.05);
    transition: all 0.2s ease;
}
</style>
@endpush

@section('content')
<div class="content-header">
    <h1>
        <a href="{{ route('sitemanager.files.board-attachments') }}">
            <i class="bi bi-paperclip opacity-75"></i> {{ t('Board Attachments Management') }}
        </a>

        <span class="count">{{ number_format($attachments->total()) }}</span>
    </h1>
</div>

<form method="GET" class="search-form">
    <select name="board_slug" id="board_slug" class="form-select">
        <option value="">{{ t('All Boards') }}</option>
        @foreach($boards as $board)
            <option value="{{ $board->slug }}" 
                {{ request('board_slug') === $board->slug ? 'selected' : '' }}>
                {{ $board->name }}
            </option>
        @endforeach
    </select>

    <input type="text" name="search" id="search" class="form-control" 
            placeholder="{{ t('Search by filename...') }}" value="{{ request('search') }}">

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-search"></i> {{ t('Filter') }}
    </button>
</form>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th width="80">{{ t('Preview') }}</th>
                <th>{{ t('Filename') }}</th>
                <th>{{ t('Board') }}</th>
                <th>{{ t('Post') }}</th>
                <th>{{ t('Size') }}</th>
                <th>{{ t('Upload Date') }}</th>
                <th class="text-end">{{ t('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attachments as $attachment)
                <tr>
                    <td>
                        @php
                            $extension = strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));
                            $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        @endphp
                        
                        @if($isImage)
                            <img src="{{ $attachment->preview_url ?: $attachment->file_url }}" alt="{{ $attachment->original_name }}" 
                                    class="img-thumbnail cursor-pointer" style="max-width: 60px; max-height: 60px;"
                                    onclick="showImagePreview('{{ $attachment->preview_url ?: $attachment->file_url }}', '{{ addslashes($attachment->original_name) }}', '{{ $attachment->download_url }}')"
                                    title="{{ t('Click to view larger') }}">
                        @else
                            @php
                                $iconClass = match($extension) {
                                    'pdf' => 'bi bi-file-earmark-pdf text-danger',
                                    'doc', 'docx' => 'bi bi-file-earmark-word text-primary',
                                    'xls', 'xlsx' => 'bi bi-file-earmark-excel text-success',
                                    'ppt', 'pptx' => 'bi bi-file-earmark-ppt text-warning',
                                    'zip', 'rar', '7z' => 'bi bi-file-earmark-zip text-info',
                                    'txt' => 'bi bi-file-earmark-text text-secondary',
                                    'mp4', 'avi', 'mov' => 'bi bi-file-earmark-play text-primary',
                                    'mp3', 'wav' => 'bi bi-file-earmark-music text-info',
                                    default => 'bi bi-file-earmark text-secondary'
                                };
                            @endphp
                            <i class="{{ $iconClass }}" style="font-size: 2rem;"></i>
                        @endif
                    </td>
                    <td>
                        <div class="fw-bold">
                            <a href="{{ $attachment->download_url }}" class="text-decoration-none" 
                                title="{{ t('Click to download') }}">
                                {{ $attachment->original_name }}
                            </a>
                        </div>
                        <small class="text-muted">{{ $attachment->filename }}</small>
                    </td>
                    <td>
                        @if($attachment->board)
                            <span class="badge bg-info">{{ $attachment->board->name }}</span>
                        @else
                            <span class="badge bg-secondary">{{ t('Unknown') }}</span>
                        @endif
                    </td>
                    <td>
                        @if($attachment->post && $attachment->board)
                            <a href="{{ route('board.show', [$attachment->board->slug, $attachment->post->slug ?: $attachment->post->id]) }}" 
                                target="_blank" class="text-decoration-none">
                                {{ Str::limit($attachment->post->title, 30) }}
                                <i class="bi bi-box-arrow-up-right" style="font-size: 0.75rem;"></i>
                            </a>
                        @elseif($attachment->post)
                            <span class="text-muted">{{ Str::limit($attachment->post->title, 30) }}</span>
                        @else
                            <span class="text-muted">{{ t('Unknown Post') }}</span>
                        @endif
                    </td>
                    <td class="number">{{ $attachment->human_size }}</td>
                    <td class="number">{{ $attachment->created_at->format('Y-m-d H:i') }}</td>
                    <td class="text-end actions">
                        <!-- 파일 다운로드 -->
                        <a href="{{ $attachment->download_url }}" class="btn btn-sm btn-outline-primary" 
                            download="{{ $attachment->original_name }}">
                            <i class="bi bi-download"></i>
                        </a>
                        
                        <!-- 파일 교체 -->
                        <button type="button" class="btn btn-sm btn-outline-warning" 
                                onclick="showReplaceModal({{ $attachment->id }}, '{{ $attachment->original_name }}')">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                        
                        <!-- 파일 삭제 -->
                        <form method="POST" action="{{ route('sitemanager.files.board-attachments.delete', $attachment) }}" 
                                style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                    onclick="return confirm('{{ t('Are you sure you want to delete this attachment?') }}')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="bi bi-paperclip" style="font-size: 3rem; color: #6c757d;"></i>
                        <p class="text-muted mt-3">{{ t('No board attachments found.') }}</p>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $attachments->withQueryString()->links('sitemanager::pagination.default') }}

<!-- 파일 교체 모달 -->
<div class="modal fade" id="replaceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ t('Replace Attachment') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="replaceForm" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <p>{{ t('Replace file') }}: <strong id="replaceFileName"></strong></p>
                    <div class="mb-3">
                        <label for="replacement_file" class="form-label">{{ t('Choose new file') }}</label>
                        <input type="file" name="replacement_file" id="replacement_file" 
                               class="form-control" required>
                        <div class="form-text">{{ t('Max file size: 50MB') }}</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ t('Cancel') }}</button>
                    <button type="submit" class="btn btn-warning">{{ t('Replace File') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Image Preview Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imagePreviewTitle">{{ t('Image Preview') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="imagePreviewImg" src="" alt="" class="img-fluid" style="max-height: 70vh;">
            </div>
            <div class="modal-footer">
                <a id="imageDownloadLink" href="" download="" class="btn btn-primary">
                    <i class="bi bi-download"></i> {{ t('Download') }}
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ t('Close') }}</button>
            </div>
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

function showImagePreview(imageUrl, imageName, downloadUrl) {
    const modal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
    const img = document.getElementById('imagePreviewImg');
    const title = document.getElementById('imagePreviewTitle');
    const downloadLink = document.getElementById('imageDownloadLink');
    
    img.src = imageUrl;
    img.alt = imageName;
    title.textContent = imageName;
    downloadLink.href = downloadUrl || imageUrl; // downloadUrl이 있으면 사용, 없으면 imageUrl 사용
    downloadLink.download = imageName;
    
    modal.show();
}
</script>
@endpush
