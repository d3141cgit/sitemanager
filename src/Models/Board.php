<?php

namespace SiteManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 */
class Board extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id',
        'slug',
        'name',
        'skin',
        'posts_per_page',
        'categories',
        'settings',
        'status',
    ];

    protected $casts = [
        'categories' => 'array',
        'settings' => 'array',
        'posts_per_page' => 'integer',
    ];

    /**
     * 연결된 메뉴
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * 게시글 테이블명 반환
     */
    public function getPostsTableAttribute(): string
    {
        return "board_posts_{$this->slug}";
    }

    /**
     * 댓글 테이블명 반환
     */
    public function getCommentsTableAttribute(): string
    {
        return "board_comments_{$this->slug}";
    }

    /**
     * 게시판 URL 생성
     */
    public function getUrlAttribute(): string
    {
        return route('board.index', $this->slug);
    }

    /**
     * 카테고리 목록 반환
     */
    public function getCategoryOptions(): array
    {
        return $this->categories ?? [];
    }

    /**
     * 설정값 가져오기
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * 설정값 업데이트
     */
    public function updateSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    /**
     * 카테고리 사용 여부
     */
    public function usesCategories(): bool
    {
        return $this->getSetting('useCategories', false);
    }

    /**
     * 파일 업로드 사용 여부 (기본 체크)
     */
    public function usesFiles(): bool
    {
        return $this->getSetting('allowFileUpload', false);
    }

    /**
     * 댓글 사용 여부
     */
    public function usesComments(): bool
    {
        return $this->getSetting('allow_comments', true);
    }

    /**
     * 태그 사용 여부
     */
    public function usesTags(): bool
    {
        return $this->getSetting('useTags', false);
    }

    /**
     * 파일 업로드 허용 여부
     */
    public function allowsFileUpload(): bool
    {
        // 게시판 설정에서 파일 업로드 허용 여부 확인
        if (!$this->getSetting('allowFileUpload', false)) {
            return false;
        }
        
        // 메뉴 권한에서 uploadFiles 권한 확인
        if ($this->menu && !can('uploadFiles', $this->menu)) {
            return false;
        }
        
        return true;
    }

    /**
     * 허용 파일 확장자 목록
     */
    public function getAllowedFileTypes(): array
    {
        $default = config('sitemanager.board.allowed_extensions');
        $allowed = $this->getSetting('allowed_file_types', implode(',', $default));
        
        if (is_string($allowed)) {
            return array_map('trim', explode(',', $allowed));
        }
        
        return is_array($allowed) ? $allowed : $default;
    }

    /**
     * 최대 파일 크기 (KB)
     */
    public function getMaxFileSize(): int
    {
        return $this->getSetting('max_file_size', 2048);
    }

    /**
     * 파일당 최대 개수
     */
    public function getMaxFilesPerPost(): int
    {
        return $this->getSetting('max_files_per_post', 5);
    }

    /**
     * 총 게시물 수 계산
     */
    public function getPostsCount(): int
    {
        $postsTable = "board_posts_{$this->slug}";
        
        // 테이블이 존재하는지 확인
        if (!Schema::hasTable($postsTable)) {
            return 0;
        }
        
        return DB::table($postsTable)
            ->where('status', 'published')
            ->count();
    }

    /**
     * 총 댓글 수 계산
     */
    public function getCommentsCount(): int
    {
        $commentsTable = "board_comments_{$this->slug}";
        
        // 테이블이 존재하는지 확인
        if (!Schema::hasTable($commentsTable)) {
            return 0;
        }
        
        return DB::table($commentsTable)
            ->where('status', 'approved')
            ->count();
    }

    /**
     * 카테고리별 게시물 수 반환
     */
    public function getCategoryCounts(): array
    {
        $postsTable = "board_posts_{$this->slug}";
        
        // 테이블이 존재하는지 확인
        if (!Schema::hasTable($postsTable)) {
            return [];
        }
        
        // 카테고리별 게시물 수 집계
        $counts = DB::table($postsTable)
            ->select('category', DB::raw('COUNT(*) as count'))
            ->where('status', 'published')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();
            
        return $counts;
    }

    /**
     * 특정 카테고리의 게시물 수 반환
     */
    public function getCategoryCount(string $category): int
    {
        $postsTable = "board_posts_{$this->slug}";
        
        // 테이블이 존재하는지 확인
        if (!Schema::hasTable($postsTable)) {
            return 0;
        }
        
        return DB::table($postsTable)
            ->where('status', 'published')
            ->where('category', $category)
            ->count();
    }
}
