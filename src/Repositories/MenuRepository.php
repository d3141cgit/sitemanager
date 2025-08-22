<?php

namespace SiteManager\Repositories;

use SiteManager\Models\Menu;
use SiteManager\Repositories\MenuRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class MenuRepository implements MenuRepositoryInterface
{
    protected $model;
    
    public function __construct(Menu $model)
    {
        $this->model = $model;
    }
    
    public function all(): Collection
    {
        return $this->model->orderBy('section')->orderBy('_lft')->get(['id', 'title', 'type', 'target', 'section', 'hidden', 'permission', 'parent_id', '_lft', '_rgt', 'depth', 'images']);
    }
    
    public function find(int $id): ?Menu
    {
        return $this->model->find($id);
    }
    
    public function getByType(string $type): Collection
    {
        return $this->model->where('type', $type)
                          ->orderBy('section')
                          ->orderBy('_lft')
                          ->get();
    }
    
    public function getMenuTree(): Collection
    {
        return $this->model->get()->toTree();
    }
    
    public function create(array $data): Menu
    {
        // parent_id가 있는 경우 NodeTrait의 appendToNode를 사용
        if (isset($data['parent_id']) && $data['parent_id']) {
            $parent = $this->find($data['parent_id']);
            if (!$parent) {
                throw new \Exception("Parent menu with ID {$data['parent_id']} not found");
            }
            
            // parent_id를 제거하고 메뉴를 먼저 생성
            $parentId = $data['parent_id'];
            unset($data['parent_id']);
            
            $menu = $this->model->create($data);
            
            // 생성 후 부모에 추가
            $menu->appendToNode($parent)->save();
            
            return $menu;
        }
        
        // parent_id가 없는 경우 일반 생성
        return $this->model->create($data);
    }
    
    public function update(int $id, array $data): bool
    {
        $menu = $this->find($id);
        
        if (!$menu) {
            return false;
        }
        
        return $menu->update($data);
    }
    
    public function delete(int $id): bool
    {
        $menu = $this->find($id);
        
        if (!$menu) {
            return false;
        }
        
        return $menu->delete();
    }

    /**
     * 마지막 루트 메뉴 조회
     */
    public function getLastRootMenu(): ?Menu
    {
        return $this->model->whereNull('parent_id')
                          ->orderBy('_rgt', 'desc')
                          ->first();
    }

    /**
     * 최대 섹션 번호 조회
     */
    public function getMaxSection(): ?int
    {
        return $this->model->max('section');
    }
}
