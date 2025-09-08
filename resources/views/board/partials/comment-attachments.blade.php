@if($comment->attachments && $comment->attachments->count() > 0)
    @foreach($comment->attachments as $attachment)
        <div class="d-flex align-items-center justify-content-between mb-1 p-2 border rounded" data-attachment-id="{{ $attachment->id }}">
            <div class="d-flex align-items-center">
                @if($attachment->is_image)
                    <div class="me-2">
                        <img src="{{ $attachment->preview_url }}" 
                             alt="{{ $attachment->original_name }}" 
                             style="width: 24px; height: 24px; object-fit: cover; border-radius: 3px; cursor: pointer;"
                             onclick="showImageModal('{{ $attachment->file_url }}', '{{ $attachment->original_name }}', '{{ $attachment->download_url }}')">
                    </div>
                    <a href="{{ $attachment->download_url }}" download>{{ $attachment->original_name }}</a>
                @else
                    <i class="bi {{ $attachment->file_icon }} me-2"></i>
                    <a href="{{ $attachment->download_url }}" download>{{ $attachment->original_name }}</a>
                @endif
                <small class="text-muted ms-2">({{ $attachment->file_size_human }})</small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" 
                    onclick="removeExistingAttachment({{ $comment->id }}, {{ $attachment->id }})"
                    title="Remove attachment">
                <i class="bi bi-x"></i>
            </button>
        </div>
    @endforeach
@endif
