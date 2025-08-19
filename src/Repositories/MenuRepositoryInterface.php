<?php

namespace SiteManager\Repositories;

use SiteManager\Models\Menu;
use Illuminate\Database\Eloquent\Collection;

interface MenuRepositoryInterface
{
    public function all(): Collection;
    
    public function find(int $id): ?Menu;
    
    public function getByType(string $type): Collection;
    
    public function getMenuTree(): Collection;
    
    public function create(array $data): Menu;
    
    public function update(int $id, array $data): bool;
    
    public function delete(int $id): bool;
    
    /*
     * ======================================
     * 메뉴 이동 관련 메서드들 (현재 사용하지 않음)
     * ======================================
     */
    
    // public function moveNode(int $nodeId, int $targetId, string $position = 'after'): bool;
    
    // public function reorderMenus(array $menuStructure): bool;
    
    public function getLastRootMenu(): ?Menu;
}
