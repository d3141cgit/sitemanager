<div @class(['comment-item', 'pending' => $comment->status === 'pending']) data-comment-id="{{ $comment->id }}" style="margin-left: {{ ($level ?? 0) * 30 }}px;">

    <div class="comment-header">
        <div class="d-flex align-items-center">
            @if(($level ?? 0) > 0)
                <i class="bi bi-arrow-return-right me-2 text-muted"></i>
            @endif
            <span class="comment-author">
                @if($comment->author_profile_photo)
                    <img src="{{ $comment->author_profile_photo }}" 
                         alt="{{ $comment->author }}" 
                         class="profile-photo me-2"
                         style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
                @endif

                {{ $comment->author_name }}
            </span>
            <span class="comment-date">
                {{ $comment->created_at->diffForHumans() }}
                @if($comment->is_edited)
                    (edited)
                @endif
            </span>
        </div>
        
        {{-- Comment Actions --}}
        <div class="dropdown">
            @if($comment->status === 'pending')
                <span class="comment-pending">Pending Approval</span>
            @endif

            @if($comment->permissions['canEdit'] || $comment->permissions['canDelete'])
                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end">
                    @if($comment->permissions['canEdit'])
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="editComment({{ $comment->id }})">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                        </li>
                    @endif
                    
                    @if($comment->permissions['canReply'] && $comment->status !== 'pending')
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="event.preventDefault(); showReplyLoading({{ $comment->id }}); replyToComment({{ $comment->id }})">
                                <i class="bi bi-reply"></i> Reply
                            </a>
                        </li>
                    @endif
                    
                    @if($comment->permissions['canManage'] && $comment->status === 'pending')
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="approveComment({{ $comment->id }})">
                                <i class="bi bi-check-circle text-success"></i> Approve
                            </a>
                        </li>
                    @endif
                    
                    @if($comment->permissions['canDelete'])
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="javascript:void(0)" 
                               onclick="deleteComment({{ $comment->id }})">
                                <i class="bi bi-trash"></i> Delete
                            </a>
                        </li>
                    @endif
                </ul>
            @endif
        </div>
    </div>
    
    <div class="comment-content">{!! nl2br($comment->content) !!}</div>
    
    {{-- Comment Attachments --}}
    @if($comment->attachments && $comment->attachments->count() > 0)
        <div class="comment-attachments">
            @foreach($comment->attachments as $attachment)
                @if($attachment->is_image)
                    <img src="{{ $attachment->preview_url }}" 
                        alt="{{ $attachment->original_name }}" 
                        onclick="showImageModal('{{ $attachment->file_url }}', '{{ $attachment->original_name }}', '{{ $attachment->download_url }}')">
                @else
                    <a href="{{ $attachment->download_url }}" 
                        class="btn btn-sm btn-light" 
                        title="{{ $attachment->original_name }} ({{ $attachment->file_size_human }})">
                        <i class="{{ $attachment->file_icon }}"></i>
                        {{ Str::limit($attachment->original_name, 20) }}
                        <small class="text-muted">({{ $attachment->file_size_human }})</small>
                    </a>
                @endif
            @endforeach
        </div>
    @endif
    
    {{-- Reply Form --}}
    <div id="reply-form-{{ $comment->id }}" class="reply-form" style="display: none;">
        <form onsubmit="submitReply(event, {{ $comment->id }})" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="post_id" value="{{ $comment->post_id }}">
            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
            
            <textarea name="content" class="form-control form-control-sm" rows="2" placeholder="Write a reply..." required></textarea>

            @if($comment->permissions['canFileUpload'] ?? false)
                <div>
                    <input type="file" name="files[]" id="file-reply-{{ $comment->id }}" class="d-none" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                    <button type="button" class="btn btn-sm btn-light" onclick="document.getElementById('file-reply-{{ $comment->id }}').click()">
                        <i class="bi bi-paperclip"></i> Add Files
                    </button>
                    <div class="comment-file-preview"></div>
                </div>
            @endif

            <div class="d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-sm btn-success">
                    <i class="bi bi-send"></i> Reply
                </button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="cancelReply({{ $comment->id }})">
                    Cancel
                </button>
            </div>
        </form>
    </div>
    
    {{-- Edit Form --}}
    <div id="edit-form-{{ $comment->id }}" class="edit-form" style="display: none;">
        <form onsubmit="submitEdit(event, {{ $comment->id }})" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            
            <textarea name="content" class="form-control form-control-sm" rows="3" required>{{ $comment->content }}</textarea>
            
            {{-- 기존 첨부파일 표시 --}}
            @if($comment->attachments && $comment->attachments->count() > 0)
                <div class="existing-attachments">
                    @include('sitemanager::board.partials.comment-attachments', ['comment' => $comment])
                </div>
            @endif
            
            {{-- 새 파일 업로드 (파일 업로드 권한이 있는 경우만) --}}
            @if($comment->permissions['canFileUpload'] ?? false)
                <div>
                    <input type="file" name="files[]" id="file-edit-{{ $comment->id }}" class="d-none" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('file-edit-{{ $comment->id }}').click()">
                        <i class="bi bi-paperclip"></i> Add Files
                    </button>
                    <div class="comment-file-preview"></div>
                </div>
            @endif
            
            <div class="d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-check"></i> Save
                </button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="cancelEdit({{ $comment->id }})">
                    Cancel
                </button>
            </div>
        </form>
    </div>
    
    {{-- Child Comments --}}
    @if($comment->children && $comment->children->count() > 0 && $level < 3)
        <div class="child-comments">
            @foreach($comment->children->sortByDesc('created_at') as $child)
                @include('sitemanager::board.partials.comment', ['comment' => $child, 'level' => $level + 1])
            @endforeach
        </div>
    @endif
</div>
