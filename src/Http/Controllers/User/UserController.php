<?php

namespace SiteManager\Http\Controllers\User;

use App\Http\Controllers\Controller;
use SiteManager\Models\Member;
use SiteManager\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * 사용자 대시보드
     */
    public function dashboard()
    {
        $user = Auth::user();
        $user->load('groups');
        
        return view('user.dashboard', compact('user'));
    }

    /**
     * 프로필 보기
     */
    public function profile()
    {
        $user = Auth::user();
        $user->load('groups');
        
        return view('user.profile', compact('user'));
    }

    /**
     * 프로필 수정 폼
     */
    public function editProfile()
    {
        $user = Auth::user();
        $user->load('groups');
        
        return view('user.profile-edit', compact('user'));
    }

    /**
     * 프로필 수정
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('members')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'birth_date' => 'nullable|date',
        ]);

        $user->update($validated);

        return redirect()->route('user.profile')
            ->with('success', '프로필이 성공적으로 수정되었습니다.');
    }

    /**
     * 비밀번호 변경 폼
     */
    public function changePasswordForm()
    {
        return view('user.change-password');
    }

    /**
     * 비밀번호 변경
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        // 현재 비밀번호 확인
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => '현재 비밀번호가 올바르지 않습니다.'
            ]);
        }

        // 새 비밀번호로 변경
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return redirect()->route('user.profile')
            ->with('success', '비밀번호가 성공적으로 변경되었습니다.');
    }

    /**
     * 내 그룹 목록
     */
    public function myGroups()
    {
        $user = Auth::user();
        $groups = $user->groups()->with('members')->get();
        
        return view('user.groups', compact('groups'));
    }

    /**
     * 회원 탈퇴 폼
     */
    public function deleteAccountForm()
    {
        return view('user.delete-account');
    }

    /**
     * 회원 탈퇴
     */
    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password' => 'required',
            'confirmation' => 'required|in:회원탈퇴',
        ], [
            'confirmation.in' => '정확히 "회원탈퇴"를 입력해주세요.'
        ]);

        $user = Auth::user();

        // 비밀번호 확인
        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'password' => '비밀번호가 올바르지 않습니다.'
            ]);
        }

        // 로그아웃 후 계정 삭제
        Auth::logout();
        $user->delete();

        return redirect()->route('home')
            ->with('success', '회원탈퇴가 완료되었습니다.');
    }
}
