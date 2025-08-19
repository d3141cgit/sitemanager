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
