<div id="edit-form-{{ $comment->id }}" class="edit-form mt-2">
    <form onsubmit="submitEdit(event, {{ $comment->id }})" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <input type="hidden" name="post_id" value="{{ $comment->post_id }}">
        
        <textarea name="content" class="form-control form-control-sm" rows="3" required>{{ $comment->content }}</textarea>
        
        <!-- 기존 첨부파일 표시 -->
        @if($comment->attachments && $comment->attachments->count() > 0)
            <div class="existing-attachments">
                @include('sitemanager::board.partials.comment-attachments', ['comment' => $comment])
            </div>
        @endif
        
        <!-- 새 파일 업로드 (파일 업로드 권한이 있는 경우만) -->
        @if($comment->canUploadFiles())
            <div>
                <input type="file" name="files[]" id="file-input-{{ $comment->id }}" class="d-none" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('file-input-{{ $comment->id }}').click()">
                    <i class="bi bi-paperclip"></i> Add Files
                </button>
                <div class="comment-file-preview"></div>
            </div>
        @endif
        
        <div class="d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-sm btn-dark">
                <i class="bi bi-check"></i> Save
            </button>
            <button type="button" class="btn btn-sm btn-secondary" 
                    onclick="cancelEdit({{ $comment->id }})">
                Cancel
            </button>
        </div>
    </form>
</div>
