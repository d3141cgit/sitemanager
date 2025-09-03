<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ isset($post) ? $post->title : t('Post Not Found') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .post-content img {
            max-width: 100%;
            height: auto;
            border: 2px solid #007bff;
            border-radius: 8px;
            margin: 10px 0;
            box-shadow: 0 2px 8px rgba(0,123,255,0.3);
        }
        .highlighted-image {
            animation: highlight 2s ease-in-out infinite;
        }
        @keyframes highlight {
            0%, 100% { box-shadow: 0 2px 8px rgba(0,123,255,0.3); }
            50% { box-shadow: 0 4px 16px rgba(255,193,7,0.6); }
        }
    </style>
</head>
<body>
    <div class="container my-4">
        @if(isset($error))
            <div class="alert alert-danger">
                <h4><i class="bi bi-exclamation-triangle"></i> {{ t('Error') }}</h4>
                <p>{{ $error }}</p>
            </div>
        @elseif(isset($post))
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0">{{ $post->title }}</h3>
                        <small class="text-muted">
                            {{ t('Board') }}: <span class="badge bg-info">{{ $boardSlug }}</span> | 
                            {{ t('Created') }}: {{ $post->created_at->format('Y-m-d H:i') }}
                        </small>
                    </div>
                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="highlightImages()">
                        <i class="bi bi-lightbulb"></i> {{ t('Highlight Images') }}
                    </button>
                </div>
                
                <div class="card-body">
                    <div class="post-content">
                        {!! $post->content !!}
                    </div>
                </div>
                
                @if(isset($post->created_at) || isset($post->updated_at))
                    <div class="card-footer text-muted">
                        <small>
                            @if($post->created_at)
                                {{ t('Created') }}: {{ $post->created_at->format('Y-m-d H:i:s') }}
                            @endif
                            @if($post->updated_at && $post->updated_at != $post->created_at)
                                | {{ t('Updated') }}: {{ $post->updated_at->format('Y-m-d H:i:s') }}
                            @endif
                        </small>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 번역 변수
        const noImagesMessage = '{{ t('This post contains no images.') }}';
        
        function highlightImages() {
            const images = document.querySelectorAll('.post-content img');
            
            images.forEach(img => {
                img.classList.add('highlighted-image');
                
                // 5초 후 하이라이트 제거
                setTimeout(() => {
                    img.classList.remove('highlighted-image');
                }, 5000);
            });
            
            if (images.length === 0) {
                alert(noImagesMessage);
            } else {
                // 첫 번째 이미지로 스크롤
                images[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        
        // URL 파라미터에서 이미지 하이라이트 확인
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('highlight') === 'images') {
            setTimeout(highlightImages, 500);
        }
    </script>
</body>
</html>
