<?php

namespace SiteManager\Http\Controllers\Admin;

use SiteManager\Http\Controllers\Controller;
use SiteManager\Models\Group;
use SiteManager\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminGroupController extends Controller
{
    /**
     * 그룹 목록
     */
    public function index(Request $request)
    {
        $query = Group::withCount('members');

        // 삭제된 그룹 포함 여부
        if ($request->get('status') === 'deleted') {
            $query->onlyTrashed();
        } elseif ($request->get('status') !== 'all') {
            $query->withTrashed();
        }

        // 검색 필터
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // 상태 필터 (active/inactive, 삭제된 것 제외)
        if ($request->filled('status') && in_array($request->get('status'), ['active', 'inactive'])) {
            $query->where('active', $request->get('status') === 'active');
        }

        $groups = $query->paginate(20)->appends($request->query());

        return view('sitemanager::admin.groups.index', compact('groups'));
    }

    /**
     * 그룹 생성 폼
     */
    public function create()
    {
        return view('sitemanager::admin.groups.form');
    }

    /**
     * 그룹 저장
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:groups',
            'description' => 'nullable|string|max:1000',
            'active' => 'boolean',
        ]);

        Group::create($validated);

        return redirect()->route('admin.groups.index')
            ->with('success', 'Group created successfully.');
    }

    /**
     * 그룹 상세
     */
    public function show(Group $group)
    {
        return redirect()->route('admin.groups.edit', $group);
    }

    /**
     * 그룹 수정 폼
     */
    public function edit(Group $group)
    {
        $group->load('members');
        $availableMembers = Member::whereDoesntHave('groups', function($query) use ($group) {
            $query->where('group_id', $group->id);
        })->orderBy('name')->get();

        return view('sitemanager::admin.groups.form', compact('group', 'availableMembers'));
    }

    /**
     * 그룹 수정
     */
    public function update(Request $request, Group $group)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:groups,name,' . $group->id,
            'description' => 'nullable|string|max:1000',
            'active' => 'boolean',
            'members' => 'array',
            'members.*' => 'exists:members,id',
        ]);

        $group->update($validated);
        
        if (isset($validated['members'])) {
            $group->members()->sync($validated['members']);
        }

        return redirect()->route('admin.groups.index')
            ->with('success', 'Group updated successfully.');
    }

    /**
     * 그룹 삭제 (소프트 삭제)
     */
    public function destroy(Group $group)
    {
        $group->delete();
        
        return redirect()->route('admin.groups.index')
            ->with('success', 'Group deleted successfully.');
    }

    /**
     * 그룹 복원
     */
    public function restore($id)
    {
        $group = Group::withTrashed()->findOrFail($id);
        $group->restore();
        
        return redirect()->route('admin.groups.index')
            ->with('success', 'Group restored successfully.');
    }

    /**
     * 그룹 완전 삭제
     */
    public function forceDelete($id)
    {
        $group = Group::withTrashed()->findOrFail($id);
        $group->forceDelete();
        
        return redirect()->route('admin.groups.index')
            ->with('success', 'Group permanently deleted.');
    }
}
