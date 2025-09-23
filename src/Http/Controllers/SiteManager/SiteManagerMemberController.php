<?php

namespace SiteManager\Http\Controllers\SiteManager;

use SiteManager\Http\Controllers\Controller;
use SiteManager\Models\Member;
use SiteManager\Models\Group;
use SiteManager\Services\FileUploadService;
use SiteManager\Services\MemberServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SiteManagerMemberController extends Controller
{
    public function __construct(
        private FileUploadService $fileUploadService
    ) {}
    /**
     * 멤버 목록 (관리자용)
     */
    public function index(Request $request)
    {
        $query = Member::with('groups')->orderBy('name');

        // 삭제된 멤버 포함 여부
        if ($request->get('status') === 'deleted') {
            $query->onlyTrashed();
        } elseif ($request->get('status') === 'inactive') {
            $query->where('active', false);
        } elseif ($request->get('status') === 'all') {
            // 'all' 상태일 때만 모든 멤버 표시 (active + inactive, 삭제된 것 제외)
        } else {
            // 기본값: active 멤버만 표시 (status가 없거나 'active'인 경우)
            $query->where('active', true);
        }

        // 검색 필터
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // 상태 필터 (active/inactive, 삭제된 것 제외) - 위에서 이미 처리됨
        if ($request->filled('status') && $request->get('status') === 'inactive') {
            // inactive만 따로 처리 (위의 기본 active 필터를 덮어씀)
            $query->where('active', false);
        }

        // 그룹 필터
        if ($request->filled('group_id')) {
            $query->whereHas('groups', function($q) use ($request) {
                $q->where('group_id', $request->get('group_id'));
            });
        }

        // 레벨 필터
        if ($request->filled('level')) {
            $query->where('level', $request->get('level'));
        }

        // 동적 pagination 개수 설정
        $perPage = $request->get('per_page', config('sitemanager.ui.pagination_per_page', 20));
        $perPage = min(max((int)$perPage, 1), 100); // 1-100 범위로 제한
        
        $members = $query->paginate($perPage)->appends($request->query());
        $groups = Group::orderBy('name')->get();
        $levels = config('member.levels');

        return view('sitemanager::sitemanager.members.index', compact('members', 'groups', 'levels'));
    }

    /**
     * 멤버 상세 보기 (edit으로 리다이렉트)
     */
    public function show(Member $member)
    {
        return redirect()->route('sitemanager.members.edit', $member);
    }

    /**
     * 멤버 생성 폼
     */
    public function create()
    {
        $groups = Group::orderBy('name')->get();
        $levels = config('member.levels');
        return view('sitemanager::sitemanager.members.form', compact('groups', 'levels'));
    }

    /**
     * 멤버 생성
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:members,username',
            'title' => 'nullable|string|max:30',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:members,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'level' => 'required|integer|between:1,255',
            'active' => 'boolean',
            'groups' => 'array',
            'groups.*' => 'exists:groups,id',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // MemberServiceFactory를 통해 적절한 서비스 사용 (패스워드 해시는 서비스에서 처리)
        
        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            $fileInfo = $this->fileUploadService->uploadProfilePhoto($file);
            $validated['profile_photo'] = $fileInfo['path'];
        }
        
        $memberService = MemberServiceFactory::create();
        
        try {
            $member = $memberService->createMember($validated);
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
        
        if (isset($validated['groups'])) {
            $member->groups()->sync($validated['groups']);
        }

        return redirect()->route('sitemanager.members.index')
            ->with('success', '멤버가 성공적으로 생성되었습니다.');
    }

    /**
     * 멤버 수정 폼
     */
    public function edit(Member $member)
    {
        $groups = Group::orderBy('name')->get();
        $levels = config('member.levels');
        $member->load('groups');

        if ($member->id === 1 && Auth::user()->level !== 255) {
            abort(403, '루트 권한이 필요합니다.');
        }

        return view('sitemanager::sitemanager.members.form', compact('member', 'groups', 'levels'));
    }

    /**
     * 멤버 수정
     */
    public function update(Request $request, Member $member)
    {
        // 루트 멤버(id=1)는 level 255만 수정 가능
        if ($member->id === 1 && Auth::user()->level !== 255) {
            abort(403, '루트 권한이 필요합니다.');
        }

        // 루트 멤버(id=1)의 username 변경 방지
        if ($member->id === 1 && $request->has('username') && $request->username !== $member->username) {
            return redirect()->back()
                ->withErrors(['username' => 'Root user\'s username cannot be changed.'])
                ->withInput();
        }
        
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', Rule::unique('members')->ignore($member->id)],
            'title' => 'nullable|string|max:30',
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('members')->ignore($member->id)],
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'level' => 'required|integer|between:0,255',
            'active' => 'boolean',
            'groups' => 'array',
            'groups.*' => 'exists:groups,id',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'remove_profile_photo' => 'boolean',
        ]);

        // Handle profile photo removal
        if ($request->get('remove_profile_photo') == '1' && $member->profile_photo) {
            $this->fileUploadService->deleteFile($member->profile_photo);
            $validated['profile_photo'] = null;
        }

        $validated['active'] = $request->boolean('active');

        // 상태가 inactive로 변경되면 level을 0으로 설정
        if (!$validated['active']) {
            $validated['level'] = 0;
        }

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($member->profile_photo) {
                $this->fileUploadService->deleteFile($member->profile_photo);
            }
            
            $file = $request->file('profile_photo');
            $fileInfo = $this->fileUploadService->uploadProfilePhoto($file);
            $validated['profile_photo'] = $fileInfo['path'];
        }

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = bcrypt($validated['password']);
        }

        $member->update($validated);
        
        if (isset($validated['groups'])) {
            $member->groups()->sync($validated['groups']);
        }

        // return redirect()->route('sitemanager.members.show', $member)
        //     ->with('success', '멤버 정보가 성공적으로 수정되었습니다.');
        return redirect()->route('sitemanager.members.index')
            ->with('success', '멤버 정보가 성공적으로 수정되었습니다.');
    }

    /**
     * 멤버 삭제 (소프트 삭제)
     */
    public function destroy(Member $member)
    {
        // 루트 멤버(id=1) 삭제 방지
        if ($member->id === 1) {
            return redirect()->route('sitemanager.members.index')
                ->with('error', 'Root user cannot be deleted.');
        }
        
        $member->delete();
        
        return redirect()->route('sitemanager.members.index')
            ->with('success', '멤버가 성공적으로 삭제되었습니다.');
    }

    /**
     * 멤버 복원
     */
    public function restore($id)
    {
        $member = Member::withTrashed()->findOrFail($id);
        $member->restore();
        
        return redirect()->route('sitemanager.members.index')
            ->with('success', '멤버가 성공적으로 복원되었습니다.');
    }

    /**
     * 멤버 완전 삭제
     */
    public function forceDelete($id)
    {
        $member = Member::withTrashed()->findOrFail($id);
        
        // 루트 멤버(id=1) 완전 삭제 방지
        if ($member->id === 1) {
            return redirect()->route('sitemanager.members.index')
                ->with('error', 'Root user cannot be permanently deleted.');
        }
        
        $member->forceDelete();
        
        return redirect()->route('sitemanager.members.index')
            ->with('success', '멤버가 완전히 삭제되었습니다.');
    }

    /**
     * 멤버 상태 토글 (AJAX)
     */
    public function toggleStatus(Request $request, Member $member)
    {
        $newStatus = $request->boolean('active');
        
        // 상태가 inactive로 변경되면 level을 0으로 설정
        $updateData = ['active' => $newStatus];
        if (!$newStatus) {
            $updateData['level'] = 0;
        } else {
            $updateData['level'] = 1; // 활성화 시 기본 레벨을 1로 설정
        }
        
        $member->update($updateData);
        
        return response()->json([
            'success' => true,
            'message' => '멤버 상태가 변경되었습니다.',
            'active' => $member->active,
            'level' => $member->level
        ]);
    }

    /**
     * 멤버 검색 (AJAX)
     */
    public function search(Request $request)
    {
        try {
            $query = $request->get('q');
            
            if (strlen($query) < 2) {
                return response()->json([]);
            }
            
            $members = Member::where('active', true)
                ->where('id', '!=', 1) // root 사용자 제외
                ->where(function($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('username', 'like', "%{$query}%")
                      ->orWhere('email', 'like', "%{$query}%");
                })
                ->select('id', 'name', 'username', 'email')
                ->limit(20)
                ->get();
            
            return response()->json($members);
            
        } catch (\Exception $e) {
            Log::error('Member search error: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Search failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
