<div class="table-responsive">
    <table class="table table-hover align-middle board-posts-table">
        <thead>
            <tr>
                <th> # </th>
                <th class="text-start"> Title </th>
                @if ($board->getSetting('show_name', false))
                <th class="text-start"> Author </th>
                @endif
                <th class="text-center"> Views </th>
                @if ($board->getSetting('allow_comments', false))
                <th class="text-center"> Comments </th>
                @endif
                @if ($board->getSetting('enable_likes', false))
                <th class="text-center"> Likes </th>
                @endif
                <th class="text-end"> Date </th>
            </tr>
        </thead>
        <tbody>
            @if($board->getSetting('enable_notice', false) && isset($notices) && $notices->count() > 0)
                @foreach($notices as $post)
                <tr class="notice">
                    <td><i class="bi bi-flag-fill"></i></td>

                    <td class="text-start">
                        <div class="post-title">
                            @if($post->category)
                                <div class="post-categories">
                                    @foreach($post->categories as $category)
                                        <span>{{ $category }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if($post->isSecret())
                                <i class="bi bi-lock-fill text-warning me-1"></i>
                            @endif

                            <a href="{{ route('board.show', [$board->slug, $post->slug ?: $post->id]) }}">
                                {{ $post->title }}
                            </a>
                        </div>
                    </td>

                    @if ($board->getSetting('show_name', false))
                    <td class="text-start">
                        <div class="d-flex align-items-center justify-content-start">
                            @if($post->author_profile_photo)
                                <img src="{{ $post->author_profile_photo }}" 
                                        alt="{{ $post->author }}" 
                                        class="rounded-circle me-2"
                                        style="width: 24px; height: 24px; object-fit: cover;">
                            @endif
                            <span class="text-secondary fw-medium small">{{ $post->author }}</span>
                        </div>
                    </td>
                    @endif

                    <td class="text-center">
                        <span class="text-muted small">
                            {{ $post->view_count ? number_format($post->view_count):'-' }}
                        </span>
                    </td>

                    @if ($board->getSetting('allow_comments', false))
                    <td class="text-center">
                        <span class="text-muted small">
                            {{ $post->comment_count ? number_format($post->comment_count) : '-' }}
                        </span>
                    </td>
                    @endif

                    @if ($board->getSetting('enable_likes', false))
                    <td class="text-center">
                        <span class="text-muted small">
                            {{ $post->like_count ? number_format($post->like_count) : '-' }}
                        </span>
                    </td>
                    @endif
                    
                    <td class="date">
                        <time class="text-muted small">{{ $post->created_at->format('M j, Y') }}</time>
                    </td>
                </tr>
                @endforeach
            @endif

            @php
                $currentPostId = isset($post) ? $post->id : null;
            @endphp
            @foreach($posts as $row)
                <tr @class(['active' => $currentPostId && $row->id == $currentPostId])>
                    <td>
                        {{ $row->id }}
                    </td>

                    <td class="title">
                        <div class="post-title">
                            @if($row->category)
                                <div class="post-categories">
                                    @foreach($row->categories as $category)
                                        <span>{{ $category }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if($row->isSecret())
                                <i class="bi bi-lock-fill text-warning me-1"></i>
                            @endif

                            <a href="{{ route('board.show', [$board->slug, $row->slug ?: $row->id]) }}">
                                {{ $row->title }}
                            </a>
                        </div>
                    </td>

                    @if ($board->getSetting('show_name', false))
                    <td class="author">
                        <div class="d-flex align-items-center justify-content-start">
                            @if($row->author_profile_photo)
                                <img src="{{ $row->author_profile_photo }}" 
                                        alt="{{ $row->author }}" 
                                        class="profile-photo">
                            @else
                                <i class="bi bi-person-circle profile-photo-icon"></i>
                            @endif
                            <span class="text-secondary fw-medium small ms-1">{{ $row->author }}</span>
                        </div>
                    </td>
                    @endif

                    <td class="text-center">
                        <span class="text-muted small">
                            {{ $row->view_count ? number_format($row->view_count):'-' }}
                        </span>
                    </td>

                    @if ($board->getSetting('allow_comments', false))
                    <td class="text-center">
                        <span class="text-muted small">
                            {{ $row->comment_count ? number_format($row->comment_count) : '-' }}
                        </span>
                    </td>
                    @endif

                    @if ($board->getSetting('enable_likes', false))
                    <td class="text-center">
                        <span class="text-muted small">
                            {{ $row->like_count ? number_format($row->like_count) : '-' }}
                        </span>
                    </td>
                    @endif

                    <td class="date">
                        <time class="text-muted small">{{ $row->created_at->format('M j, Y') }}</time>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if($posts->hasPages())
    <div class="d-none d-md-block">
    @php
        // pagination 링크를 목록 페이지로 연결
        $posts->withPath(route('board.index', $board->slug));
    @endphp
    {{ $posts->appends(request()->except('id'))->onEachSide(1)->links('sitemanager::pagination.default') }}
    </div>
    <div class="d-md-none">
        {{ $posts->appends(request()->except('id'))->links('sitemanager::pagination.simple') }}
    </div>
@endif