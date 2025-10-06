@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', isset($post) ? 'Edit Post - ' . $post->title : 'Write New Post - ' . $board->name)

@push('head')
    <meta name="robots" content="noindex,nofollow">

    {!! setResources(['flatpickr']) !!}

    {!! resource('sitemanager::js/board/form.js') !!}

    @if($board->getSetting('allow_secret_posts', false))
        {!! resource('sitemanager::js/board/secret.js') !!}
    @endif

    {!! resource('sitemanager::css/board.default.css') !!}
@endpush

@section('content')
<main class="board">
    <div class="container">
        <form method="POST" action="{{ isset($post) ? route('board.update', [$board->slug, $post->id]) : route('board.store', $board->slug) }}" enctype="multipart/form-data" data-board-slug="{{ $board->slug }}" data-post-id="{{ isset($post) ? $post->id : '' }}" data-edit-mode="{{ isset($post) ? 'true' : 'false' }}" class="board-form">
            @csrf
            @if(isset($post))
                @method('PUT')
            @endif
            
            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8 mb-4 mb-lg-0">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Post Content</h5>
                        </div>
                        <div class="card-body">
                            <!-- Title -->
                            <div class="form-group">
                                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                    id="title" name="title" value="{{ old('title', isset($post) ? $post->title : '') }}" 
                                    required maxlength="200" placeholder="Enter post title">
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Slug -->
                            <div class="form-group">
                                <label for="slug" class="form-label">
                                    URL Slug 
                                    <small class="text-muted">(SEO friendly URL)</small>
                                </label>
                                <div class="input-group">
                                    <input type="text" class="form-control @error('slug') is-invalid @enderror" 
                                        id="slug" name="slug" value="{{ old('slug', isset($post) ? $post->slug : '') }}" 
                                        maxlength="200" placeholder="url-friendly-slug">
                                    <button type="button" class="btn btn-outline-secondary" id="generate-slug-btn">
                                        <i class="bi bi-arrow-clockwise"></i> Generate
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" id="check-slug-btn">
                                        <i class="bi bi-check-circle"></i> Check
                                    </button>
                                </div>
                                <div class="form-text">
                                    <span id="slug-preview">{{ url('/board/' . $board->slug . '/') }}/<span id="slug-value">{{ old('slug', isset($post) ? $post->slug : 'your-slug') }}</span></span>
                                </div>
                                <div id="slug-feedback" class="mt-1"></div>
                                @error('slug')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Excerpt -->
                            <div class="form-group">
                                <label for="excerpt" class="form-label">
                                    Excerpt 
                                    <small class="text-muted">(Summary for SEO and previews)</small>
                                </label>
                                <div class="position-relative">
                                    <textarea class="form-control @error('excerpt') is-invalid @enderror" 
                                            id="excerpt" name="excerpt" rows="3" maxlength="1000"
                                            placeholder="Brief summary of your post...">{{ old('excerpt', isset($post) ? $post->excerpt : '') }}</textarea>
                                    <button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 m-1" 
                                            id="generate-excerpt-btn" title="Generate from content">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    <span id="excerpt-count">{{ mb_strlen(old('excerpt', isset($post) ? $post->excerpt : '')) }}</span>/1000 characters
                                </div>
                                @error('excerpt')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Content -->
                            <div class="form-group">
                                <label for="content" class="form-label">Content <span class="text-danger">*</span></label>
                                <x-sitemanager::editor 
                                    name="content" 
                                    :value="old('content', isset($post) ? $post->content : '')"
                                    height="500"
                                    placeholder="Write your post content here..." 
                                    referenceType="board"
                                    :referenceSlug="$board->slug"
                                    :referenceId="isset($post) ? $post->id : null"
                                />
                                @error('content')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- File Upload -->
                            @if($board->allowsFileUpload())
                                <x-sitemanager::file-upload 
                                    name="files[]"
                                    id="files"
                                    :multiple="true"
                                    :max-file-size="$board->getMaxFileSize()"
                                    :max-files="$board->getMaxFilesPerPost()"
                                    :allowed-types="$board->getAllowedFileTypes()"
                                    :file-categories="$board->getFileCategories()"
                                    :enable-preview="true"
                                    :enable-edit="true"
                                    :show-file-info="true"
                                    :existing-attachments="isset($post) ? $post->attachments : null"
                                    label="Attachments"
                                    icon="bi-paperclip"
                                    :errors="$errors"
                                />
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Post Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Post Settings</h6>
                        </div>
                        <div class="card-body">
                            <!-- Category -->
                            @if($board->usesCategories() && count($board->getCategoryOptions()) > 0)
                                <div class="form-group">
                                    <label class="form-label">Category</label>
                                    
                                    @if($board->getSetting('category_multiple', false))
                                        <!-- Multiple Category Selection (Checkboxes) -->
                                        @php
                                            $selectedCategories = old('categories', isset($post) ? $post->categories : []);
                                        @endphp
                                        
                                        <div class="row">
                                            @foreach($board->getCategoryOptions() as $category)
                                                <div class="col-md-6 col-lg-4 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input @error('categories') is-invalid @enderror" 
                                                            type="checkbox" 
                                                            name="categories[]" 
                                                            value="{{ $category }}" 
                                                            id="category_{{ $loop->index }}"
                                                            {{ in_array($category, $selectedCategories) ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="category_{{ $loop->index }}">
                                                            {{ $category }}
                                                        </label>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        @error('categories')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    @else
                                        <!-- Single Category Selection (Dropdown) -->
                                        <select class="form-select @error('category') is-invalid @enderror" 
                                                id="category" name="category">
                                            <option value="">Select Category</option>
                                            @foreach($board->getCategoryOptions() as $category)
                                                <option value="{{ $category }}" {{ old('category', isset($post) ? $post->category : '') === '|'.$category.'|' ? 'selected' : '' }}>
                                                    {{ $category }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('category')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    @endif
                                </div>
                            @endif

                            <!-- Tags -->
                            @if($board->usesTags())
                                <div class="form-group">
                                    <label for="tags" class="form-label">Tags</label>
                                    <input type="text" class="form-control @error('tags') is-invalid @enderror" 
                                        id="tags" name="tags" value="{{ old('tags', isset($post) && $post->tags ? (is_array($post->tags) ? implode(', ', $post->tags) : $post->tags) : '') }}" 
                                        placeholder="tag1, tag2, tag3">
                                    <div class="form-text">Separate tags with commas</div>
                                    @error('tags')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif

                            <!-- Author Selection (for authorized users with manage permission) -->
                            @if($canManage ?? false)
                                <div class="form-group">
                                    <label class="form-label">Author</label>
                                    
                                    @if(isset($members) && $members->count() > 0)
                                        <!-- 멤버 선택 -->
                                        <select id="author_member_select" name="member_id" class="form-select mb-2">
                                            <option value="">직접 입력</option>
                                            @foreach($members as $member)
                                                @php
                                                    $displayName = $member->name;
                                                    
                                                    // 그룹명 추가
                                                    if (!empty($member->groups) && is_array($member->groups)) {
                                                        $groupNames = implode(', ', $member->groups);
                                                        $displayName .= ' ' . $groupNames;
                                                    }
                                                    
                                                    // 직책이 있으면 추가
                                                    if (!empty($member->title)) {
                                                        $displayName .= ' (' . $member->title . ')';
                                                    }
                                                    
                                                    // author_name에 들어갈 이름 (그룹명 포함)
                                                    $authorNameValue = $member->name;
                                                    if (!empty($member->groups) && is_array($member->groups)) {
                                                        $authorNameValue .= ' ' . implode(', ', $member->groups);
                                                    }
                                                @endphp
                                                <option value="{{ $member->id }}" 
                                                        data-member-name="{{ $member->name }}"
                                                        data-author-name="{{ $authorNameValue }}"
                                                        {{ (old('member_id') ?? ($post->member_id ?? '')) == $member->id ? 'selected' : '' }}>
                                                    {{ $displayName }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @endif
                                    
                                    <!-- 작성자 이름 입력 -->
                                    <input type="text" class="form-control @error('author_name') is-invalid @enderror" 
                                        id="author_name" name="author_name" value="{{ old('author_name', isset($post) ? $post->author_name : '') }}" 
                                        maxlength="50" placeholder="Author Name">
                                    <div class="form-text">Select a member or enter manually</div>
                                    @error('author_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @elseif(!auth()->check())
                                <!-- Author Name (for guests) -->
                                <div class="form-group">
                                    <label for="author_name" class="form-label">Your Name</label>
                                    <input type="text" class="form-control @error('author_name') is-invalid @enderror" 
                                        id="author_name" name="author_name" value="{{ old('author_name', isset($post) ? $post->author_name : '') }}" 
                                        maxlength="50" placeholder="Enter your name">
                                    @error('author_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif

                            @if (isset($post) && $canManage ?? false)
                                <!-- Published At (for users with manage permission) -->
                                <div class="form-group">
                                    <label for="published_at" class="form-label">Published At</label>
                                    <input type="text" class="form-control @error('published_at') is-invalid @enderror" 
                                        id="published_at" name="published_at" 
                                        placeholder="{{ $post->published_at ? $post->published_at->format('Y-m-d H:i') : '' }}"
                                        data-current-value="{{ $post->published_at ? $post->published_at->format('Y-m-d H:i') : '' }}">
                                    @error('published_at')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif

                            <!-- Secret Password Section -->
                            @if($board->getSetting('allow_secret_posts', false))
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-lock-fill text-warning"></i> Private Post Settings</label>
                                    <div class="secret-post-section">
                                        @if(isset($post) && $post->isSecret())
                                            <div class="alert alert-warning mb-3 p-2">
                                                <i class="bi bi-lock"></i> This post is currently set as private.
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="remove_secret_password" 
                                                    name="remove_secret_password" value="1">
                                                <label class="form-check-label" for="remove_secret_password">
                                                    <span class="text-danger">Remove Private Setting</span>
                                                    <small class="text-muted d-block">Check to remove password and make this a public post.</small>
                                                </label>
                                            </div>
                                            
                                            <div id="password-change-section">
                                                <label for="secret_password" class="form-label">Change Password</label>
                                                <input type="password" class="form-control @error('secret_password') is-invalid @enderror" 
                                                    id="secret_password" name="secret_password" 
                                                    placeholder="New password (leave empty to keep current password)"
                                                    minlength="4" maxlength="20">
                                                <div class="form-text">Enter a new password to change the existing one.</div>
                                            </div>
                                        @else
                                            <label for="secret_password" class="form-label">Password</label>
                                            <input type="password" class="form-control @error('secret_password') is-invalid @enderror" 
                                                id="secret_password" name="secret_password" 
                                                value="{{ old('secret_password') }}"
                                                placeholder="Enter password (4-20 characters)"
                                                minlength="4" maxlength="20">
                                            <div class="form-text">
                                                <i class="bi bi-info-circle"></i> 
                                                Setting a password will make this post visible only to users who know the password.
                                            </div>
                                        @endif
                                        
                                        @error('secret_password')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            @endif

                            <!-- Special Options (for users with manage permission) -->
                            @if($canManage ?? false)
                                <div class="form-group">
                                    <label class="form-label">Options</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="option_is_notice" 
                                            name="options[is_notice]" value="1" 
                                            {{ old('options.is_notice', isset($post) && $post->hasOption('is_notice') ? '1' : '') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="option_is_notice">
                                            Mark as Notice
                                        </label>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <a href="{{ isset($post) ? route('board.show', [$board->slug, $post->id]) : route('board.index', $board->slug) }}" class="btn btn-outline-dark">
                            <i class="bi bi-arrow-left"></i> Cancel
                        </a>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-dark">
                                <i class="bi bi-send"></i> {{ isset($post) ? 'Update Post' : 'Publish Post' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Hidden inputs for attachment removal -->
        <form id="attachment-removal-form" style="display: none;">
            @csrf
            @method('DELETE')
        </form>
    </div>
</main>
@endsection
