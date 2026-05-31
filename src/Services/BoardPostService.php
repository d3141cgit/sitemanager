<?php

namespace SiteManager\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use SiteManager\Jobs\CleanupUnusedEditorImagesJob;
use SiteManager\Models\Board;
use SiteManager\Models\BoardAttachment;
use SiteManager\Models\BoardPost;
use SiteManager\Models\EditorImage;

class BoardPostService
{
    public function __construct(
        private FileUploadService $fileUploadService
    ) {}

    public function validationRules(Board $board, bool $isUpdate = false): array
    {
        $rules = [
            'title' => 'required|string|max:200',
            'content' => 'nullable|string',
            'slug' => 'nullable|string|max:200',
            'excerpt' => 'nullable|string|max:1000',
            'category' => 'nullable|string|max:50',
            'categories' => 'nullable|array',
            'categories.*' => 'nullable|string|max:50',
            'tags' => 'nullable|string',
            'status' => 'nullable|in:published,draft,private',
            'secret_password' => 'nullable|string|min:4|max:20',
            'options' => 'nullable|array',
            'options_present' => 'nullable|boolean',
            'meta' => 'nullable|array',
            'member_id' => 'nullable|integer|exists:members,id',
            'author_name' => 'nullable|string|max:50',
            'author_email' => 'nullable|email|max:100',
            'published_at' => 'nullable|date',
            'files.*' => 'nullable|file|max:' . ($board->getSetting('max_file_size', 2048)),
            'file_names.*' => 'nullable|string|max:255',
            'file_descriptions.*' => 'nullable|string|max:500',
            'file_categories.*' => 'nullable|string|max:50',
            'existing_file_names.*' => 'nullable|string|max:255',
            'existing_file_descriptions.*' => 'nullable|string|max:500',
            'existing_file_categories.*' => 'nullable|string|max:50',
            'removed_files' => 'nullable|string',
        ];

        if ($isUpdate) {
            $rules['remove_secret_password'] = 'nullable|boolean';
        }

        foreach ($board->getPostFieldDefinitions() as $key => $field) {
            if (! is_string($key)) {
                continue;
            }

            $rules["meta.{$key}"] = data_get($field, 'rules', 'nullable|string|max:1000');
        }

        return $rules;
    }

    public function postsQuery(Board $board)
    {
        $postClass = BoardPost::forBoard($board->slug);

        return $postClass::with(['member', 'attachments']);
    }

    public function getPost(Board $board, $id)
    {
        $postClass = BoardPost::forBoard($board->slug);

        return $postClass::with(['member', 'attachments'])->findOrFail($id);
    }

    public function createFromRequest(Board $board, Request $request)
    {
        $data = $request->validate($this->validationRules($board));

        return DB::transaction(function () use ($board, $request, $data) {
            $post = $this->createPost($board, $data);

            $this->setSecretPassword($post, $data);
            $this->uploadAttachments($request, $board, $post);
            $this->syncEditorImages($post, null);

            return $post;
        });
    }

    public function updateFromRequest(Board $board, $post, Request $request)
    {
        $data = $request->validate($this->validationRules($board, true));

        return DB::transaction(function () use ($board, $post, $request, $data) {
            $post = $this->updatePost($board, $post, $data);

            $this->setSecretPassword($post, $data, $request->boolean('remove_secret_password'));
            $this->updateExistingAttachments($request, $post);
            $this->removeAttachments($request, $post);
            $this->uploadAttachments($request, $board, $post);
            $this->syncEditorImages($post, $post->id);

            return $post;
        });
    }

    public function deletePost(Board $board, $post): void
    {
        $post->delete();
        EditorImage::where('reference_type', 'board')
            ->where('reference_slug', $board->slug)
            ->where('reference_id', $post->id)
            ->update(['is_used' => false]);
    }

