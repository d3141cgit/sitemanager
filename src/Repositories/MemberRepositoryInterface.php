<?php

namespace SiteManager\Repositories;

use SiteManager\Models\Member;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface MemberRepositoryInterface
{
    public function all(): Collection;
    
    public function paginate(int $perPage = 15): LengthAwarePaginator;
    
    public function find(int $id): ?Member;
    
    public function findByUsername(string $username): ?Member;
    
    public function findByEmail(string $email): ?Member;
    
    public function create(array $data): Member;
    
    public function update(int $id, array $data): bool;
    
    public function delete(int $id): bool;
    
    public function restore(int $id): bool;
    
    public function forceDelete(int $id): bool;
    
    public function withTrashed(): Collection;
    
    public function onlyTrashed(): Collection;
}
