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
        'author_email',
        'author_password',
        'title',
        'content',
        'content_type',
        'excerpt',
        'slug',
        'category',
        'tags',
        'status',
        'secret_password',
        'options',
        'meta',
        'view_count',
        'comment_count',
        'file_count',
        'like_count',
        'published_at',
        'created_at',
        'updated_at',
        'email_verification_token',
        'email_verified_at'
    ];

    protected $casts = [
        'tags' => 'array',
        'meta' => 'array',
        'view_count' => 'integer',
        'comment_count' => 'integer',
        'file_count' => 'integer',
        'like_count' => 'integer',
        'email_verified_at' => 'datetime',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * 이메일 인증 여부를 확인하는 accessor
     */
    public function getIsVerifiedAttribute(): bool
    {
        // 회원은 항상 인증된 것으로 간주
        if ($this->member_id) {
            return true;
        }
        
        // 비회원은 email_verified_at 필드로 판단
        return !is_null($this->email_verified_at);
    }

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
        return $this->hasMany(BoardAttachment::class, 'attachment_id', 'id')
                    ->where('attachment_type', 'post')
                    ->where('board_slug', $this->getBoardSlug())
                    ->ordered();
    }

    /**
     * 에디터 이미지들
     */
    public function editorImages(): HasMany
    {
        return $this->hasMany(EditorImage::class, 'reference_id', 'id')
                    ->where('reference_type', 'board')
                    ->where('reference_slug', $this->getBoardSlug())
                    ->where('is_used', true)
                    ->orderBy('created_at');
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
        $count = $this->comments()
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->count();
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
        return $query->where('category', 'like', '%|' . $category . '|%');
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
     * 요약 생성 (자동)
     * excerpt가 이미 있으면 반환, 없으면 content로부터 자동 생성
     */
    public function generateExcerpt(int $length = 200): string
    {
        // excerpt가 이미 있으면 그대로 반환
        if ($this->excerpt) {
            return $this->excerpt;
        }

        // content가 비어있으면 빈 문자열 반환
        if (empty($this->content)) {
            return '';
        }

        // 새로운 extractExcerpt 메서드 사용
        return self::extractExcerpt($this->content, $length);
    }

    /**
     * HTML 컨텐츠에서 excerpt 추출 (정적 메서드)
     * 
     * @param string $content HTML 컨텐츠 또는 일반 텍스트
     * @param int $length 최대 길이 (기본 200자)
     * @return string 정리된 excerpt
     */
    public static function extractExcerpt(string $content, int $length = 200): string
    {
        // HTML 태그 제거
        $text = strip_tags($content);
        
        // HTML 엔티티 디코딩 및 정리
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // 연속된 공백을 하나로 통합
        $text = preg_replace('/\s+/', ' ', $text);
        
        // 앞뒤 공백 제거
        $text = trim($text);
        
        // 빈 문자열이면 그대로 반환
        if (empty($text)) {
            return '';
        }
        
        // 지정된 길이로 자르기
        $excerpt = mb_substr($text, 0, $length);
        
        // 문장이 중간에 끊기지 않도록 마지막 마침표나 공백에서 자르기
        if (mb_strlen($text) > $length) {
            // 마지막 마침표 찾기 (최소 100자 이상인 경우만)
            $lastPeriod = mb_strrpos($excerpt, '.');
            $lastSpace = mb_strrpos($excerpt, ' ');
            
            if ($lastPeriod !== false && $lastPeriod > $length * 0.5) {
                // 마침표가 있고 중간 이상 위치에 있으면 마침표까지만
                $excerpt = mb_substr($excerpt, 0, $lastPeriod + 1);
            } elseif ($lastSpace !== false && $lastSpace > $length * 0.5) {
                // 공백이 있고 중간 이상 위치에 있으면 공백까지만 + ...
                $excerpt = mb_substr($excerpt, 0, $lastSpace) . '...';
            } else {
                // 적절한 끊을 곳이 없으면 그냥 자르고 ... 추가
                $excerpt .= '...';
            }
        }
        
        return $excerpt;
    }

    /**
     * 제목에서 slug 추출 (정적 메서드)
     * 
     * @param string $title 게시글 제목
     * @param string|null $boardSlug 게시판 slug (중복 체크용)
     * @param int|null $excludeId 제외할 게시글 ID (수정 시)
     * @param bool $englishOnly 영어만 추출 (기본값: false)
     * @return string 생성된 slug
     */
    public static function extractSlug(string $title, ?string $boardSlug = null, ?int $excludeId = null, bool $englishOnly = false): string
    {
        // 한글과 영문을 모두 지원하는 slug 생성
        $slug = mb_strtolower($title);
        
        if ($englishOnly) {
            // 영어, 숫자, 공백, 하이픈만 허용 (한글 제거)
            $slug = preg_replace('/[^a-z0-9\s-]/u', '', $slug);
        } else {
            // 특수문자 제거 (한글, 영문, 숫자, 공백, 하이픈만 허용)
            $slug = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $slug);
        }
        
        // 공백을 하이픈으로 변환
        $slug = preg_replace('/\s+/', '-', trim($slug));
        
        // 연속된 하이픈 제거
        $slug = preg_replace('/-+/', '-', $slug);
        
        // 앞뒤 하이픈 제거
        $slug = trim($slug, '-');
        
        // 빈 문자열이면 시간 기반으로 생성
        if (empty($slug)) {
            $slug = 'post-' . time();
        }
        
        // 중복 체크 (boardSlug가 제공된 경우에만)
        if ($boardSlug) {
            $postClass = self::forBoard($boardSlug);
            $originalSlug = $slug;
            $counter = 1;

            $query = $postClass::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            while ($query->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
                
                $query = $postClass::where('slug', $slug);
                if ($excludeId) {
                    $query->where('id', '!=', $excludeId);
                }
            }
        }
        
        return $slug;
    }

    /**
     * 현재 게시글의 excerpt를 content로부터 생성하여 저장
     * 
     * @param int $length 최대 길이 (기본 200자)
     * @return bool 성공 여부
     */
    public function updateExcerptFromContent(int $length = 200): bool
    {
        if (empty($this->content)) {
            return false;
        }
        
        $excerpt = self::extractExcerpt($this->content, $length);
        
        if (empty($excerpt)) {
            return false;
        }
        
        return $this->update(['excerpt' => $excerpt]);
    }

    /**
     * SEO용 슬러그 생성 (한글 지원)
     * slug가 이미 있으면 반환, 없으면 title로부터 자동 생성
     */
    public function generateSlug(): string
    {
        // slug가 이미 있으면 그대로 반환
        if ($this->slug) {
            return $this->slug;
        }

        // title이 비어있으면 ID 기반으로 생성
        if (empty($this->title)) {
            return 'post-' . ($this->id ?: time());
        }

        // 게시판 slug 추출
        $boardSlug = $this->getBoardSlug();
        
        // 새로운 extractSlug 메서드 사용
        return self::extractSlug($this->title, $boardSlug, $this->id);
    }

    /**
     * SEO용 슬러그 생성 (영문 변환 방식 - 백업)
     */
    public function generateSlugTransliterated(): string
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
                    ->Where('mime_type', 'like', 'image/%')
                    ->orderBy('sort_order')
                    ->first();
    }

    /**
     * 첫 번째 이미지 URL 반환 (썸네일용)
     */
    public function getThumbnailAttribute(): ?string
    {
        $firstImage = $this->first_image;
        
        if ($firstImage) {
            return $firstImage->preview_url;
        }
        
        // 첨부파일에 이미지가 없으면 에디터 이미지에서 첫 번째 이미지 사용
        $firstEditorImage = $this->editorImages()->first();
        if ($firstEditorImage) {
            return $firstEditorImage->url;
        }
        
        return null;
    }

    /**
     * 헤더 이미지 정보 반환 (category가 'header'인 이미지 우선)
     */
    public function getHeaderImageAttribute(): ?array
    {
        // 1. category가 'header'인 첨부파일 이미지 찾기
        $headerImage = $this->attachments()
                            ->where('mime_type', 'like', 'image/%')
                            ->where('category', 'header')
                            ->orderBy('sort_order')
                            ->first();
        
        if ($headerImage) {
            return [
                'url' => $headerImage->preview_url,
                'original_url' => $headerImage->url,
                'filename' => $headerImage->filename,
                'description' => $headerImage->description,
                'alt' => $headerImage->description ?: $headerImage->filename,
            ];
        }
        
        // 2. header 카테고리가 없으면 첫 번째 이미지 첨부파일 사용 - category <> thumbnail
        $firstImage = $this->attachments()
                            ->where('mime_type', 'like', 'image/%')
                            ->where(function($query) {
                                $query->whereNull('category')
                                      ->orWhere('category', '<>', 'thumbnail');
                            })
                            ->orderBy('sort_order')
                            ->first();
        // $firstImage = $this->first_image;
        
        if ($firstImage) {
            return [
                'url' => $firstImage->preview_url,
                'original_url' => $firstImage->url,
                'filename' => $firstImage->filename,
                'description' => $firstImage->description,
                'alt' => $firstImage->description ?: $firstImage->filename,
            ];
        }
        
        return null;
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

    public function getCategoriesAttribute(): array
    {
        if (empty($this->category)) {
            return [];
        }
        
        // 앞뒤 구분자 '|' 제거 후 분리
        $trimmed = trim($this->category, '|');
        return explode('|', $trimmed);
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

    /**
     * 비밀글인지 확인
     */
    public function isSecret(): bool
    {
        return !empty($this->secret_password);
    }

    /**
     * 비밀번호 확인
     */
    public function checkSecretPassword(string $password): bool
    {
        if (!$this->isSecret()) {
            return true; // 비밀글이 아니면 항상 통과
        }
        
        return password_verify($password, $this->secret_password);
    }

    /**
     * 비밀번호 설정 (해시화)
     */
    public function setSecretPassword(?string $password): void
    {
        $this->secret_password = $password ? password_hash($password, PASSWORD_DEFAULT) : null;
    }

    /**
     * 세션에서 비밀번호 확인 여부 체크
     */
    public function isPasswordVerified(): bool
    {
        if (!$this->isSecret()) {
            return true;
        }
        
        $userId = current_user_id() ?? 'guest';
        $sessionKey = "post_password_verified_{$this->id}_{$userId}";
        $sessionData = session($sessionKey);
        
        // 세션 데이터가 존재하고 유효한지 확인
        if ($sessionData && is_array($sessionData)) {
            return isset($sessionData['user_id']) && 
                   $sessionData['user_id'] === $userId &&
                   isset($sessionData['post_id']) && 
                   $sessionData['post_id'] === $this->id;
        }
        
        return false;
    }

    /**
     * 세션에 비밀번호 확인 상태 저장
     */
    public function markPasswordVerified(): void
    {
        if ($this->isSecret()) {
            $userId = current_user_id() ?? 'guest';
            $sessionKey = "post_password_verified_{$this->id}_{$userId}";
            session()->put($sessionKey, [
                'verified_at' => now(),
                'user_id' => $userId,
                'post_id' => $this->id
            ]);
        }
    }

    /**
     * 비밀글 접근 권한 확인 (비밀번호 확인 + 작성자 확인)
     */
    public function canAccess(?int $userId = null): bool
    {
        if (!$this->isSecret()) {
            return true;
        }

        // 작성자는 항상 접근 가능
        if ($userId && $this->member_id === $userId) {
            return true;
        }

        // 관리자는 항상 접근 가능 (선택사항)
        // if ($userId && User::find($userId)?->isAdmin()) {
        //     return true;
        // }

        // 세션에서 비밀번호 확인 여부 체크
        return $this->isPasswordVerified();
    }

    /**
     * 미리보기 콘텐츠 (비밀글은 제한)
     */
    public function getPreviewContentAttribute(): string
    {
        if ($this->isSecret() && !$this->isPasswordVerified()) {
            return '🔒 This post is private.';
        }
        
        return $this->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($this->content), 200);
    }

        /**
     * 권한 체크 Helper 메서드들
     * 실시간으로 권한을 계산하여 반환
     */

    /**
     * 게시글 수정 권한 확인
     */
    public function canEdit(): bool
    {
        $board = $this->getBoard();
        if (!$board || !$board->menu_id) return false;
        
        $user = current_user();
        $canManage = can('manage', $board);
        $canWrite = can('write', $board);
        $isAuthor = $user && $this->member_id && $this->member_id === $user->id;
        
        return $canManage || ($isAuthor && $canWrite);
    }

    /**
     * 게시글 삭제 권한 확인
     */
    public function canDelete(): bool
    {
        $board = $this->getBoard();
        if (!$board || !$board->menu_id) return false;
        
        $user = current_user();
        $canManage = can('manage', $board);
        $canWrite = can('write', $board);
        $isAuthor = $user && $this->member_id && $this->member_id === $user->id;
        
        return $canManage || ($isAuthor && $canWrite);
    }

    /**
     * 댓글 작성 권한 확인
     */
    public function canWriteComments(): bool
    {
        $board = $this->getBoard();
        if (!$board || !$board->menu_id) return false;
        
        // 게시판 설정에서 댓글 허용 여부 확인
        if (!$board->getSetting('allow_comments', true)) {
            return false;
        }
        
        return can('writeComments', $board);
    }

    /**
     * 파일 업로드 권한 확인
     */
    public function canUploadFiles(): bool
    {
        $board = $this->getBoard();
        if (!$board || !$board->menu_id) return false;
        
        // 게시판 설정에서 파일 업로드 허용 여부 확인
        if (!$board->getSetting('allow_file_upload', false)) {
            return false;
        }
        
        return can('uploadFiles', $board);
    }

    /**
     * 댓글에 파일 업로드 권한 확인
     */
    public function canUploadCommentFiles(): bool
    {
        $board = $this->getBoard();
        if (!$board || !$board->menu_id) return false;
        
        // 게시판 설정에서 댓글 허용 여부 확인
        if (!$board->getSetting('allow_comments', true)) {
            return false;
        }
        
        return can('uploadCommentFiles', $board);
    }

    /**
     * 특정 액션에 대한 권한 확인
     */
    public function hasPermission(string $action): bool
    {
        return match($action) {
            'canEdit' => $this->canEdit(),
            'canDelete' => $this->canDelete(),
            'canWriteComments' => $this->canWriteComments(),
            'canUploadFiles' => $this->canUploadFiles(),
            'canUploadCommentFiles' => $this->canUploadCommentFiles(),
            default => false,
        };
    }

    /**
     * 권한이 설정되어 있는지 확인 (하위 호환성을 위해 유지)
     */
    public function hasPermissions(): bool
    {
        return true; // 실시간 계산 방식에서는 항상 true
    }

    /**
     * 게시판 정보를 가져오는 helper 메서드
     */
    protected function getBoard()
    {
        // 이미 로드된 관계가 있으면 사용
        if ($this->relationLoaded('board')) {
            return $this->board;
        }

        // 게시판 slug를 통해 Board 모델 조회
        $boardSlug = $this->getBoardSlug();
        return \SiteManager\Models\Board::where('slug', $boardSlug)->first();
    }

}
