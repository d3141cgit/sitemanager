<div class="comment-item mb-3" data-comment-id="{{ $comment->id }}" style="margin-left: {{ ($level ?? 0) * 30 }}px;">
    <div class="d-flex justify-content-between align-items-start mb-2">
        <div class="d-flex align-items-center">
            @if(($level ?? 0) > 0)
                <i class="bi bi-arrow-return-right me-2 text-muted"></i>
            @endif
            <div class="d-flex align-items-center">
                @if($comment->author_profile_photo)
                    <img src="{{ $comment->author_profile_photo }}" 
                         alt="{{ $comment->author }}" 
                         class="profile-photo me-2"
                         style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
                @endif
                <strong class="me-2">{{ $comment->author_name }}</strong>
            </div>
            <small class="text-muted">
                {{ $comment->created_at->diffForHumans() }}
                @if($comment->is_edited)
                    <span class="text-muted">(edited)</span>
                @endif
            </small>
            @if($comment->status === 'pending')
                <span class="badge bg-warning text-dark ms-2">Pending Approval</span>
            @endif
        </div>
        
        <!-- Comment Actions -->
        <div class="dropdown">
            @if($comment->permissions['canEdit'] || $comment->permissions['canDelete'])
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                        type="button" data-bs-toggle="dropdown">
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
                                <i class="bi bi-reply"></i> <span class="reply-text">Reply</span>
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
    
    <!-- Comment Content -->
    <div class="comment-content mb-2">{!! nl2br($comment->content) !!}</div>
    
    <!-- Reply Form (Hidden by default) -->
    <div id="reply-form-{{ $comment->id }}" class="reply-form mt-3" style="display: none;">
        <form onsubmit="submitReply(event, {{ $comment->id }})">
            @csrf
            <input type="hidden" name="post_id" value="{{ $comment->post_id }}">
            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
            <div class="mb-2">
                <textarea name="content" class="form-control form-control-sm" 
                          rows="2" placeholder="Write a reply..." required></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-send"></i> Reply
                </button>
                <button type="button" class="btn btn-sm btn-secondary" 
                        onclick="cancelReply({{ $comment->id }})">
                    Cancel
                </button>
            </div>
        </form>
    </div>
    
    <!-- Edit Form (Hidden by default) -->
    <div id="edit-form-{{ $comment->id }}" class="edit-form mt-3" style="display: none;">
        <form onsubmit="submitEdit(event, {{ $comment->id }})">
            @csrf
            @method('PUT')
            <div class="mb-2">
                <textarea name="content" class="form-control form-control-sm" 
                          rows="3" required>{{ $comment->content }}</textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-check"></i> Save
                </button>
                <button type="button" class="btn btn-sm btn-secondary" 
                        onclick="cancelEdit({{ $comment->id }})">
                    Cancel
                </button>
            </div>
        </form>
    </div>
    
    <!-- Child Comments -->
    @if($comment->children && $comment->children->count() > 0 && $level < 3)
        <div class="child-comments mt-3">
            @foreach($comment->children->sortByDesc('created_at') as $child)
                @include('sitemanager::board.partials.comment', ['comment' => $child, 'level' => $level + 1])
            @endforeach
        </div>
    @endif
</div>
