<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SiteManager\Services\MemberServiceFactory;

class MemberController extends Controller
{
    /**
     * 회원 가입
     */
    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255',
            'email' => 'required|email|unique:members,email',
            'password' => 'required|string|min:6',
            'name' => 'required|string|max:255',
        ]);
        
        // 팩토리를 통해 적절한 서비스 자동 선택
        $memberService = MemberServiceFactory::create();
        
        $member = $memberService->createMember([
            'username' => $request->username,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'name' => $request->name,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '회원가입이 완료되었습니다.',
            'service_type' => MemberServiceFactory::getServiceType(),
            'member' => $member
        ]);
    }
}