    public function createPost(Board $board, array $data)
    {
        $postClass = BoardPost::forBoard($board->slug);
        $user = current_user();

        $post = $postClass::create([
            'board_id' => $board->id,
            'member_id' => $data['member_id'] ?? current_user_id(),
            'author_name' => $data['author_name'] ?? ($user->name ?? 'Admin'),
            'author_email' => $data['author_email'] ?? ($user->email ?? null),
            'title' => $data['title'],
            'content' => $data['content'] ?? null,
            'content_type' => 'html',
            'category' => $this->normalizeCategory($data),
            'tags' => $this->normalizeTags($data['tags'] ?? null),
            'status' => $data['status'] ?? 'published',
            'options' => $this->normalizeOptions($board, $data),
            'meta' => $this->normalizeMeta($board, $data),
            'published_at' => $data['published_at'] ?? now(),
        ]);

        $post->slug = !empty($data['slug']) ? $data['slug'] : $post->generateSlug();
        $post->excerpt = !empty($data['excerpt']) ? $data['excerpt'] : $post->generateExcerpt();
        $post->save();

        return $post;
    }

    public function updatePost(Board $board, $post, array $data)
    {
        $post->update([
            'member_id' => $data['member_id'] ?? $post->member_id,
            'author_name' => $data['author_name'] ?? $post->author_name,
            'author_email' => $data['author_email'] ?? $post->author_email,
            'title' => $data['title'],
            'content' => $data['content'] ?? null,
            'category' => $this->normalizeCategory($data),
            'tags' => $this->normalizeTags($data['tags'] ?? null),
            'status' => $data['status'] ?? $post->status,
            'options' => $this->normalizeOptions($board, $data, $post),
            'meta' => $this->normalizeMeta($board, $data, $post),
            'published_at' => $data['published_at'] ?? $post->published_at,
        ]);

        if (!empty($data['slug'])) {
            $post->slug = $data['slug'];
        } elseif ($post->wasChanged('title')) {
            $post->slug = $post->generateSlug();
        }

        if (!empty($data['excerpt'])) {
            $post->excerpt = $data['excerpt'];
        } elseif ($post->wasChanged('title') || $post->wasChanged('content')) {
            $post->excerpt = $post->generateExcerpt();
        }

        $post->save();

        return $post;
    }

