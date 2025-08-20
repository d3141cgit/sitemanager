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
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_notice" 
                                           name="is_notice" value="1" {{ old('is_notice', isset($post) ? $post->is_notice : false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_notice">
                                        Mark as Notice
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_featured" 
                                           name="is_featured" value="1" {{ old('is_featured', isset($post) ? $post->is_featured : false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_featured">
                                        Mark as Featured
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
@if(!isset($post))
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const titleInput = document.getElementById('title');
    const contentInput = document.getElementById('content');
    
    // Load saved data
    if (localStorage.getItem('post_title')) {
        titleInput.value = localStorage.getItem('post_title');
    }
    if (localStorage.getItem('post_content')) {
        contentInput.value = localStorage.getItem('post_content');
    }
    
    // Save data on input
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
});
@endif
</script>
@endpush
