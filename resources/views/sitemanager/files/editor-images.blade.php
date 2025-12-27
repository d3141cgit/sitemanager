@extends('sitemanager::layouts.sitemanager')

@section('title', t('Editor Images Management'))

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
        <a href="{{ route('sitemanager.files.editor-images') }}">
            <i class="bi bi-images opacity-75"></i> {{ t('Editor Images Management') }}
        </a>

        <span class="count">{{ number_format($images->total()) }}</span>
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

    <select name="used_status" id="used_status" class="form-select">
        <option value="">{{ t('All Status') }}</option>
        <option value="used" {{ request('used_status') === 'used' ? 'selected' : '' }}>{{ t('Used') }}</option>
        <option value="unused" {{ request('used_status') === 'unused' ? 'selected' : '' }}>{{ t('Unused') }}</option>
    </select>

    <input type="text" name="search" id="search" class="form-control" 
            placeholder="{{ t('Search by filename...') }}" value="{{ request('search') }}">

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-search"></i> {{ t('Filter') }}
    </button>
</form>

<!-- 이미지 목록 -->
<div class="table-responsive">
    <table class="table table-striped table-hover table-bordered">
        <thead>
            <tr>
                <th width="80">{{ t('Preview') }}</th>
                <th>{{ t('Filename') }}</th>
                <th>{{ t('Board') }}</th>
                <th class="text-center">{{ t('Size') }}</th>
                <th class="text-center">{{ t('Used') }}</th>
                <th class="text-center">{{ t('Upload Date') }}</th>
                <th class="text-center">{{ t('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($images as $image)
                <tr>
                    <td>
                        <img src="{{ $image->url }}" alt="{{ $image->filename }}" 
                                class="img-thumbnail cursor-pointer" style="max-width: 60px; max-height: 60px;"
                                onclick="showImagePreview('{{ $image->url }}', '{{ addslashes($image->original_name) }}')"
                                title="{{ t('Click to view larger') }}">
                    </td>
                    <td>
                        <div class="fw-bold">
                            <a href="{{ $image->url }}" download="{{ $image->original_name }}" 
                                class="text-decoration-none" title="{{ t('Click to download') }}">
                                {{ $image->original_name }}
                            </a>
                        </div>
                        <small class="text-muted">{{ $image->filename }}</small>
                    </td>
                    <td>
                        @if($image->reference_slug)
                            <span class="badge bg-info me-1">{{ $image->reference_slug }}</span>
                            @if($image->reference_id && $image->reference_id > 0)
                                @php
                                    $postModelClass = \SiteManager\Models\BoardPost::forBoard($image->reference_slug);
                                    $post = $postModelClass::find($image->reference_id);
                                @endphp
                                @if($post)
                                    <a href="javascript:void(0)" onclick="openPostInNewWindow('{{ $image->reference_slug }}', {{ $image->reference_id }})" class="text-decoration-none small" title="{{ t('View post') }}">
                                        {{ $post->title ?? t('No Title') }}
                                    </a>
                                @endif
                            @elseif($image->reference_id && $image->reference_id < 0)
                                <div class="mt-1">
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-exclamation-triangle"></i> {{ t('Temp ID') }}: {{ $image->reference_id }}
                                    </span>
                                    <br><small class="text-muted">{{ t('Reference needs update') }}</small>
                                </div>
                            @endif
                        @else
                            <span class="badge bg-secondary">{{ t('No Board') }}</span>
                        @endif
                    </td>
                    <td nowrap class="number text-center">{{ $image->human_size }}</td>
                    <td class="text-center">
                        <div class="d-flex align-items-center justify-content-center">
                            <span class="badge {{ $image->is_used ? 'bg-success' : 'bg-warning' }} me-2">
                                {{ $image->is_used ? t('Used') : t('Unused') }}
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-info" 
                                    onclick="checkActualUsage({{ $image->id }}, '{{ addslashes($image->filename) }}', {{ $image->is_used ? 'true' : 'false' }})" 
                                    title="{{ t('Check actual usage status') }}">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </td>
                    <td class="text-nowrap number text-center">{{ $image->created_at->format('Y-m-d H:i') }}</td>
                    <td class="text-center actions">
                        <!-- 이미지 교체 -->
                        <button type="button" class="btn btn-sm btn-outline-warning" 
                                onclick="showReplaceModal({{ $image->id }}, '{{ $image->original_name }}')">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                        
                        <!-- 이미지 삭제 -->
                        @if(!$image->is_used)
                            <form method="POST" action="{{ route('sitemanager.files.editor-images.delete', $image) }}" 
                                    style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" 
                                        onclick="return confirm('{{ t('Are you sure you want to delete this image?') }}')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="bi bi-images" style="font-size: 3rem; color: #6c757d;"></i>
                        <p class="text-muted mt-3">{{ t('No editor images found.') }}</p>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- 페이지네이션 -->
@if($images->hasPages())
    {{ $images->links('sitemanager::pagination.default') }}
@endif

<!-- 이미지 교체 모달 -->
<div class="modal fade" id="replaceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ t('Replace Image') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="replaceForm" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <p>{{ t('Replace image') }}: <strong id="replaceImageName"></strong></p>
                    <div class="mb-3">
                        <label for="replacement_file" class="form-label">{{ t('Choose new image') }}</label>
                        <input type="file" name="replacement_file" id="replacement_file" 
                               class="form-control" accept="image/*" required>
                        <div class="form-text">{{ t('Max file size: 5MB. Allowed formats: JPG, PNG, GIF, WebP') }}</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ t('Cancel') }}</button>
                    <button type="submit" class="btn btn-warning">{{ t('Replace Image') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 사용 여부 체크 모달 -->
<div class="modal fade" id="usageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ t('Image Usage Check') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="usageModalBody">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">{{ t('Loading...') }}</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div id="updateButtons" style="display: none;">
                    <button type="button" class="btn btn-success me-2" onclick="updateUsageStatus(currentImageId, true)">
                        <i class="bi bi-check-circle"></i> {{ t('Mark as Used') }}
                    </button>
                    <button type="button" class="btn btn-danger me-2" onclick="updateUsageStatus(currentImageId, false)">
                        <i class="bi bi-x-circle"></i> {{ t('Mark as Unused') }}
                    </button>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ t('Close') }}</button>
            </div>
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
// 번역 변수들
const translations = {
    used: '{{ t('Used') }}',
    notUsed: '{{ t('Not Used') }}',
    statusMismatch: '{{ t('Status Mismatch!') }}',
    databaseShows: '{{ t('Database shows') }}',
    actualContent: '{{ t('Actual content') }}',
    image: '{{ t('Image') }}',
    actualUsageStatus: '{{ t('Actual Usage Status') }}',
    foundInPosts: '{{ t('Found in posts') }}',
    dbStatus: '{{ t('DB Status') }}',
    updateNeeded: '{{ t('Update needed?') }}',
    errorCheckingUsage: '{{ t('Error checking usage') }}',
    imageNotFound: '{{ t('Image not found') }}',
    usageStatusUpdated: '{{ t('Usage status updated successfully') }}',
    errorUpdatingStatus: '{{ t('Error updating usage status') }}',
    confirm: '{{ t('Are you sure you want to delete this image?') }}',
    checkingUsage: '{{ t('Checking usage...') }}',
    checkingActualUsage: '{{ t('Checking actual usage in content...') }}',
    databaseStatus: '{{ t('Database Status') }}',
    actualUsage: '{{ t('Actual Usage') }}',
    foundInBoards: '{{ t('Found in Boards') }}',
    posts: '{{ t('posts') }}',
    updated: '{{ t('Updated!') }}',
    error: '{{ t('Error!') }}',
    failedToUpdate: '{{ t('Failed to update usage status') }}',
    updateError: '{{ t('An error occurred while updating usage status') }}'
};

function showReplaceModal(imageId, imageName) {
    document.getElementById('replaceImageName').textContent = imageName;
    document.getElementById('replaceForm').action = `/sitemanager/files/editor-images/${imageId}/replace`;
    new bootstrap.Modal(document.getElementById('replaceModal')).show();
}

function checkActualUsage(imageId, filename, isUsed) {
    currentImageId = imageId;
    
    const modal = new bootstrap.Modal(document.getElementById('usageModal'));
    const modalBody = document.getElementById('usageModalBody');
    const updateButtons = document.getElementById('updateButtons');
    
    // 로딩 상태로 초기화
    modalBody.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">${translations.checkingUsage}</span>
            </div>
            <p class="mt-2">${translations.checkingActualUsage}</p>
        </div>
    `;
    
    // 버튼 숨기기
    updateButtons.style.display = 'none';
    
    modal.show();
    
    fetch(`/sitemanager/files/editor-images/${imageId}/check-usage`)
        .then(response => response.json())
        .then(data => {
            let statusIcon, statusText, statusClass;
            
            if (data.found) {
                statusIcon = '<i class="bi bi-check-circle-fill text-success"></i>';
                statusText = translations.used;
                statusClass = 'success';
            } else {
                statusIcon = '<i class="bi bi-x-circle-fill text-danger"></i>';
                statusText = translations.notUsed;
                statusClass = 'danger';
            }
            
            // 불일치 체크
            const mismatch = (data.found && !isUsed) || (!data.found && isUsed);
            const mismatchAlert = mismatch ? `
                <div class="alert alert-warning mt-3">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>${translations.statusMismatch}</strong><br>
                    ${translations.databaseShows}: <strong>${isUsed ? translations.used : translations.notUsed}</strong><br>
                    ${translations.actualContent}: <strong>${data.found ? translations.used : translations.notUsed}</strong>
                </div>
            ` : '';
            
            modalBody.innerHTML = `
                <div class="text-center mb-3">
                    <h5>${translations.image}: ${filename}</h5>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-database"></i> ${translations.databaseStatus}</h6>
                            </div>
                            <div class="card-body text-center">
                                <span class="badge bg-${isUsed ? 'success' : 'danger'}">${isUsed ? translations.used : translations.notUsed}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-search"></i> ${translations.actualUsage}</h6>
                            </div>
                            <div class="card-body text-center">
                                ${statusIcon}
                                <span class="badge bg-${statusClass} ms-2">${statusText}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                ${mismatchAlert}
                
                ${data.boards && data.boards.length > 0 ? `
                    <div class="mt-3">
                        <h6><i class="bi bi-list-ul"></i> ${translations.foundInBoards}:</h6>
                        ${data.boards.map(board => `
                            <div class="card mb-2">
                                <div class="card-header py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong>${board.title}</strong>
                                        <span class="badge bg-primary rounded-pill">${board.posts_count} ${translations.posts}</span>
                                    </div>
                                </div>
                                ${board.posts && board.posts.length > 0 ? `
                                    <div class="card-body py-2">
                                        <div class="list-group list-group-flush">
                                            ${board.posts.map(post => `
                                                <div class="list-group-item px-0 py-1 d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <a href="javascript:void(0)" 
                                                           onclick="openPostInNewWindow('${board.slug}', ${post.id})"
                                                           class="text-decoration-none fw-medium">
                                                            ${post.title}
                                                        </a>
                                                        <small class="text-muted d-block">${post.created_at}</small>
                                                    </div>
                                                    <i class="bi bi-box-arrow-up-right text-muted"></i>
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        `).join('')}
                    </div>
                ` : ''}
            `;
            
            // 불일치가 있을 때만 업데이트 버튼 표시
            if (mismatch) {
                updateButtons.style.display = 'block';
            }
        })
        .catch(error => {
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    ${translations.errorCheckingUsage}: ${error.message}
                </div>
            `;
        });
}

