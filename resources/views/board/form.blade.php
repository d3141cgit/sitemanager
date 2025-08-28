@extends('sitemanager::layouts.app')

@section('title', isset($post) ? 'Edit Post - ' . $post->title : 'Write New Post - ' . $board->name)

@section('content')
<div class="container py-4">
    <!-- Navigation Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('board.index', $board->slug) }}">{{ $board->name }}</a>
            </li>
            @if(isset($post))
                <li class="breadcrumb-item">
                    <a href="{{ route('board.show', [$board->slug, $post->id]) }}">{{ Str::limit($post->title, 30) }}</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            @else
                <li class="breadcrumb-item active" aria-current="page">Write New Post</li>
            @endif
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3">{{ isset($post) ? 'Edit Post' : 'Write New Post' }}</h1>
            <p class="text-muted">{{ isset($post) ? 'Update your post content' : 'Share your thoughts with the community' }}</p>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <h6>Please fix the following errors:</h6>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ isset($post) ? route('board.update', [$board->slug, $post->id]) : route('board.store', $board->slug) }}" enctype="multipart/form-data">
        @csrf
        @if(isset($post))
            @method('PUT')
        @endif
        
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Post Content</h5>
                    </div>
                    <div class="card-body">
                        <!-- Title -->
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                   id="title" name="title" value="{{ old('title', isset($post) ? $post->title : '') }}" 
                                   required maxlength="200" placeholder="Enter post title">
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Slug -->
                        <div class="mb-3">
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
                                <span id="slug-preview">{{ url('/board/' . $board->slug . '/') }}<span id="slug-value">{{ old('slug', isset($post) ? $post->slug : 'your-slug') }}</span></span>
                            </div>
                            <div id="slug-feedback" class="mt-1"></div>
                            @error('slug')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Excerpt -->
                        <div class="mb-3">
                            <label for="excerpt" class="form-label">
                                Excerpt 
                                <small class="text-muted">(Summary for SEO and previews)</small>
                            </label>
                            <div class="position-relative">
                                <textarea class="form-control @error('excerpt') is-invalid @enderror" 
                                          id="excerpt" name="excerpt" rows="3" maxlength="1000"
                                          placeholder="Brief summary of your post...">{{ old('excerpt', isset($post) ? $post->excerpt : '') }}</textarea>
                                <button type="button" class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-1" 
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
                        <div class="mb-3">
                            <label for="content" class="form-label">Content <span class="text-danger">*</span></label>
                            <x-sitemanager::editor 
                                name="content" 
                                :value="old('content', isset($post) ? $post->content : '')"
                                height="500"
                                placeholder="Write your post content here..." 
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
                        <h6 class="card-title mb-0">Post Settings</h6>
                    </div>
                    <div class="card-body">
                        <!-- Category -->
                        @if($board->usesCategories() && count($board->getCategoryOptions()) > 0)
                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select @error('category') is-invalid @enderror" 
                                        id="category" name="category">
                                    <option value="">Select Category</option>
                                    @foreach($board->getCategoryOptions() as $category)
                                        <option value="{{ $category }}" {{ old('category', isset($post) ? $post->category : '') === $category ? 'selected' : '' }}>
                                            {{ $category }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif

                        <!-- Tags -->
                        @if($board->usesTags())
                            <div class="mb-3">
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

                        <!-- Author Name (for guests) -->
                        @if(!auth()->check())
                            <div class="mb-3">
                                <label for="author_name" class="form-label">Your Name</label>
                                <input type="text" class="form-control @error('author_name') is-invalid @enderror" 
                                       id="author_name" name="author_name" value="{{ old('author_name', isset($post) ? $post->author_name : '') }}" 
                                       maxlength="50" placeholder="Enter your name">
                                @error('author_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif

                        <!-- Special Options (for admins) -->
                        @php
                            $user = auth()->user();
                            $isAdmin = $user && $user->level >= config('member.admin_level', 200);
                        @endphp
                        
                        @if($isAdmin)
                            <div class="mb-3">
                                <label class="form-label">Options</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="option_is_notice" 
                                           name="options[is_notice]" value="1" 
                                           {{ old('options.is_notice', isset($post) && $post->hasOption('is_notice') ? '1' : '') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="option_is_notice">
                                        Mark as Notice
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="option_show_image" 
                                           name="options[show_image]" value="1" 
                                           {{ old('options.show_image', isset($post) && $post->hasOption('show_image') ? '1' : '') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="option_show_image">
                                        Show Image in List
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="option_no_indent" 
                                           name="options[no_indent]" value="1" 
                                           {{ old('options.no_indent', isset($post) && $post->hasOption('no_indent') ? '1' : '') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="option_no_indent">
                                        No Indent
                                    </label>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Tips Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">{{ isset($post) ? 'Edit Tips' : 'Writing Tips' }}</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small text-muted mb-0">
                            <li class="mb-2">
                                <i class="bi bi-lightbulb text-warning"></i>
                                Write a clear and descriptive title
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-lightbulb text-warning"></i>
                                Use proper formatting and paragraphs
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-lightbulb text-warning"></i>
                                Be respectful and constructive
                            </li>
                            @if($board->getSetting('moderate_comments', false))
                                <li class="mb-2">
                                    <i class="bi bi-info-circle text-info"></i>
                                    {{ isset($post) ? 'Changes require approval' : 'Posts require approval before being published' }}
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <a href="{{ isset($post) ? route('board.show', [$board->slug, $post->id]) : route('board.index', $board->slug) }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Cancel
                    </a>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
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

@endsection


@push('scripts')
<style>
.form-control:focus { border-color: #86b7fe; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25); }
textarea { resize: vertical; min-height: 200px; }
.card { box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
</style>

<script>
// Character counter for title
document.getElementById('title').addEventListener('input', function() {
    const maxLength = 200;
    const currentLength = this.value.length;
    const remaining = maxLength - currentLength;
    
    let counter = document.getElementById('title-counter');
    if (!counter) {
        counter = document.createElement('div');
        counter.id = 'title-counter';
        counter.className = 'form-text';
        this.parentNode.appendChild(counter);
    }
    
    counter.textContent = `${currentLength}/${maxLength} characters`;
    counter.className = remaining < 20 ? 'form-text text-warning' : 'form-text text-muted';
});

// Auto-save to localStorage (only for create mode)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Slug and Excerpt management
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    const slugValue = document.getElementById('slug-value');
    const slugFeedback = document.getElementById('slug-feedback');
    const generateSlugBtn = document.getElementById('generate-slug-btn');
    const checkSlugBtn = document.getElementById('check-slug-btn');
    
    const excerptInput = document.getElementById('excerpt');
    const excerptCount = document.getElementById('excerpt-count');
    const generateExcerptBtn = document.getElementById('generate-excerpt-btn');
    
    const boardSlug = '{{ $board->slug }}';
    const postId = {{ isset($post) ? $post->id : 'null' }};
    
    // Update slug preview
    function updateSlugPreview() {
        const slugVal = slugInput.value || 'your-slug';
        slugValue.textContent = slugVal;
    }
    
    // Update excerpt character count
    function updateExcerptCount() {
        const length = excerptInput.value.length;
        excerptCount.textContent = length;
        excerptCount.parentElement.className = length > 1000 ? 'form-text text-danger' : 'form-text';
    }
    
    // Generate slug from title
    function generateSlugFromTitle() {
        const title = titleInput.value.trim();
        if (!title) {
            alert('Please enter a title first');
            return;
        }
        
        generateSlugBtn.disabled = true;
        generateSlugBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating...';
        
        fetch(`/board/${boardSlug}/generate-slug`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ title: title })
        })
        .then(response => response.json())
        .then(data => {
            if (data.slug) {
                slugInput.value = data.slug;
                updateSlugPreview();
                checkSlugAvailability(); // Auto-check after generation
            }
        })
        .catch(error => {
            console.error('Error generating slug:', error);
            alert('Error generating slug. Please try again.');
        })
        .finally(() => {
            generateSlugBtn.disabled = false;
            generateSlugBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Generate';
        });
    }
    
    // Check slug availability
    function checkSlugAvailability() {
        const slug = slugInput.value.trim();
        if (!slug) {
            slugFeedback.innerHTML = '';
            return;
        }
        
        checkSlugBtn.disabled = true;
        checkSlugBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Checking...';
        slugFeedback.innerHTML = '<small class="text-muted">Checking availability...</small>';
        
        fetch(`/board/${boardSlug}/check-slug`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ 
                slug: slug,
                post_id: postId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.available) {
                slugFeedback.innerHTML = '<small class="text-success"><i class="bi bi-check-circle"></i> ' + data.message + '</small>';
                slugInput.classList.remove('is-invalid');
                slugInput.classList.add('is-valid');
            } else {
                slugFeedback.innerHTML = `
                    <small class="text-danger"><i class="bi bi-x-circle"></i> ${data.message}</small>
                    ${data.suggested_slug ? `<br><small class="text-muted">Suggestion: <a href="#" onclick="useSlug('${data.suggested_slug}')">${data.suggested_slug}</a></small>` : ''}
                `;
                slugInput.classList.remove('is-valid');
                slugInput.classList.add('is-invalid');
            }
        })
        .catch(error => {
            console.error('Error checking slug:', error);
            slugFeedback.innerHTML = '<small class="text-danger">Error checking availability</small>';
        })
        .finally(() => {
            checkSlugBtn.disabled = false;
            checkSlugBtn.innerHTML = '<i class="bi bi-check-circle"></i> Check';
        });
    }
    
    // Use suggested slug
    window.useSlug = function(slug) {
        slugInput.value = slug;
        updateSlugPreview();
        checkSlugAvailability();
    };
    
    // Generate excerpt from content
    function generateExcerptFromContent() {
        // Get content from editor (assuming it's a textarea or has a value property)
        let content = '';
        const contentInput = document.querySelector('[name="content"]');
        
        if (contentInput) {
            content = contentInput.value;
        } else {
            // Try to get from rich editor if available
            const editorFrame = document.querySelector('.editor-frame iframe');
            if (editorFrame && editorFrame.contentDocument) {
                content = editorFrame.contentDocument.body.textContent || editorFrame.contentDocument.body.innerText || '';
            }
        }
        
        if (!content.trim()) {
            alert('Please write some content first');
            return;
        }
        
        generateExcerptBtn.disabled = true;
        generateExcerptBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
        
        fetch('/board/generate-excerpt', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ 
                content: content,
                length: 200
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.excerpt) {
                excerptInput.value = data.excerpt;
                updateExcerptCount();
            }
        })
        .catch(error => {
            console.error('Error generating excerpt:', error);
            alert('Error generating excerpt. Please try again.');
        })
        .finally(() => {
            generateExcerptBtn.disabled = false;
            generateExcerptBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
        });
    }
    
    // Event listeners
    slugInput.addEventListener('input', updateSlugPreview);
    excerptInput.addEventListener('input', updateExcerptCount);
    generateSlugBtn.addEventListener('click', generateSlugFromTitle);
    checkSlugBtn.addEventListener('click', checkSlugAvailability);
    generateExcerptBtn.addEventListener('click', generateExcerptFromContent);
    
    // Auto-generate slug when title changes (optional)
    let titleTimeout;
    titleInput.addEventListener('input', function() {
        clearTimeout(titleTimeout);
        titleTimeout = setTimeout(() => {
            if (titleInput.value.trim() && !slugInput.value.trim()) {
                generateSlugFromTitle();
            }
        }, 1000); // Wait 1 second after user stops typing
    });
    
    // Initialize
    updateSlugPreview();
    updateExcerptCount();

@if(!isset($post))
    const form = document.querySelector('form');
    const contentInput = document.querySelector('[name="content"]');
    
    // Auto-save functionality
    if (titleInput && localStorage.getItem('post_title')) {
        titleInput.value = localStorage.getItem('post_title');
    }
    
    if (contentInput && localStorage.getItem('post_content')) {
        contentInput.value = localStorage.getItem('post_content');
    }
    
    titleInput.addEventListener('input', function() {
        localStorage.setItem('post_title', this.value);
    });
    
    contentInput.addEventListener('input', function() {
        localStorage.setItem('post_content', this.value);
    });
    
    // Clear saved data on form submit
    form.addEventListener('submit', function() {
        localStorage.removeItem('post_title');
        localStorage.removeItem('post_content');
    });
    
    // Warn before leaving with unsaved changes
    let hasUnsavedChanges = false;
    
    titleInput.addEventListener('input', () => hasUnsavedChanges = true);
    contentInput.addEventListener('input', () => hasUnsavedChanges = true);
    
    window.addEventListener('beforeunload', function(e) {
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    
    form.addEventListener('submit', () => hasUnsavedChanges = false);
@endif
});
</script>
@endpush
</script>
@endpush
