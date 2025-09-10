<div @class(['comment-item', 'pending' => $comment->status === 'pending']) data-comment-id="{{ $comment->id }}" style="margin-left: {{ $level ? 30 : 0 }}px;">

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

            @if(isset($comment->permissions))
                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end">
                    @if(isset($comment->permissions['canEdit']) && $comment->permissions['canEdit'])
                        <li>
                            @if(!Auth::check() && $comment->email_verified_at)
                                {{-- 비회원 댓글 수정 (이메일 인증 완료) --}}
                                <a class="dropdown-item" href="javascript:void(0)" onclick="requestEmailVerification({{ $comment->id }}, 'edit')">
                                    <i class="bi bi-pencil"></i> Edit
                                    <small class="text-muted d-block">Email/Password required</small>
                                </a>
                            @elseif(Auth::check())
                                {{-- 회원 댓글 수정 --}}
                                <a class="dropdown-item" href="javascript:void(0)" onclick="editComment({{ $comment->id }})">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                            @endif
                        </li>
                    @endif
                    
                    @if(isset($comment->permissions['canReply']) && $comment->permissions['canReply'] && $comment->status !== 'pending')
                        <li>
                            @if(!Auth::check())
                                {{-- 비회원 답글 (이메일 인증 필요) --}}
                                <a class="dropdown-item" href="javascript:void(0)" onclick="event.preventDefault(); showReplyLoading({{ $comment->id }}); replyToComment({{ $comment->id }})">
                                    <i class="bi bi-reply"></i> Reply
                                    <small class="text-muted d-block">Guest reply with email verification</small>
                                </a>
                            @else
                                {{-- 일반 답글 --}}
                                <a class="dropdown-item" href="javascript:void(0)" onclick="event.preventDefault(); showReplyLoading({{ $comment->id }}); replyToComment({{ $comment->id }})">
                                    <i class="bi bi-reply"></i> Reply
                                </a>
                            @endif
                        </li>
                    @endif
                    
                    @if(isset($comment->permissions['canManage']) && $comment->permissions['canManage'] && $comment->status === 'pending')
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="approveComment({{ $comment->id }})">
                                <i class="bi bi-check-circle text-success"></i> Approve
                            </a>
                        </li>
                    @endif
                    
                    @if(isset($comment->permissions['canDelete']) && $comment->permissions['canDelete'])
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            @if(!Auth::check() && $comment->email_verified_at)
                                {{-- 비회원 댓글 삭제 (이메일 인증 완료) --}}
                                <a class="dropdown-item text-danger" href="javascript:void(0)" 
                                   onclick="requestEmailVerification({{ $comment->id }}, 'delete')">
                                    <i class="bi bi-trash"></i> Delete
                                    <small class="text-muted d-block">Email/Password required</small>
                                </a>
                            @elseif(Auth::check())
                                {{-- 회원 댓글 삭제 --}}
                                <a class="dropdown-item text-danger" href="javascript:void(0)" 
                                   onclick="deleteComment({{ $comment->id }})">
                                    <i class="bi bi-trash"></i> Delete
                                </a>
                            @endif
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
                        class="comment-attachment-image"
                        data-image-url="{{ $attachment->file_url }}" 
                        data-image-name="{{ $attachment->original_name }}" 
                        data-download-url="{{ $attachment->download_url }}">
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
    
    {{-- Dynamic Reply Form Container --}}
    <div id="reply-form-container-{{ $comment->id }}"></div>
    
    {{-- Dynamic Edit Form Container --}}
    <div id="edit-form-container-{{ $comment->id }}"></div>
    
    {{-- Child Comments --}}
    @if($comment->children && $comment->children->count() > 0 && $level < 3)
        <div class="child-comments">
            @foreach($comment->children->sortByDesc('created_at') as $child)
                @include('sitemanager::board.partials.comment', ['comment' => $child, 'level' => $level + 1])
            @endforeach
        </div>
    @endif
</div>
