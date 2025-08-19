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
     * 동적 모델 생성
     */
    public static function forBoard(string $slug): string
    {
        $className = 'BoardComment' . Str::studly($slug);
        
        if (!class_exists($className)) {
            eval("
                class {$className} extends App\\Models\\BoardComment {
                    protected \$table = 'board_comments_{$slug}';
                    
                    public function post(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo {
                        return \$this->belongsTo(App\\Models\\BoardPost::forBoard('{$slug}'), 'post_id');
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
        return $this->member ? $this->member->name : ($this->author_name ?? '익명');
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
}
