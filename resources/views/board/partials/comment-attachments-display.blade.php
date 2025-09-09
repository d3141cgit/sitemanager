@if($comment->attachments && $comment->attachments->count() > 0)
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
@endif
