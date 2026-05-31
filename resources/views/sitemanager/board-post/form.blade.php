@extends('sitemanager::layouts.sitemanager')

@section('title', isset($post) ? 'Edit Post' : 'Create Post')

@push('head')
    {!! setResources(['flatpickr']) !!}
    {!! resource('sitemanager::js/board/form.js') !!}
@endpush

@section('content')
<div class="content-header">
    <h1>
        <a href="{{ route('sitemanager.boards.index') }}">
            <i class="bi bi-list-ul opacity-75"></i> {{ t('Board Management') }}
        </a>
        <i class="bi bi-chevron-right small opacity-50"></i>
        <a href="{{ route('sitemanager.boards.posts.index', $board) }}">{{ $board->name }} Posts</a>
        <i class="bi bi-chevron-right small opacity-50"></i>
        {{ isset($post) ? 'Edit #' . $post->id : 'Create Post' }}
    </h1>

    <div class="d-flex gap-1">
        @if(isset($post))
            <a href="{{ route('board.show', [$board->slug, $post->slug ?: $post->id]) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-box-arrow-up-right"></i> View
            </a>
        @endif
        <a href="{{ route('sitemanager.boards.posts.index', $board) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<form method="POST"
      action="{{ isset($post) ? route('sitemanager.boards.posts.update', [$board, $post->id]) : route('sitemanager.boards.posts.store', $board) }}"
      enctype="multipart/form-data"
      data-board-slug="{{ $board->slug }}"
      data-post-id="{{ isset($post) ? $post->id : '' }}"
      data-edit-mode="{{ isset($post) ? 'true' : 'false' }}"
      class="board-form">
    @csrf
    @if(isset($post)) @method('PUT') @endif

    <div class="row">
        <div class="col-lg-8 mb-4 mb-lg-0">
            <div class="card default-form">
                <div class="card-header">
                    <h4>{{ t('Post Content') }}</h4>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title', $post->title ?? '') }}" required maxlength="200">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label for="slug" class="form-label">URL Slug</label>
                        <div class="input-group">
                            <input type="text" id="slug" name="slug" class="form-control @error('slug') is-invalid @enderror"
                                   value="{{ old('slug', $post->slug ?? '') }}" maxlength="200">
                            <button type="button" class="btn btn-outline-secondary" id="generate-slug-btn">
                                <i class="bi bi-arrow-clockwise"></i> Generate
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="check-slug-btn">
                                <i class="bi bi-check-circle"></i> Check
                            </button>
                        </div>
                        <div class="form-text">
                            <span id="slug-preview">{{ url('/board/' . $board->slug . '/') }}/<span id="slug-value">{{ old('slug', $post->slug ?? 'your-slug') }}</span></span>
                        </div>
                        <div id="slug-feedback" class="mt-1"></div>
                        @error('slug')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label for="excerpt" class="form-label">Excerpt</label>
                        <div class="position-relative">
                            <textarea id="excerpt" name="excerpt" rows="3" maxlength="1000"
                                      class="form-control @error('excerpt') is-invalid @enderror">{{ old('excerpt', $post->excerpt ?? '') }}</textarea>
                            <button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 m-1"
                                    id="generate-excerpt-btn" title="Generate from content">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            <span id="excerpt-count">{{ mb_strlen(old('excerpt', $post->excerpt ?? '')) }}</span>/1000 characters
                        </div>
                        @error('excerpt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label for="content" class="form-label">Content</label>
                        <x-sitemanager::editor
                            name="content"
                            :value="old('content', $post->content ?? '')"
                            height="520"
                            placeholder="Write post content..."
                            referenceType="board"
                            :referenceSlug="$board->slug"
                            :referenceId="isset($post) ? $post->id : null"
                        />
                        @error('content')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

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

        <div class="col-lg-4">
            <div class="card default-form mb-3">
                <div class="card-header">
                    <h4>{{ t('Post Settings') }}</h4>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                            @foreach(['published' => 'Published', 'draft' => 'Draft', 'private' => 'Private'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $post->status ?? 'published') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    @if($board->usesCategories() && count($board->getCategoryOptions()) > 0)
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            @if($board->getSetting('category_multiple', false))
                                @php $selectedCategories = old('categories', isset($post) ? $post->categories : []); @endphp
                                <div class="d-flex flex-column gap-1">
                                    @foreach($board->getCategoryOptions() as $category)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="categories[]" value="{{ $category }}"
                                                   id="category_{{ $loop->index }}" @checked(in_array($category, $selectedCategories, true))>
                                            <label class="form-check-label" for="category_{{ $loop->index }}">{{ $category }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <select id="category" name="category" class="form-select @error('category') is-invalid @enderror">
                                    <option value="">Select Category</option>
                                    @foreach($board->getCategoryOptions() as $category)
                                        <option value="{{ $category }}" @selected(old('category', isset($post) ? trim((string) $post->category, '|') : '') === $category)>
                                            {{ $category }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    @endif

                    @if($board->usesTags())
                        <div class="form-group">
                            <label for="tags" class="form-label">Tags</label>
                            <input type="text" id="tags" name="tags" class="form-control @error('tags') is-invalid @enderror"
                                   value="{{ old('tags', isset($post) && $post->tags ? (is_array($post->tags) ? implode(', ', $post->tags) : $post->tags) : '') }}"
                                   placeholder="tag1, tag2">
                            <div class="form-text">Separate tags with commas</div>
                            @error('tags')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    <div class="form-group">
                        <label for="author_member_select" class="form-label">Author</label>
                        <select id="author_member_select" name="member_id" class="form-select mb-2">
                            <option value="">Manual author</option>
                            @foreach($members as $member)
                                <option value="{{ $member->id }}" data-member-name="{{ $member->name }}" data-author-name="{{ $member->name }}"
                                        @selected((string) old('member_id', $post->member_id ?? '') === (string) $member->id)>
                                    {{ $member->name }}{{ $member->email ? ' - ' . $member->email : '' }}
                                </option>
                            @endforeach
                        </select>
                        <input type="text" id="author_name" name="author_name" class="form-control @error('author_name') is-invalid @enderror"
                               value="{{ old('author_name', $post->author_name ?? '') }}" maxlength="50" placeholder="Author name">
                        @error('author_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label for="author_email" class="form-label">Author Email</label>
                        <input type="email" id="author_email" name="author_email" class="form-control @error('author_email') is-invalid @enderror"
                               value="{{ old('author_email', $post->author_email ?? '') }}" maxlength="100">
                        @error('author_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label for="published_at" class="form-label">Published At</label>
                        <input type="text" id="published_at" name="published_at"
                               class="form-control @error('published_at') is-invalid @enderror"
                               value="{{ old('published_at', isset($post) && $post->published_at ? $post->published_at->format('Y-m-d H:i') : now()->format('Y-m-d H:i')) }}">
                        @error('published_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    @if($board->getSetting('allow_secret_posts', false))
                        <div class="form-group">
                            <label for="secret_password" class="form-label">Private Password</label>
                            @if(isset($post) && $post->isSecret())
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="remove_secret_password" name="remove_secret_password" value="1">
                                    <label class="form-check-label text-danger" for="remove_secret_password">Remove private setting</label>
                                </div>
                            @endif
                            <input type="password" id="secret_password" name="secret_password"
                                   class="form-control @error('secret_password') is-invalid @enderror"
                                   minlength="4" maxlength="20" autocomplete="new-password"
                                   placeholder="{{ isset($post) && $post->isSecret() ? 'New password' : 'Password' }}">
                            @error('secret_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    @if(!empty($postOptionFields))
                        <div class="form-group">
                            <label class="form-label">Options</label>
                            <input type="hidden" name="options_present" value="1">
                            <div class="d-flex flex-column gap-2">
                                @foreach($postOptionFields as $optionKey => $optionConfig)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               id="option_{{ $optionKey }}"
                                               name="options[{{ $optionKey }}]"
                                               value="1"
                                               @checked(old("options.{$optionKey}", $postOptions[$optionKey] ?? false))>
                                        <label class="form-check-label" for="option_{{ $optionKey }}">
                                            {{ $optionConfig['label'] ?? \Illuminate\Support\Str::headline($optionKey) }}
                                        </label>
                                        @if(!empty($optionConfig['help']))
                                            <div class="form-text">{{ $optionConfig['help'] }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            @if(!empty($postFields))
                <div class="card default-form mb-3">
                    <div class="card-header"><h4>{{ t('Post Metadata') }}</h4></div>
                    <div class="card-body">
                        @foreach($postFields as $key => $field)
                            @php
                                $type = data_get($field, 'type', 'text');
                                $label = data_get($field, 'label', \Illuminate\Support\Str::headline($key));
                                $help = data_get($field, 'help');
                                $placeholder = data_get($field, 'placeholder');
                                $value = old("meta.{$key}", $postMeta[$key] ?? data_get($field, 'default', ''));
                                $options = data_get($field, 'options', []);
                            @endphp
                            <div class="form-group">
                                <label for="meta_{{ $key }}" class="form-label">{{ $label }}</label>

                                @if($type === 'textarea')
                                    <textarea id="meta_{{ $key }}"
                                              name="meta[{{ $key }}]"
                                              rows="{{ data_get($field, 'rows', 3) }}"
                                              class="form-control @error("meta.{$key}") is-invalid @enderror"
                                              placeholder="{{ $placeholder }}">{{ $value }}</textarea>
                                @elseif($type === 'select')
                                    <select id="meta_{{ $key }}"
                                            name="meta[{{ $key }}]"
                                            class="form-select @error("meta.{$key}") is-invalid @enderror">
                                        <option value="">Select</option>
                                        @foreach($options as $optionValue => $optionLabel)
                                            <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                @elseif($type === 'boolean')
                                    <div class="form-check">
                                        <input class="form-check-input @error("meta.{$key}") is-invalid @enderror"
                                               type="checkbox"
                                               id="meta_{{ $key }}"
                                               name="meta[{{ $key }}]"
                                               value="1"
                                               @checked((bool) $value)>
                                    </div>
                                @else
                                    <input type="{{ in_array($type, ['url', 'number', 'date', 'datetime-local'], true) ? $type : 'text' }}"
                                           id="meta_{{ $key }}"
                                           name="meta[{{ $key }}]"
                                           class="form-control @error("meta.{$key}") is-invalid @enderror"
                                           value="{{ $value }}"
                                           placeholder="{{ $placeholder }}">
                                @endif

                                @if($help)
                                    <div class="form-text">{!! $help !!}</div>
                                @endif
                                @error("meta.{$key}")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center my-3">
        <div>
            <button type="submit" class="btn btn-danger">{{ isset($post) ? 'Update Post' : 'Create Post' }}</button>
            <a href="{{ route('sitemanager.boards.posts.index', $board) }}" class="btn btn-secondary ms-2">Cancel</a>
        </div>
        @if(isset($post))
            <button type="submit" formaction="{{ route('sitemanager.boards.posts.destroy', [$board, $post->id]) }}"
                    formmethod="POST" class="btn btn-outline-danger"
                    onclick="event.preventDefault(); if(confirm('Delete this post?')) { const f=this.form; f.querySelector('[name=_method]').value='DELETE'; f.action=this.getAttribute('formaction'); f.submit(); }">
                <i class="bi bi-trash"></i> Delete
            </button>
        @endif
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.flatpickr) {
        flatpickr('#published_at', { enableTime: true, dateFormat: 'Y-m-d H:i' });
    }

    const memberSelect = document.getElementById('author_member_select');
    const authorName = document.getElementById('author_name');
    memberSelect?.addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        if (selected && selected.dataset.authorName && authorName) {
            authorName.value = selected.dataset.authorName;
        }
    });
});
</script>
@endpush
