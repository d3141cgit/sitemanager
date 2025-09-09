@if($comment->attachments && $comment->attachments->count() > 0)
    @foreach($comment->attachments as $attachment)
        <div class="existing-attachment-item" data-attachment-id="{{ $attachment->id }}">
            <div>
                @if($attachment->is_image)
                    <img src="{{ $attachment->preview_url }}" alt="{{ $attachment->original_name }}" onclick="showImageModal('{{ $attachment->file_url }}', '{{ $attachment->original_name }}', '{{ $attachment->download_url }}')">
                    <a href="{{ $attachment->download_url }}" download>{{ $attachment->original_name }}</a>
                @else
                    <i class="bi {{ $attachment->file_icon }} me-2"></i>
                    <a href="{{ $attachment->download_url }}" download>{{ $attachment->original_name }}</a>
                @endif
                <small class="text-muted ms-2">({{ $attachment->file_size_human }})</small>
            </div>
            <button type="button" onclick="removeExistingAttachment({{ $comment->id }}, {{ $attachment->id }})" title="Remove attachment">
                <i class="bi bi-x"></i>
            </button>
        </div>
    @endforeach
@endif
