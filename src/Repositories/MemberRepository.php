<?php

namespace SiteManager\Repositories;

use SiteManager\Models\Member;
use SiteManager\Repositories\MemberRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class MemberRepository implements MemberRepositoryInterface
{
    protected $model;
    
    public function __construct(Member $model)
    {
        $this->model = $model;
    }
    
    public function all(): Collection
    {
        return $this->model->all();
    }
    
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->withTrashed()
                          ->orderBy('level', 'desc')
                          ->orderBy('name')
                          ->paginate($perPage);
    }
    
    public function find(int $id): ?Member
    {
        return $this->model->find($id);
    }
    
    public function findByUsername(string $username): ?Member
    {
        return $this->model->where('username', $username)->first();
    }
    
    public function findByEmail(string $email): ?Member
    {
        return $this->model->where('email', $email)->first();
    }
    
    public function create(array $data): Member
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        
        return $this->model->create($data);
    }
    
    public function update(int $id, array $data): bool
    {
        $member = $this->find($id);
        
        if (!$member) {
            return false;
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        
        return $member->update($data);
    }
    
    public function delete(int $id): bool
    {
        $member = $this->find($id);
        
        if (!$member) {
            return false;
        }
        
        return $member->delete();
    }
    
    public function restore(int $id): bool
    {
        $member = $this->model->withTrashed()->find($id);
        
        if (!$member) {
            return false;
        }
        
        return $member->restore();
    }
    
    public function forceDelete(int $id): bool
    {
        $member = $this->model->withTrashed()->find($id);
        
        if (!$member) {
            return false;
        }
        
        return $member->forceDelete();
    }
    
    public function withTrashed(): Collection
    {
        return $this->model->withTrashed()->get();
    }
    
    public function onlyTrashed(): Collection
    {
        return $this->model->onlyTrashed()->get();
    }
}
