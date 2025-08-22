<?php

namespace SiteManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use SiteManager\Services\FileUploadService;
// use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Kalnoy\Nestedset\NodeTrait;

class Menu extends Model
{
    use HasFactory, NodeTrait;

    protected $fillable = [
        'section',
        'parent_id',
        'title',
        'description',
        'type',
        'target',
        'hidden',
        'permission',
        'images',
        '_lft',
        '_rgt',
        'depth',
    ];

    protected $casts = [
        'section' => 'integer',
        'hidden' => 'boolean',
        'permission' => 'integer',
        'images' => 'array',
    ];

    /**
     * 모델 부팅
     */
    protected static function boot()
    {
        parent::boot();

        // 저장 전에 linkable하지 않은 타입의 target을 null로 설정
        static::saving(function ($menu) {
            if (!$menu->isLinkable()) {
                $menu->target = null;
            }
        });
    }

    public function getSectionLabelAttribute(): string
    {
        // 해당 섹션의 루트 메뉴(depth=0)를 찾아서 그 제목을 라벨로 사용
        $rootMenu = static::where('section', $this->section)
                         ->where('depth', 0)
                         ->first();
        
        return $rootMenu ? $rootMenu->title : "섹션 {$this->section}";
    }

    // Nested Set 설정 - 섹션별로 독립적인 트리 구조
    public function getLftName()
    {
        return '_lft';
    }

    public function getRgtName()
    {
        return '_rgt';
    }

    public function getParentIdName()
    {
        return 'parent_id';
    }

    // 섹션별로 독립적인 트리를 만들기 위한 설정
    public function getScopeAttributes()
    {
        return ['section'];
    }

    // 섹션별 루트 노드들
    public function scopeRoots($query, $section = null)
    {
        $query = $query->whereNull('parent_id');
        
        if ($section !== null) {
            $query->where('section', $section);
        }
        
        return $query->orderBy('section')->orderBy('_lft');
    }

