<?php

namespace SiteManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

abstract class BoardComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'post_id',
        'parent_id',
        'member_id',
        'author_name',
        'author_email',
        'content',
        'status',
        'ip_address',
        'user_agent',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // 댓글 생성 시
        static::created(function ($comment) {
            if ($comment->post && $comment->status === 'approved') {
                $comment->post->updateCommentCount();
            }
        });

        // 댓글 업데이트 시 (상태 변경 등)
        static::updated(function ($comment) {
            if ($comment->post && $comment->wasChanged('status')) {
                $comment->post->updateCommentCount();
            }
        });

        // 댓글 소프트 삭제 시
        static::deleted(function ($comment) {
            if ($comment->post) {
                $comment->post->updateCommentCount();
            }
        });

        // 댓글 복원 시
        static::restored(function ($comment) {
            if ($comment->post) {
                $comment->post->updateCommentCount();
            }
        });

        // 댓글 완전 삭제 시
        static::forceDeleted(function ($comment) {
            // forceDeleted 이벤트에서는 post 관계가 이미 끊어져 있을 수 있으므로
            // post_id를 직접 사용해서 게시글을 찾아 업데이트
            if ($comment->post_id) {
                $postModelClass = 'BoardPost' . \Illuminate\Support\Str::studly(
                    substr(static::class, strlen('BoardComment'))
                );
                if (class_exists($postModelClass)) {
                    $post = $postModelClass::find($comment->post_id);
                    if ($post) {
                        $post->updateCommentCount();
                    }
                }
            }
        });
    }

    /**
     * 동적 모델 생성
     */
    public static function forBoard(string $slug): string
    {
        $className = 'BoardComment' . Str::studly($slug);
        
        if (!class_exists($className)) {
            eval("
                class {$className} extends SiteManager\\Models\\BoardComment {
                    protected \$table = 'board_comments_{$slug}';
                    
                    public function post(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo {
                        return \$this->belongsTo(SiteManager\\Models\\BoardPost::forBoard('{$slug}'), 'post_id');
                    }
                }
            ");
        }
        
        return $className;
    }

    /**
     * 게시글 (추상 - 하위 클래스에서 구현)
     */
    abstract public function post(): BelongsTo;

    /**
     * 작성자
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * 부모 댓글
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * 자식 댓글들
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    /**
     * 모든 자손 댓글들 (재귀적)
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * 승인된 댓글만
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * 최상위 댓글만 (대댓글 제외)
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * 특정 게시글의 댓글들
     */
    public function scopeForPost($query, int $postId)
    {
        return $query->where('post_id', $postId);
    }

    /**
     * 작성자명 반환
     */
    public function getAuthorAttribute(): string
    {
        // return $this->member ? $this->member->name : ($this->author_name ?? 'Anonymous');
        return $this->author_name ?? $this->member?->name ?? 'Anonymous';
    }

    /**
     * 작성자 프로필 사진 URL 반환
     */
    public function getAuthorProfilePhotoAttribute(): ?string
    {
        return $this->member ? $this->member->profile_photo_url : null;
    }

    /**
     * 댓글 깊이 계산
     */
    public function getDepthAttribute(): int
    {
        $depth = 0;
        $parent = $this->parent;
        
        while ($parent) {
            $depth++;
            $parent = $parent->parent;
        }
        
        return $depth;
    }

    /**
     * 댓글이 수정되었는지 확인
     */
    public function getIsEditedAttribute(): bool
    {
        return $this->created_at->diffInSeconds($this->updated_at) > 60;
    }

    /**
     * 자식 댓글들 개수
     */
    public function getChildrenCountAttribute(): int
    {
        return $this->children()->count();
    }

    /**
     * 모든 자손 댓글들 개수 (재귀적)
     */
    public function getDescendantsCountAttribute(): int
    {
        $count = $this->children()->count();
        
        foreach ($this->children as $child) {
            $count += $child->descendants_count;
        }
        
        return $count;
    }

    /**
     * 승인 가능 여부 체크
     */
    public function canApprove(): bool
    {
        // 이미 승인된 댓글은 승인 불가
        if ($this->status === 'approved') {
            return false;
        }
        
        // 부모 댓글이 있고 부모가 승인되지 않은 경우 승인 불가
        if ($this->parent_id && $this->parent && $this->parent->status !== 'approved') {
            return false;
        }
        
        return true;
    }

    /**
     * 삭제 가능 여부 체크
     */
    public function canDelete(): bool
    {
        // 자식 댓글이 있으면 삭제 불가
        return $this->children()->count() === 0;
    }

    /**
     * 복원 가능 여부 체크
     */
    public function canRestore(): bool
    {
        // 삭제된 상태가 아니면 복원 불가
        if (!$this->trashed()) {
            return false;
        }
        
        // 부모 댓글이 있고 부모가 삭제된 경우 복원 불가
        if ($this->parent_id) {
            $parent = static::withTrashed()->find($this->parent_id);
            if ($parent && $parent->trashed()) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * 완전 삭제 가능 여부 체크
     */
    public function canForceDelete(): bool
    {
        // 자식 댓글이 있으면 완전 삭제 불가 (삭제된 자식 포함)
        return static::withTrashed()->where('parent_id', $this->id)->count() === 0;
    }

    /**
     * 액션별 가능 여부와 이유 반환
     */
    public function getActionAvailability(): array
    {
        return [
            'approve' => [
                'can' => $this->canApprove(),
                'reason' => !$this->canApprove() ? 
                    ($this->status === 'approved' ? 'Already approved' : 'Parent comment must be approved first') : null
            ],
            'delete' => [
                'can' => $this->canDelete(),
                'reason' => !$this->canDelete() ? 'Cannot delete comment with replies' : null
            ],
            'restore' => [
                'can' => $this->canRestore(),
                'reason' => !$this->canRestore() ? 
                    (!$this->trashed() ? 'Comment is not deleted' : 'Parent comment must be restored first') : null
            ],
            'force_delete' => [
                'can' => $this->canForceDelete(),
                'reason' => !$this->canForceDelete() ? 'Cannot permanently delete comment with replies' : null
            ]
        ];
    }
}
