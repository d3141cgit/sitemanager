<?php

namespace SiteManager\Http\Controllers;

use SiteManager\Services\EmailVerificationService;
use SiteManager\Models\BoardPost;
use SiteManager\Models\BoardComment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function __construct(
        private EmailVerificationService $emailVerificationService
    ) {}

    /**
     * 현재 프로젝트의 레이아웃 경로 감지
     */
    private function getProjectLayoutPath(): string
    {
        // 프로젝트에 layouts.app이 있으면 우선 사용
        if (view()->exists('layouts.app')) {
            return 'layouts.app';
        }
        
        // 없으면 sitemanager 기본 레이아웃 사용
        return 'sitemanager::layouts.app';
    }

    /**
     * 이메일 인증 페이지 표시
     */
    public function verify(Request $request, string $token): View|RedirectResponse
    {
        $tokenData = Cache::get("email_verification:{$token}");
        $layoutPath = $this->getProjectLayoutPath();
        
        if (!$tokenData) {
            return view('sitemanager::email.verified-failed', compact('layoutPath'));
        }
        
        // 비회원 게스트인 경우 비밀번호 설정 페이지로 이동
        return view('sitemanager::email.password-setup', [
            'tokenData' => $tokenData,
            'token' => $token,
            'layoutPath' => $layoutPath
        ]);
    }
    
    /**
     * 수정/삭제 인증 페이지 표시
     */
    public function editVerify(Request $request, string $token): View|RedirectResponse
    {
        $tokenData = $this->emailVerificationService->verifyEditToken($token);
        $layoutPath = $this->getProjectLayoutPath();
        
        if (!$tokenData) {
            return view('sitemanager::email.edit-verification-failed', compact('layoutPath'));
        }
        
        // 인증 성공 시 해당 작업 페이지로 리다이렉트
        if ($tokenData['action'] === 'edit') {
            if ($tokenData['type'] === 'post') {
                return redirect()->route('board.edit', [
                    'slug' => $tokenData['board_slug'],
                    'id' => $tokenData['id'],
                    'verified_token' => $token
                ]);
            } else {
                return redirect()->route('board.show', [
                    'slug' => $tokenData['board_slug'],
                    'id' => $tokenData['id']
                ])->with('comment_edit_verified', $tokenData['id']);
            }
        } elseif ($tokenData['action'] === 'delete') {
            return view('sitemanager::email.delete-confirmation', [
                'tokenData' => $tokenData,
                'layoutPath' => $layoutPath
            ]);
        }
        
        return view('sitemanager::email.edit-verification-failed', compact('layoutPath'));
    }
    
    /**
     * 재인증 이메일 발송 (AJAX)
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'type' => 'required|in:post,comment',
            'id' => 'required|integer',
            'board_slug' => 'required|string'
        ]);
        
        try {
            $this->emailVerificationService->sendVerificationEmail(
                $validated['email'],
                $validated['type'],
                $validated['id'],
                $validated['board_slug']
            );
            
            return response()->json([
                'success' => true,
                'message' => '인증 이메일이 다시 발송되었습니다.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to resend verification email', [
                'request' => $validated,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '이메일 발송에 실패했습니다. 다시 시도해주세요.'
            ], 500);
        }
    }
    
    /**
     * 수정/삭제 인증 이메일 발송 (AJAX)
     */
    public function sendEditVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'type' => 'required|in:post,comment',
            'id' => 'required|integer',
            'board_slug' => 'required|string',
            'action' => 'required|in:edit,delete',
            'g-recaptcha-response' => 'nullable|string'
        ]);
        
        // 캡챠 검증
        if ($request->has('g-recaptcha-response')) {
            if (!$this->emailVerificationService->verifyCaptcha(
                $validated['g-recaptcha-response'],
                $request->ip()
            )) {
                return response()->json([
                    'success' => false,
                    'message' => '캡챠 검증에 실패했습니다.'
                ], 422);
            }
        }
        
        // 이메일 도메인 블랙리스트 검사
        if ($this->emailVerificationService->isBlockedEmailDomain($validated['email'])) {
            return response()->json([
                'success' => false,
                'message' => '사용할 수 없는 이메일 도메인입니다.'
            ], 422);
        }
        
        try {
            $this->emailVerificationService->sendEditVerificationEmail(
                $validated['email'],
                $validated['type'],
                $validated['id'],
                $validated['board_slug'],
                $validated['action']
            );
            
            return response()->json([
                'success' => true,
                'message' => '인증 이메일이 발송되었습니다. 이메일을 확인해주세요.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send edit verification email', [
                'request' => $validated,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '이메일 발송에 실패했습니다. 다시 시도해주세요.'
            ], 500);
        }
    }
    
    /**
     * 삭제 확인 처리
     */
    public function confirmDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'confirm' => 'required|boolean'
        ]);
        
        if (!$validated['confirm']) {
            return response()->json([
                'success' => false,
                'message' => '삭제가 취소되었습니다.'
            ]);
        }
        
        $tokenData = $this->emailVerificationService->verifyEditToken($validated['token']);
        
        if (!$tokenData) {
            return response()->json([
                'success' => false,
                'message' => '인증 토큰이 유효하지 않습니다.'
            ], 422);
        }
        
        try {
            if ($tokenData['type'] === 'post') {
                $postModelClass = BoardPost::forBoard($tokenData['board_slug']);
                $post = $postModelClass::findOrFail($tokenData['id']);
                $post->delete();
                
                return response()->json([
                    'success' => true,
                    'message' => '게시글이 삭제되었습니다.',
                    'redirect' => route('board.index', ['slug' => $tokenData['board_slug']])
                ]);
                
            } else {
                $commentModelClass = BoardComment::forBoard($tokenData['board_slug']);
                $comment = $commentModelClass::findOrFail($tokenData['id']);
                $postId = $comment->post_id;
                $comment->delete();
                
                return response()->json([
                    'success' => true,
                    'message' => '댓글이 삭제되었습니다.',
                    'redirect' => route('board.show', [
                        'slug' => $tokenData['board_slug'],
                        'id' => $postId
                    ])
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to delete after verification', [
                'token_data' => $tokenData,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '삭제 처리 중 오류가 발생했습니다.'
            ], 500);
        }
    }
    
    /**
     * 비밀번호 설정 처리
     */
    public function setupPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:4|max:20|confirmed'
        ]);
        
        $tokenData = Cache::get("email_verification:{$validated['token']}");
        
        if (!$tokenData) {
            return response()->json([
                'success' => false,
                'message' => '유효하지 않거나 만료된 토큰입니다.'
            ], 400);
        }
        
        try {
            // 이메일 인증과 비밀번호 설정을 함께 처리
            $result = $this->emailVerificationService->verifyEmailAndSetPassword(
                $validated['token'],
                $validated['password']
            );
            
            if ($result) {
                // 토큰 삭제 (일회용)
                Cache::forget("email_verification:{$validated['token']}");
                
                return response()->json([
                    'success' => true,
                    'message' => '비밀번호가 설정되었습니다.',
                    'redirect_url' => '/board/email/setup-complete'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '인증 처리에 실패했습니다.'
                ], 500);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to setup password', [
                'token' => $validated['token'],
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '비밀번호 설정 중 오류가 발생했습니다.'
            ], 500);
        }
    }
    
    /**
     * 비밀번호 설정 완료 페이지
     */
    public function setupComplete(): View
    {
        $layoutPath = $this->getProjectLayoutPath();
        return view('sitemanager::email.setup-complete', compact('layoutPath'));
    }
}
