<?php

namespace SiteManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

abstract class BoardPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'board_id',
        'member_id',
        'author_name',
        'title',
        'content',
        'content_type',
        'excerpt',
        'slug',
        'category',
        'tags',
        'status',
        'is_notice',
        'is_featured',
        'view_count',
        'comment_count',
        'file_count',
        'meta',
        'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'meta' => 'array',
        'is_notice' => 'boolean',
        'is_featured' => 'boolean',
        'view_count' => 'integer',
        'comment_count' => 'integer',
        'file_count' => 'integer',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * 동적 모델 생성
     */
    public static function forBoard(string $slug): string
    {
        $className = 'BoardPost' . Str::studly($slug);
        
        if (!class_exists($className)) {
            eval("
                class {$className} extends SiteManager\\Models\\BoardPost {
                    protected \$table = 'board_posts_{$slug}';
                    
                    public function comments() {
                        return \$this->hasMany(SiteManager\\Models\\BoardComment::forBoard('{$slug}'), 'post_id')
                            ->where('status', 'approved')
                            ->orderBy('created_at', 'desc');
                    }
                }
            ");
        }
        
        return $className;
    }

    /**
     * 게시판
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * 작성자
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * 댓글들 (추상 - 하위 클래스에서 구현)
     */
    abstract public function comments();

    /**
     * 첨부파일들
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(BoardAttachment::class, 'post_id', 'id')
                    ->where('board_slug', $this->getBoardSlug())
                    ->ordered();
    }

    /**
     * 게시판 slug 반환 (동적 모델을 위해)
     */
    protected function getBoardSlug(): string
    {
        // 테이블명에서 게시판 slug 추출 (board_posts_xxx -> xxx)
        $tableName = $this->getTable();
        return str_replace('board_posts_', '', $tableName);
    }

    /**
     * 조회수 증가
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * 댓글 수 업데이트
     */
    public function updateCommentCount(): void
    {
        $count = $this->comments()->count();
        $this->update(['comment_count' => $count]);
    }

    /**
     * 발행된 게시글만
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * 공지사항만
     */
    public function scopeNotices($query)
    {
        return $query->where('is_notice', true);
    }

    /**
     * 추천글만
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * 카테고리별
     */
    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * 전체 텍스트 검색
     */
    public function scopeSearch($query, string $keyword)
    {
        return $query->whereRaw('MATCH(title, content) AGAINST(? IN BOOLEAN MODE)', [$keyword]);
    }

    /**
     * URL 생성
     */
    public function getUrlAttribute(): string
    {
        $board = $this->board;
        if ($this->slug) {
            return route('board.show', [$board->slug, $this->slug]);
        }
        return route('board.show', [$board->slug, $this->id]);
    }

    /**
     * 작성자명 반환
     */
    public function getAuthorAttribute(): string
    {
        return $this->member ? $this->member->name : ($this->author_name ?? '익명');
    }

    /**
     * 요약 생성 (자동)
     */
    public function generateExcerpt(int $length = 200): string
    {
        if ($this->excerpt) {
            return $this->excerpt;
        }

        $content = strip_tags($this->content);
        return Str::limit($content, $length);
    }

    /**
     * SEO용 슬러그 생성
     */
    public function generateSlug(): string
    {
        $slug = Str::slug($this->title);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
