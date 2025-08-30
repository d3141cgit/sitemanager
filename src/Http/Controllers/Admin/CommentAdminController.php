<?php

namespace SiteManager\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use SiteManager\Http\Controllers\Controller;
use SiteManager\Models\Board;
use SiteManager\Models\BoardComment;
use SiteManager\Services\BoardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommentAdminController extends Controller
{
    public function __construct(
        private BoardService $boardService
    ) {}

    /**
     * 댓글 관리 메인 페이지
     */
    public function index(Request $request): View
    {
        $boards = Board::orderBy('name')->get();
        $selectedBoardId = $request->get('board_id');
        $status = $request->get('status', 'pending');
        
        $pendingComments = collect();
        $selectedBoard = null;
        
        if ($selectedBoardId) {
            $selectedBoard = Board::findOrFail($selectedBoardId);
            
            // 해당 게시판의 댓글 모델 클래스 가져오기
            $commentModelClass = BoardComment::forBoard($selectedBoard->slug);
            
            // 상태별 댓글 조회
            $query = $commentModelClass::with(['member', 'post']);
            
            // 삭제된 댓글을 보려면 withTrashed() 사용
            if ($status === 'deleted') {
                $query = $query->onlyTrashed();
            } else {
                $query = $query->where('status', $status);
            }
            
            $pendingComments = $query->orderBy('created_at', 'desc')->paginate(20);
        }
        
        return view('sitemanager::admin.comments.index', compact(
            'boards', 
            'selectedBoardId', 
            'selectedBoard',
            'status',
            'pendingComments'
        ));
    }

    /**
     * 댓글 승인
     */
    public function approve(Request $request): JsonResponse
    {
        $commentId = $request->get('comment_id');
        $boardSlug = $request->get('board_slug');
        
        try {
            $commentModelClass = BoardComment::forBoard($boardSlug);
            $comment = $commentModelClass::findOrFail($commentId);
            
            // 승인 권한 체크
            $board = Board::where('slug', $boardSlug)->firstOrFail();
            if (!can('manageComments', $board)) {
                return response()->json(['error' => '권한이 없습니다.'], 403);
            }
            
            $comment->update(['status' => 'approved']);
            
            // 게시글의 댓글 수 업데이트
            if ($comment->post) {
                $comment->post->updateCommentCount();
            }
            
            return response()->json([
                'success' => true,
                'message' => '댓글이 승인되었습니다.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Comment approval failed', [
                'error' => $e->getMessage(),
                'comment_id' => $commentId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '댓글 승인 중 오류가 발생했습니다.'
            ], 500);
        }
    }

    /**
     * 댓글 거부/삭제
     */
    public function reject(Request $request): JsonResponse
    {
        $commentId = $request->get('comment_id');
        $boardSlug = $request->get('board_slug');
        
        try {
            $commentModelClass = BoardComment::forBoard($boardSlug);
            $comment = $commentModelClass::findOrFail($commentId);
            
            // 승인 권한 체크
            $board = Board::where('slug', $boardSlug)->firstOrFail();
            if (!can('manageComments', $board)) {
                return response()->json(['error' => '권한이 없습니다.'], 403);
            }
            
            // 거부 = 삭제로 처리
            $comment->delete();
            
            return response()->json([
                'success' => true,
                'message' => '댓글이 삭제되었습니다.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Comment rejection failed', [
                'error' => $e->getMessage(),
                'comment_id' => $commentId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '댓글 삭제 중 오류가 발생했습니다.'
            ], 500);
        }
    }

    /**
     * 댓글 영구 삭제
     */
    public function delete(Request $request): JsonResponse
    {
        $commentId = $request->get('comment_id');
        $boardSlug = $request->get('board_slug');
        
        try {
            $commentModelClass = BoardComment::forBoard($boardSlug);
            $comment = $commentModelClass::findOrFail($commentId);
            
            // 승인 권한 체크
            $board = Board::where('slug', $boardSlug)->firstOrFail();
            if (!can('manageComments', $board)) {
                return response()->json(['error' => '권한이 없습니다.'], 403);
            }
            
            $comment->delete();
            
            return response()->json([
                'success' => true,
                'message' => '댓글이 삭제되었습니다.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Comment deletion failed', [
                'error' => $e->getMessage(),
                'comment_id' => $commentId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '댓글 삭제 중 오류가 발생했습니다.'
            ], 500);
        }
    }

    /**
     * 댓글 복원
     */
    public function restore(Request $request): JsonResponse
    {
        $commentId = $request->get('comment_id');
        $boardSlug = $request->get('board_slug');
        
        try {
            $commentModelClass = BoardComment::forBoard($boardSlug);
            $comment = $commentModelClass::withTrashed()->findOrFail($commentId);
            
            // 승인 권한 체크
            $board = Board::where('slug', $boardSlug)->firstOrFail();
            if (!can('manageComments', $board)) {
                return response()->json(['error' => 'Access denied.'], 403);
            }
            
            $comment->restore();
            
            return response()->json([
                'success' => true,
                'message' => 'Comment has been restored.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Comment restoration failed', [
                'error' => $e->getMessage(),
                'comment_id' => $commentId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while restoring the comment.'
            ], 500);
        }
    }

    /**
     * 댓글 완전 삭제
     */
    public function forceDelete(Request $request): JsonResponse
    {
        $commentId = $request->get('comment_id');
        $boardSlug = $request->get('board_slug');
        
        try {
            $commentModelClass = BoardComment::forBoard($boardSlug);
            $comment = $commentModelClass::withTrashed()->findOrFail($commentId);
            
            // 승인 권한 체크
            $board = Board::where('slug', $boardSlug)->firstOrFail();
            if (!can('manageComments', $board)) {
                return response()->json(['error' => 'Access denied.'], 403);
            }
            
            $comment->forceDelete();
            
            return response()->json([
                'success' => true,
                'message' => 'Comment has been permanently deleted.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Comment force deletion failed', [
                'error' => $e->getMessage(),
                'comment_id' => $commentId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while permanently deleting the comment.'
            ], 500);
        }
    }

    /**
     * 일괄 처리
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $action = $request->get('action'); // approve, reject, delete
        $commentIds = $request->get('comment_ids', []);
        $boardSlug = $request->get('board_slug');
        
        if (empty($commentIds)) {
            return response()->json(['error' => '선택된 댓글이 없습니다.'], 400);
        }
        
        try {
            $board = Board::where('slug', $boardSlug)->firstOrFail();
            if (!can('manageComments', $board)) {
                return response()->json(['error' => '권한이 없습니다.'], 403);
            }
            
            $commentModelClass = BoardComment::forBoard($boardSlug);
            
            DB::beginTransaction();
            
            $successCount = 0;
            foreach ($commentIds as $commentId) {
                try {
                    switch ($action) {
                        case 'approve':
                            $comment = $commentModelClass::find($commentId);
                            if ($comment) {
                                $comment->update(['status' => 'approved']);
                                if ($comment->post) {
                                    $comment->post->updateCommentCount();
                                }
                            }
                            break;
                        case 'reject':
                        case 'delete':
                            $comment = $commentModelClass::find($commentId);
                            if ($comment) {
                                $comment->delete();
                            }
                            break;
                        case 'restore':
                            $comment = $commentModelClass::withTrashed()->find($commentId);
                            if ($comment) {
                                $comment->restore();
                            }
                            break;
                        case 'force_delete':
                            $comment = $commentModelClass::withTrashed()->find($commentId);
                            if ($comment) {
                                $comment->forceDelete();
                            }
                            break;
                    }
                    
                    $successCount++;
                } catch (\Exception $e) {
                    Log::error('Bulk comment action failed for ID: ' . $commentId, [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            DB::commit();
            
            $actionText = [
                'approve' => 'approved',
                'reject' => 'deleted',
                'delete' => 'deleted',
                'restore' => 'restored',
                'force_delete' => 'permanently deleted'
            ][$action] ?? 'processed';
            
            return response()->json([
                'success' => true,
                'message' => "{$successCount} comment(s) have been {$actionText}."
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Bulk comment action failed', [
                'error' => $e->getMessage(),
                'action' => $action,
                'board_slug' => $boardSlug,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '일괄 처리 중 오류가 발생했습니다.'
            ], 500);
        }
    }
}