    // 기존 관계도 유지 (호환성)
    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('_lft');
    }

    /**
     * 메뉴 권한
     */
    public function menuPermissions()
    {
        return $this->hasMany(MenuPermission::class);
    }

    public function getChildrenAttribute()
    {
        return $this->children()->get();
    }

    /**
     * 이미지 URLs 접근자 - S3 또는 로컬 스토리지에 따라 적절한 URL 반환
     */
    public function getImageUrlsAttribute()
    {
        if (!$this->images || !is_array($this->images)) {
            return [];
        }

        $urls = [];
        foreach ($this->images as $category => $imageData) {
            if (isset($imageData['url'])) {
                $urls[$category] = FileUploadService::url($imageData['url']);
            }
        }

        return $urls;
    }

    /**
     * 특정 카테고리의 이미지 URL 가져오기
     */

    // 메뉴 트리 스코프
    public function scopeTree($query)
    {
        return $query->get()->toTree();
    }

    // 표시할 메뉴만 (숨김이 아닌 메뉴)
    public function scopeVisible($query)
    {
        return $query->where('hidden', false);
    }

    // 숨겨진 메뉴만
    public function scopeHidden($query)
    {
        return $query->where('hidden', true);
    }

    // 메뉴 타입별
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    // 섹션별 필터링
    public function scopeInSection($query, $section)
    {
        return $query->where('section', $section);
    }

    // 섹션별 트리 구조
    public function scopeSectionTree($query, $section)
    {
        return $query->where('section', $section)->get()->toTree();
    }

    // 실제 사용 중인 섹션 목록 가져오기
    public static function getUsedSections()
    {
        return self::distinct('section')->pluck('section')->sort()->values();
    }

    // 같은 섹션의 형제 메뉴들
    public function siblings()
    {
        return $this->where('section', $this->section)
                   ->where('parent_id', $this->parent_id)
                   ->where('id', '!=', $this->id);
    }
    
    /**
     * 하위 노드들 가져오기
     */
    public function getSubNodes()
    {
        if (!$this->_lft || !$this->_rgt) {
            return collect();
        }

        return self::where('section', $this->section)
                  ->where('_lft', '>', $this->_lft)
                  ->where('_rgt', '<', $this->_rgt)
                  ->orderBy('_lft')
                  ->get();
    }

    /**
     * 섹션의 nested set 구조 재구축
     */
    public function rebuildSectionNodes($section)
    {
        $rootMenus = self::where('section', $section)
                        ->whereNull('parent_id')
                        ->orderBy('id')
                        ->get();

        $left = 1;
        foreach ($rootMenus as $root) {
            $left = $this->rebuildNode($root, $left, 0);
        }
    }

    /**
     * 노드와 하위 노드들의 nested set 값 재계산
     */
    private function rebuildNode($node, $left, $depth)
    {
        $right = $left + 1;
        
        // 자식 메뉴들 가져오기
        $children = self::where('section', $node->section)
                       ->where('parent_id', $node->id)
                       ->orderBy('id')
                       ->get();

        foreach ($children as $child) {
            $right = $this->rebuildNode($child, $right, $depth + 1);
        }

        // 현재 노드 업데이트
        $node->update([
            '_lft' => $left,
            '_rgt' => $right,
            'depth' => $depth
        ]);

        return $right + 1;
    }

    /**
     * 전체 트리 구조 수정
     */
    public static function fixAllTrees()
    {
        $sections = self::distinct('section')->pluck('section');
        
        foreach ($sections as $section) {
            $menu = new self();
            $menu->rebuildSectionNodes($section);
        }
    }

    /*
     * ======================================
     * 메뉴 타입 관련 메서드들
     * ======================================
     */

    /**
     * 사용 가능한 메뉴 타입들을 가져옵니다.
     */
    public static function getAvailableTypes(): array
    {
        return config('menu.types', [
            'route' => 'Internal Route',
            'url' => 'External URL', 
            'text' => 'Text Only',
        ]);
    }

    /**
     * 링크 가능한 타입들을 가져옵니다.
     */
    public static function getLinkableTypes(): array
    {
        return config('menu.linkable_types', ['route', 'url']);
    }

    /**
     * 기본 메뉴 타입을 가져옵니다.
     */
    public static function getDefaultType(): string
    {
        return config('menu.default_type', 'text');
    }

    /**
     * 현재 메뉴가 링크 가능한 타입인지 확인합니다.
     */
    public function isLinkable(): bool
    {
        return in_array($this->type, self::getLinkableTypes());
    }

    /**
     * 타입의 라벨을 가져옵니다.
     */
    public function getTypeLabel(): string
    {
        $types = self::getAvailableTypes();
        return $types[$this->type] ?? $this->type;
    }

    /**
     * 타입이 유효한지 확인합니다.
     */
    public function isValidType(): bool
    {
        return array_key_exists($this->type, self::getAvailableTypes());
    }

    /*
     * ======================================
     * 이미지 관련 메서드들
     * ======================================
     */

    /**
     * 특정 타입의 이미지 URL을 가져옵니다.
     */
    public function getImageUrl(string $type): ?string
    {
        $images = $this->images ?? [];
        $imageData = $images[$type] ?? null;
        
        if (!$imageData) {
            return null;
        }
        
        // 새로운 구조 (category => ['url' => path, 'uploaded_at' => timestamp])
        if (is_array($imageData) && isset($imageData['url'])) {
            return FileUploadService::url($imageData['url']);
        }
        
        // 기존 구조 호환 (category => path)
        if (is_string($imageData)) {
            return FileUploadService::url($imageData);
        }
        
        return null;
    }

    /**
     * 썸네일 이미지 URL을 가져옵니다.
     */
    public function getThumbnailUrl(): ?string
    {
        return $this->getImageUrl('thumbnail');
    }

    /**
     * SEO 이미지 URL을 가져옵니다.
     */
    public function getSeoImageUrl(): ?string
    {
        return $this->getImageUrl('seo');
    }

    /**
     * 헤더 이미지 URL을 가져옵니다.
     */
    public function getHeaderImageUrl(): ?string
    {
        return $this->getImageUrl('header');
    }

    /**
     * 특정 타입의 이미지를 설정합니다.
     */
    public function setImage(string $type, ?string $url): void
    {
        $images = $this->images ?? [];
        
        if ($url) {
            $images[$type] = $url;
        } else {
            unset($images[$type]);
        }
        
        $this->images = empty($images) ? null : $images;
    }

    /**
     * 썸네일 이미지를 설정합니다.
     */
    public function setThumbnail(?string $url): void
    {
        $this->setImage('thumbnail', $url);
    }

    /**
     * SEO 이미지를 설정합니다.
     */
    public function setSeoImage(?string $url): void
    {
        $this->setImage('seo', $url);
    }

    /**
     * 헤더 이미지를 설정합니다.
     */
    public function setHeaderImage(?string $url): void
    {
        $this->setImage('header', $url);
    }

    /**
     * 모든 이미지를 제거합니다.
     */
    public function clearImages(): void
    {
        $this->images = null;
    }

    /**
     * 사용 가능한 이미지 타입들을 반환합니다.
     */
    public static function getImageTypes(): array
    {
        return [
            'thumbnail' => '썸네일',
            'seo' => 'SEO 이미지',
            'header' => '헤더 이미지',
            'banner' => '배너 이미지',
            'icon' => '아이콘',
        ];
    }

    /**
     * 이미지가 있는지 확인합니다.
     */
    public function hasImages(): bool
    {
        return !empty($this->images);
    }

    /**
     * 특정 타입의 이미지가 있는지 확인합니다.
     */
    public function hasImage(string $type): bool
    {
        return !empty($this->getImageUrl($type));
    }

    /*
     * ======================================
     * Config 기반 이미지 관련 메서드들
     * ======================================
     */

    /**
     * 사용 가능한 이미지 카테고리들을 가져옵니다.
     */
    public static function getImageCategories(): array
    {
        return config('menu.image_categories', [
            'thumbnail' => 'Thumbnail',
            'preview' => 'Preview Image',
            'header' => 'Header Image',
            'banner' => 'Banner Image',
            'icon' => 'Icon Image',
            'background' => 'Background Image',
            'seo' => 'SEO Image',
        ]);
    }

    /**
     * 기본 이미지 카테고리를 가져옵니다.
     */
    public static function getDefaultImageCategory(): string
    {
        return config('menu.default_image_category', 'thumbnail');
    }

    /**
     * 이미지 카테고리의 라벨을 가져옵니다.
     */
    public function getImageCategoryLabel(string $category): string
    {
        $categories = self::getImageCategories();
        return $categories[$category] ?? $category;
    }

    /**
     * 모든 이미지의 카테고리별 정보를 가져옵니다.
     */
    public function getImagesByCategory(): array
    {
        $imagesByCategory = [];
        $categories = self::getImageCategories();
        
        foreach ($categories as $categoryKey => $categoryLabel) {
            $imageUrl = $this->getImageUrl($categoryKey);
            if ($imageUrl) {
                $imagesByCategory[$categoryKey] = [
                    'url' => $imageUrl,
                    'label' => $categoryLabel,
                ];
            }
        }
        
        return $imagesByCategory;
    }

    /**
     * 이미지 카테고리가 유효한지 확인합니다.
     */
    public function isValidImageCategory(string $category): bool
    {
        return array_key_exists($category, self::getImageCategories());
    }

    /**
     * 이미지 파일을 저장하고 상대 경로를 반환합니다.
     */
    public static function storeImageFile($file, string $category = 'general'): string
    {
        if (!$file || !$file->isValid()) {
            throw new \InvalidArgumentException('Invalid file provided');
        }

        // 이미지 파일 검증
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \InvalidArgumentException('File must be an image (JPEG, PNG, GIF, WebP, SVG)');
        }

        // 파일 크기 제한 (10MB)
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException('File size must be less than 10MB');
        }

        // FileUploadService를 사용하여 업로드
        $directory = "menu_images/{$category}";
        $path = FileUploadService::upload($file, $directory);
        
        // 상대 경로 반환
        return $path;
    }

    /**
     * 이미지 파일을 삭제합니다.
     */
    public static function deleteImageFile(string $path): bool
    {
        // 상대 경로인지 전체 URL인지 확인하고 처리
        if (strpos($path, 'http') === 0) {
            // 전체 URL에서 storage 경로 추출 시도
            $assetPath = asset('storage/');
            if (strpos($path, $assetPath) === 0) {
                $path = str_replace($assetPath, '', $path);
                $path = ltrim($path, '/');
            } else {
                // S3 URL일 수 있음 - path에서 버킷 이후 부분 추출
                $urlParts = parse_url($path);
                if (isset($urlParts['path'])) {
                    $path = ltrim($urlParts['path'], '/');
                }
            }
        }
        
        return FileUploadService::delete($path);
    }

    /**
     * 요청에서 이미지 파일들을 처리하고 images 배열을 반환합니다.
     */
    public static function processImageUploads(array $imageData, ?array $existingImages = null): array
    {
        $processedImages = [];
        
        // 빈 배열이거나 null인 경우 기존 이미지 유지
        if (empty($imageData)) {
            return $existingImages ?? [];
        }
        
        foreach ($imageData as $index => $data) {
            // 배열이 아닌 경우 건너뛰기
            if (!is_array($data)) {
                continue;
            }
            
            $category = $data['category'] ?? self::getDefaultImageCategory();
            
            // 새 파일이 업로드된 경우
            if (isset($data['file']) && is_object($data['file']) && method_exists($data['file'], 'isValid') && $data['file']->isValid()) {
                try {
                    // 기존 파일이 있다면 삭제
                    if ($existingImages && isset($existingImages[$category]['url'])) {
                        self::deleteImageFile($existingImages[$category]['url']);
                    }
                    
                    // 새 파일 저장
                    $url = self::storeImageFile($data['file'], $category);
                    $processedImages[$category] = [
                        'url' => $url,
                        'uploaded_at' => now()->toISOString(),
                    ];
                } catch (\Exception $e) {
                    // 파일 업로드 실패 시 로그만 남기고 계속 진행
                    // Log::error("Image upload failed for category {$category}: " . $e->getMessage());
                }
            }
            // 기존 파일을 유지하는 경우
            elseif (isset($data['existing_url']) && !empty($data['existing_url'])) {
                $processedImages[$category] = [
                    'url' => $data['existing_url'],
                    'uploaded_at' => isset($existingImages[$category]['uploaded_at']) ? $existingImages[$category]['uploaded_at'] : now()->toISOString(),
                ];
            }
        }
        
        return $processedImages;
    }
}
