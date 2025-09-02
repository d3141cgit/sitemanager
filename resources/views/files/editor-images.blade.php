@extends('sitemanager::layouts.sitemanager')

@section('title', 'Editor Images Management')

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
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-images me-2"></i>Editor Images Management
                    </h5>
                </div>

                <div class="card-body">
                    <!-- 필터 -->
                    <form method="GET" class="row g-3 mb-4">
                        <div class="col-md-3">
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
                        
                        <div class="col-md-3">
                            <label for="used_status" class="form-label">Usage Status</label>
                            <select name="used_status" id="used_status" class="form-select">
                                <option value="">All Status</option>
                                <option value="used" {{ request('used_status') === 'used' ? 'selected' : '' }}>Used</option>
                                <option value="unused" {{ request('used_status') === 'unused' ? 'selected' : '' }}>Unused</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
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

                    <!-- 이미지 목록 -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="80">Preview</th>
                                    <th>Filename</th>
                                    <th>Board</th>
                                    <th>Size</th>
                                    <th>Used</th>
                                    <th>Upload Date</th>
                                    <th width="200">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($images as $image)
                                    <tr>
                                        <td>
                                            <img src="{{ $image->url }}" alt="{{ $image->filename }}" 
                                                 class="img-thumbnail cursor-pointer" style="max-width: 60px; max-height: 60px;"
                                                 onclick="showImagePreview('{{ $image->url }}', '{{ addslashes($image->original_name) }}')"
                                                 title="클릭해서 크게 보기">
                                        </td>
                                        <td>
                                            <div class="fw-bold">
                                                <a href="{{ $image->url }}" download="{{ $image->original_name }}" 
                                                   class="text-decoration-none" title="클릭해서 다운로드">
                                                    {{ $image->original_name }}
                                                </a>
                                            </div>
                                            <small class="text-muted">{{ $image->filename }}</small>
                                        </td>
                                        <td>
                                            @if($image->reference_slug)
                                                <span class="badge bg-info">{{ $image->reference_slug }}</span>
                                                @if($image->reference_id && $image->reference_id > 0)
                                                    @php
                                                        $postModelClass = \SiteManager\Models\BoardPost::forBoard($image->reference_slug);
                                                        $post = $postModelClass::find($image->reference_id);
                                                    @endphp
                                                    @if($post)
                                                        <div class="mt-1">
                                                            <a href="javascript:void(0)" 
                                                               onclick="openPostInNewWindow('{{ $image->reference_slug }}', {{ $image->reference_id }})"
                                                               class="text-decoration-none small"
                                                               title="게시물 보기">
                                                                <i class="bi bi-box-arrow-up-right"></i> {{ Str::limit($post->title ?? 'No Title', 30) }}
                                                            </a>
                                                        </div>
                                                    @endif
                                                @elseif($image->reference_id && $image->reference_id < 0)
                                                    <div class="mt-1">
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="bi bi-exclamation-triangle"></i> Temp ID: {{ $image->reference_id }}
                                                        </span>
                                                        <br><small class="text-muted">Reference needs update</small>
                                                    </div>
                                                @endif
                                            @else
                                                <span class="badge bg-secondary">No Board</span>
                                            @endif
                                        </td>
                                        <td>{{ $image->human_size }}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="badge {{ $image->is_used ? 'bg-success' : 'bg-warning' }} me-2">
                                                    {{ $image->is_used ? 'Used' : 'Unused' }}
                                                </span>
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        onclick="checkActualUsage({{ $image->id }}, '{{ addslashes($image->filename) }}', {{ $image->is_used ? 'true' : 'false' }})" 
                                                        title="실제 사용 상태 확인">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>{{ $image->created_at->format('Y-m-d H:i') }}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <!-- 이미지 교체 -->
                                                <button type="button" class="btn btn-outline-warning" 
                                                        onclick="showReplaceModal({{ $image->id }}, '{{ $image->original_name }}')">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                                
                                                <!-- 이미지 삭제 -->
                                                @if(!$image->is_used)
                                                    <form method="POST" action="{{ route('sitemanager.files.editor-images.delete', $image) }}" 
                                                          style="display: inline;">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger" 
                                                                onclick="return confirm('Are you sure you want to delete this image?')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="bi bi-images" style="font-size: 3rem; color: #6c757d;"></i>
                                            <p class="text-muted mt-3">No editor images found.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- 페이지네이션 -->
                    <div class="d-flex justify-content-center">
                        {{ $images->withQueryString()->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 이미지 교체 모달 -->
<div class="modal fade" id="replaceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Replace Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="replaceForm" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <p>Replace image: <strong id="replaceImageName"></strong></p>
                    <div class="mb-3">
                        <label for="replacement_file" class="form-label">Choose new image</label>
                        <input type="file" name="replacement_file" id="replacement_file" 
                               class="form-control" accept="image/*" required>
                        <div class="form-text">Max file size: 5MB. Allowed formats: JPG, PNG, GIF, WebP</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Replace Image</button>
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
                <h5 class="modal-title">Image Usage Check</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="usageModalBody">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div id="updateButtons" style="display: none;">
                    <button type="button" class="btn btn-success me-2" onclick="updateUsageStatus(currentImageId, true)">
                        <i class="bi bi-check-circle"></i> Mark as Used
                    </button>
                    <button type="button" class="btn btn-danger me-2" onclick="updateUsageStatus(currentImageId, false)">
                        <i class="bi bi-x-circle"></i> Mark as Unused
                    </button>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<!-- Image Preview Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imagePreviewTitle">Image Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="imagePreviewImg" src="" alt="" class="img-fluid" style="max-height: 70vh;">
            </div>
            <div class="modal-footer">
                <a id="imageDownloadLink" href="" download="" class="btn btn-primary">
                    <i class="bi bi-download"></i> Download
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
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
                <span class="visually-hidden">Checking usage...</span>
            </div>
            <p class="mt-2">Checking actual usage in content...</p>
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
                statusText = 'Used';
                statusClass = 'success';
            } else {
                statusIcon = '<i class="bi bi-x-circle-fill text-danger"></i>';
                statusText = 'Not Used';
                statusClass = 'danger';
            }
            
            // 불일치 체크
            const mismatch = (data.found && !isUsed) || (!data.found && isUsed);
            const mismatchAlert = mismatch ? `
                <div class="alert alert-warning mt-3">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>Status Mismatch!</strong><br>
                    Database shows: <strong>${isUsed ? 'Used' : 'Not Used'}</strong><br>
                    Actual content: <strong>${data.found ? 'Used' : 'Not Used'}</strong>
                </div>
            ` : '';
            
            modalBody.innerHTML = `
                <div class="text-center mb-3">
                    <h5>Image: ${filename}</h5>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-database"></i> Database Status</h6>
                            </div>
                            <div class="card-body text-center">
                                <span class="badge bg-${isUsed ? 'success' : 'danger'}">${isUsed ? 'Used' : 'Not Used'}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-search"></i> Actual Usage</h6>
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
                        <h6><i class="bi bi-list-ul"></i> Found in Boards:</h6>
                        ${data.boards.map(board => `
                            <div class="card mb-2">
                                <div class="card-header py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong>${board.title}</strong>
                                        <span class="badge bg-primary rounded-pill">${board.posts_count} posts</span>
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
                    Error checking usage: ${error.message}
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
                title: 'Updated!',
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
                title: 'Error!',
                text: 'Failed to update usage status'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An error occurred while updating usage status'
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
