<div id="reply-form-{{ $comment->id }}" class="reply-form mt-3">
    <form onsubmit="submitReply(event, {{ $comment->id }})" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="post_id" value="{{ $comment->post_id }}">
        <input type="hidden" name="parent_id" value="{{ $comment->id }}">

        {{-- 비회원 작성자 정보 폼 --}}
        @guest
            @include('sitemanager::board.partials.guest-author-form')
        @endguest

        <textarea name="content" class="form-control form-control-sm" 
                      rows="2" placeholder="Write a reply..." required></textarea>

        @if($comment->canUploadFiles())
            <div>
                <input type="file" name="files[]" id="file-reply-{{ $comment->id }}" class="d-none" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('file-reply-{{ $comment->id }}').click()">
                    <i class="bi bi-paperclip"></i> Add Files
                </button>
                <div class="comment-file-preview"></div>
            </div>
        @endif

        <div class="d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-sm btn-success">Reply</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="cancelReply({{ $comment->id }})">Cancel</button>
        </div>
    </form>
</div>
