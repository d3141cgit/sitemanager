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
        'options',
        'view_count',
        'comment_count',
        'file_count',
        'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
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
        return $query->where('options', 'like', '%is_notice%');
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
     * SEO용 슬러그 생성 (한글 지원)
     */
    public function generateSlug(): string
    {
        $title = $this->title;
        
        // 1. 한글을 로마자로 변환 (기본적인 음성학적 변환)
        $transliterated = $this->transliterateKorean($title);
        
        // 2. Laravel의 Str::slug() 적용
        $slug = Str::slug($transliterated);
        
        // 3. 빈 문자열이면 ID 기반으로 생성
        if (empty($slug)) {
            $slug = 'post-' . ($this->id ?: time());
        }
        
        // 4. 중복 체크 및 번호 추가
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * 한글을 로마자로 변환
     */
    private function transliterateKorean(string $text): string
    {
        // 한글 자음/모음 매핑 테이블
        $consonants = [
            'ㄱ' => 'g', 'ㄲ' => 'kk', 'ㄴ' => 'n', 'ㄷ' => 'd', 'ㄸ' => 'tt',
            'ㄹ' => 'r', 'ㅁ' => 'm', 'ㅂ' => 'b', 'ㅃ' => 'pp', 'ㅅ' => 's',
            'ㅆ' => 'ss', 'ㅇ' => '', 'ㅈ' => 'j', 'ㅉ' => 'jj', 'ㅊ' => 'ch',
            'ㅋ' => 'k', 'ㅌ' => 't', 'ㅍ' => 'p', 'ㅎ' => 'h'
        ];
        
        $vowels = [
            'ㅏ' => 'a', 'ㅐ' => 'ae', 'ㅑ' => 'ya', 'ㅒ' => 'yae', 'ㅓ' => 'eo',
            'ㅔ' => 'e', 'ㅕ' => 'yeo', 'ㅖ' => 'ye', 'ㅗ' => 'o', 'ㅘ' => 'wa',
            'ㅙ' => 'wae', 'ㅚ' => 'oe', 'ㅛ' => 'yo', 'ㅜ' => 'u', 'ㅝ' => 'wo',
            'ㅞ' => 'we', 'ㅟ' => 'wi', 'ㅠ' => 'yu', 'ㅡ' => 'eu', 'ㅢ' => 'ui',
            'ㅣ' => 'i'
        ];
        
        $result = '';
        $length = mb_strlen($text);
        
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1);
            $unicode = mb_ord($char);
            
            // 한글 완성형 범위 확인 (가-힣)
            if ($unicode >= 0xAC00 && $unicode <= 0xD7A3) {
                // 한글 분해
                $base = $unicode - 0xAC00;
                $cho = intval($base / 588); // 초성
                $jung = intval(($base % 588) / 28); // 중성
                $jong = $base % 28; // 종성
                
                // 초성 변환
                $choList = ['ㄱ','ㄲ','ㄴ','ㄷ','ㄸ','ㄹ','ㅁ','ㅂ','ㅃ','ㅅ','ㅆ','ㅇ','ㅈ','ㅉ','ㅊ','ㅋ','ㅌ','ㅍ','ㅎ'];
                $jungList = ['ㅏ','ㅐ','ㅑ','ㅒ','ㅓ','ㅔ','ㅕ','ㅖ','ㅗ','ㅘ','ㅙ','ㅚ','ㅛ','ㅜ','ㅝ','ㅞ','ㅟ','ㅠ','ㅡ','ㅢ','ㅣ'];
                $jongList = ['','ㄱ','ㄲ','ㄱㅅ','ㄴ','ㄴㅈ','ㄴㅎ','ㄷ','ㄹ','ㄹㄱ','ㄹㅁ','ㄹㅂ','ㄹㅅ','ㄹㅌ','ㄹㅍ','ㄹㅎ','ㅁ','ㅂ','ㅂㅅ','ㅅ','ㅆ','ㅇ','ㅈ','ㅊ','ㅋ','ㅌ','ㅍ','ㅎ'];
                
                // 로마자 변환
                $result .= $consonants[$choList[$cho]] ?? '';
                $result .= $vowels[$jungList[$jung]] ?? '';
                if ($jong > 0) {
                    $jongChar = $jongList[$jong];
                    if (strlen($jongChar) > 3) { // 복합 종성
                        $result .= $consonants[mb_substr($jongChar, 0, 3)] ?? '';
                        $result .= $consonants[mb_substr($jongChar, 3, 3)] ?? '';
                    } else {
                        $result .= $consonants[$jongChar] ?? '';
                    }
                }
            } else {
                // 한글이 아닌 문자는 그대로 유지
                $result .= $char;
            }
        }
        
        return $result;
    }

    /**
     * 첫 번째 이미지 첨부파일 반환
     */
    public function getFirstImageAttribute()
    {
        return $this->attachments()
                    ->where(function($query) {
                        $query->where('category', 'image')
                              ->orWhere('mime_type', 'like', 'image/%');
                    })
                    ->orderBy('sort_order')
                    ->first();
    }

    /**
     * 첫 번째 이미지 URL 반환 (썸네일용)
     */
    public function getThumbnailAttribute(): ?string
    {
        $firstImage = $this->first_image;
        
        if (!$firstImage) {
            return null;
        }
        
        return $firstImage->preview_url;
    }

    /**
     * 옵션 배열 반환
     */
    public function getOptionsArrayAttribute(): array
    {
        if (empty($this->options)) {
            return [];
        }
        
        return explode('|', $this->options);
    }

    /**
     * 특정 옵션이 설정되어 있는지 확인
     */
    public function hasOption(string $option): bool
    {
        return in_array($option, $this->options_array);
    }

    /**
     * 공지사항 여부 (하위 호환성)
     */
    public function getIsNoticeAttribute(): bool
    {
        return $this->hasOption('is_notice');
    }

    /**
     * 이미지 표시 여부
     */
    public function getShowImageAttribute(): bool
    {
        return $this->hasOption('show_image');
    }

    /**
     * 들여쓰기 없음 여부
     */
    public function getNoIndentAttribute(): bool
    {
        return $this->hasOption('no_indent');
    }

    /**
     * 옵션 추가
     */
    public function addOption(string $option): void
    {
        $options = $this->options_array;
        
        if (!in_array($option, $options)) {
            $options[] = $option;
            $this->options = implode('|', $options);
        }
    }

    /**
     * 옵션 제거
     */
    public function removeOption(string $option): void
    {
        $options = $this->options_array;
        $filteredOptions = array_filter($options, fn($opt) => $opt !== $option);
        
        $this->options = empty($filteredOptions) ? null : implode('|', $filteredOptions);
    }
}