    public function decodedOptions(?string $options): array
    {
        if (blank($options)) {
            return [];
        }

        $decoded = json_decode((string) $options, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return collect(explode('|', (string) $options))
            ->filter()
            ->mapWithKeys(fn ($key) => [$key => true])
            ->all();
    }

    public function prettyOptions(?string $options): string
    {
        $decoded = json_decode((string) $options, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return '';
    }

    public function decodedMeta($post): array
    {
        $meta = $post->meta ?? null;
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && filled($meta)) {
            $decoded = json_decode($meta, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function normalizeCategory(array $data): ?string
    {
        if (!empty($data['categories'])) {
            return '|' . implode('|', array_filter($data['categories'])) . '|';
        }

        if (!empty($data['category'])) {
            return '|' . trim($data['category'], '|') . '|';
        }

        return null;
    }

    private function normalizeTags(?string $tags): ?array
    {
        if (blank($tags)) {
            return null;
        }

        return collect(explode(',', $tags))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeOptions(Board $board, array $data, $post = null): ?string
    {
        if (array_key_exists('options', $data) || array_key_exists('options_present', $data)) {
            $options = $data['options'] ?? [];
            $activeOptions = array_keys(array_filter($options, fn ($value) => !empty($value) && $value !== '0'));

            return empty($activeOptions) ? null : implode('|', $activeOptions);
        }

        return $post->options ?? null;
    }

    private function normalizeMeta(Board $board, array $data, $post = null): ?array
    {
        if (! Schema::hasColumn($board->posts_table, 'meta')) {
            return null;
        }

        $submitted = $data['meta'] ?? [];
        if (! is_array($submitted)) {
            return $post ? $this->decodedMeta($post) : null;
        }

        $fields = $board->getPostFieldDefinitions();
        $keys = ! empty($fields) ? array_keys($fields) : array_keys($submitted);
        $meta = [];

        foreach ($keys as $key) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            $key = (string) $key;
            $value = $submitted[$key] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            if (filled($value)) {
                $meta[$key] = $value;
            }
        }

        return empty($meta) ? null : $meta;
    }

    private function setSecretPassword($post, array $data, bool $remove = false): void
    {
        if ($remove) {
            $post->secret_password = null;
            $post->save();
            return;
        }

        if (!empty($data['secret_password'])) {
            $post->setSecretPassword($data['secret_password']);
            $post->save();
        }
    }

    private function updateExistingAttachments(Request $request, $post): void
    {
        foreach ($request->input('existing_file_names', []) as $attachmentId => $originalName) {
            $attachment = $post->attachments()->find($attachmentId);
            if (!$attachment) {
                continue;
            }

            $attachment->update([
                'original_name' => $originalName ?: $attachment->original_name,
                'description' => $request->input("existing_file_descriptions.{$attachmentId}"),
                'category' => $request->input("existing_file_categories.{$attachmentId}"),
            ]);
        }
    }

    private function removeAttachments(Request $request, $post): void
    {
        $removed = collect(explode(',', (string) $request->input('removed_files', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->all();

        if (empty($removed)) {
            return;
        }

        foreach ($post->attachments()->whereIn('id', $removed)->get() as $attachment) {
            try {
                $this->fileUploadService->deleteFile($attachment->file_path);
            } catch (\Throwable $e) {
                Log::warning('Board attachment file delete failed', [
                    'attachment_id' => $attachment->id,
                    'file_path' => $attachment->file_path,
                    'error' => $e->getMessage(),
                ]);
            }

            $attachment->delete();
        }
    }

    private function uploadAttachments(Request $request, Board $board, $post): void
    {
        if (!$request->hasFile('files') || !$board->allowsFileUpload()) {
            return;
        }

        $files = array_filter((array) $request->file('files'));
        if (empty($files)) {
            return;
        }

        $currentFileCount = BoardAttachment::byPost($post->id, $board->slug)->count();
        $remainingSlots = $board->getMaxFilesPerPost() - $currentFileCount;
        if ($remainingSlots <= 0) {
            return;
        }

        $uploadedFiles = $this->fileUploadService->uploadBoardAttachments($files, $board->slug, $post->id, [
            'allowed_types' => $board->getAllowedFileTypes(),
            'max_size' => $board->getMaxFileSize(),
            'max_files' => $remainingSlots,
            'file_categories' => $board->getFileCategories(),
        ]);

        foreach ($uploadedFiles as $index => $fileInfo) {
            BoardAttachment::create([
                'attachment_id' => $post->id,
                'board_slug' => $board->slug,
                'attachment_type' => 'post',
                'filename' => $fileInfo['filename'],
                'original_name' => $request->input("file_names.{$index}") ?: $fileInfo['original_name'],
                'file_path' => $fileInfo['path'],
                'file_extension' => $fileInfo['file_extension'],
                'file_size' => $fileInfo['file_size'],
                'mime_type' => $fileInfo['mime_type'],
                'category' => $request->input("file_categories.{$index}") ?: $fileInfo['category'],
                'description' => $request->input("file_descriptions.{$index}"),
                'sort_order' => $currentFileCount + $index,
                'download_count' => 0,
            ]);
        }
    }

    private function syncEditorImages($post, ?int $previousReferenceId): void
    {
        $content = (string) $post->content;
        $usedFilenames = EditorImage::extractFilenamesFromContent($content);
        EditorImage::markAsUsedByContent($content, 'board', $post->board->slug, $post->id);
        EditorImage::markAsUnusedByContent($content, 'board', $post->board->slug, $post->id);

        CleanupUnusedEditorImagesJob::dispatch(
            'board',
            $post->board->slug,
            $previousReferenceId,
            $usedFilenames
        );
    }
}