function updateUsageStatus(imageId, isUsed) {
    fetch(`/sitemanager/files/editor-images/${imageId}/update-usage`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            is_used: isUsed
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 모달 닫기
            bootstrap.Modal.getInstance(document.getElementById('usageModal')).hide();
            
            // 성공 메시지 표시 (reference 업데이트 여부에 따라 메시지 다르게)
            let message = data.message;
            if (data.reference_updated) {
                message += '\nReference information has also been updated to match the actual usage.';
            }
            
            Swal.fire({
                icon: 'success',
                title: translations.updated,
                text: message,
                timer: 3000,
                showConfirmButton: false
            });
            
            // 페이지 새로고침하여 변경된 상태 반영
            setTimeout(() => {
                location.reload();
            }, 2500);
        } else {
            Swal.fire({
                icon: 'error',
                title: translations.error,
                text: translations.failedToUpdate
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: translations.error,
            text: translations.updateError
        });
    });
}

function showImagePreview(imageUrl, imageName) {
    const modal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
    const img = document.getElementById('imagePreviewImg');
    const title = document.getElementById('imagePreviewTitle');
    const downloadLink = document.getElementById('imageDownloadLink');
    
    img.src = imageUrl;
    img.alt = imageName;
    title.textContent = imageName;
    downloadLink.href = imageUrl;
    downloadLink.download = imageName;
    
    modal.show();
}

function openPostInNewWindow(boardSlug, postId) {
    // SiteManager의 게시물 보기 URL 구조에 맞춰 조정하고 이미지 하이라이트 파라미터 추가
    const url = `/sitemanager/board/${boardSlug}/posts/${postId}?highlight=images`;
    window.open(url, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
}
</script>
@endpush
