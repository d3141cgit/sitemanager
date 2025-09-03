<?php

namespace SiteManager\Http\Controllers\SiteManager;

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

class SiteManagerCommentController extends Controller
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
        $status = $request->get('status', 'approved');
        
        $pendingComments = collect();
        $selectedBoard = null;
        $statusCounts = ['pending' => 0, 'approved' => 0, 'deleted' => 0];
        
        if ($selectedBoardId) {
            $selectedBoard = Board::findOrFail($selectedBoardId);
            
            // 해당 게시판의 댓글 모델 클래스 가져오기
            $commentModelClass = BoardComment::forBoard($selectedBoard->slug);
            
            // 상태별 개수 계산
            $statusCounts['pending'] = $commentModelClass::where('status', 'pending')->count();
            $statusCounts['approved'] = $commentModelClass::where('status', 'approved')->count();
            $statusCounts['deleted'] = $commentModelClass::onlyTrashed()->count();
            
            // 상태별 댓글 조회 - 계층적 정렬
            $query = $commentModelClass::with(['member', 'post', 'parent', 'children']);
            
            // 삭제된 댓글을 보려면 withTrashed() 사용
            if ($status === 'deleted') {
                $query = $query->onlyTrashed();
            } else {
                $query = $query->where('status', $status);
            }
            
            // 계층적 정렬: 부모 댓글의 created_at 기준으로 정렬하되, 자식은 부모 바로 아래 배치
            $allComments = $query->orderByRaw('
                CASE 
                    WHEN parent_id IS NULL THEN created_at 
                    ELSE (SELECT created_at FROM ' . (new $commentModelClass)->getTable() . ' parent WHERE parent.id = ' . (new $commentModelClass)->getTable() . '.parent_id)
                END DESC,
                CASE 
                    WHEN parent_id IS NULL THEN 0 
                    ELSE 1 
                END ASC,
                created_at ASC
            ')->get();
            
            // 페이지네이션을 위한 수동 처리
            $perPage = $request->get('per_page', config('sitemanager.ui.pagination_per_page', 20));
            $perPage = min(max((int)$perPage, 1), 100); // 1-100 범위로 제한
            $currentPage = request()->get('page', 1);
            $total = $allComments->count();
            
            $pendingComments = new \Illuminate\Pagination\LengthAwarePaginator(
                $allComments->forPage($currentPage, $perPage),
                $total,
                $perPage,
                $currentPage,
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            );
            
            // 각 댓글의 액션 가능 여부 계산
            $pendingComments->getCollection()->each(function ($comment) {
                $comment->actions = $comment->getActionAvailability();
            });
        }
        
        return view('sitemanager::sitemanager.board.comments', compact(
            'boards', 
            'pendingComments', 
            'selectedBoard', 
            'selectedBoardId',
            'status',
            'statusCounts'
        ));
    }

    /**
     * 댓글 승인
     */
    public function approve(Request $request): JsonResponse
    {
        try {
            $boardSlug = $request->input('board_slug');
            $commentId = $request->input('comment_id');
            
            $board = Board::where('slug', $boardSlug)->firstOrFail();
            $commentModelClass = BoardComment::forBoard($board->slug);
            $comment = $commentModelClass::findOrFail($commentId);
            
            $comment->status = 'approved';
            $comment->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Comment approved successfully.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Comment approval failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve comment.'
            ], 500);
        }
    }

    /**
     * 댓글 삭제 (소프트 삭제)
     */
    public function delete(Request $request): JsonResponse
    {
        try {
            $boardSlug = $request->input('board_slug');
            $commentId = $request->input('comment_id');
            
            $board = Board::where('slug', $boardSlug)->firstOrFail();
            $commentModelClass = BoardComment::forBoard($board->slug);
            $comment = $commentModelClass::findOrFail($commentId);
            
            $comment->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Comment deletion failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment.'
            ], 500);
        }
    }

    /**
     * 댓글 복원
     */
    public function restore(Request $request): JsonResponse
    {
        try {
            $boardSlug = $request->input('board_slug');
            $commentId = $request->input('comment_id');
            
            $board = Board::where('slug', $boardSlug)->firstOrFail();
            $commentModelClass = BoardComment::forBoard($board->slug);
            $comment = $commentModelClass::withTrashed()->findOrFail($commentId);
            
            $comment->restore();
            
            return response()->json([
                'success' => true,
                'message' => 'Comment restored successfully.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Comment restoration failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore comment.'
            ], 500);
        }
    }

    /**
     * 댓글 완전 삭제
     */
    public function forceDelete(Request $request): JsonResponse
    {
        try {
            $boardSlug = $request->input('board_slug');
            $commentId = $request->input('comment_id');
            
            $board = Board::where('slug', $boardSlug)->firstOrFail();
            $commentModelClass = BoardComment::forBoard($board->slug);
            $comment = $commentModelClass::withTrashed()->findOrFail($commentId);
            
            // 게시글 참조를 먼저 저장 (forceDelete 후에는 접근 불가)
            $post = $comment->post;
            
            $comment->forceDelete();
            
            return response()->json([
                'success' => true,
                'message' => 'Comment permanently deleted.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Comment force deletion failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete comment.'
            ], 500);
        }
    }

    /**
     * 벌크 액션 처리
     */
    public function bulkAction(Request $request): JsonResponse
    {
        try {
            $boardSlug = $request->input('board_slug');
            $commentIds = $request->input('comment_ids', []);
            $action = $request->input('action');
            
            if (empty($commentIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No comments selected.'
                ], 400);
            }
            
            $board = Board::where('slug', $boardSlug)->firstOrFail();
            $commentModelClass = BoardComment::forBoard($board->slug);
            
            $query = $commentModelClass::whereIn('id', $commentIds);
            
            // 삭제된 댓글에 대한 액션의 경우 withTrashed() 사용
            if (in_array($action, ['restore', 'force_delete'])) {
                $query = $query->withTrashed();
            }
            
            $comments = $query->get();
            $processed = 0;
            
            foreach ($comments as $comment) {
                try {
                    switch ($action) {
                        case 'approve':
                            if ($comment->status !== 'approved') {
                                $comment->status = 'approved';
                                $comment->save();
                                $processed++;
                            }
                            break;
                            
                        case 'delete':
                            if (!$comment->trashed()) {
                                $comment->delete();
                                $processed++;
                            }
                            break;
                            
                        case 'restore':
                            if ($comment->trashed()) {
                                $comment->restore();
                                $processed++;
                            }
                            break;
                            
                        case 'force_delete':
                            $comment->forceDelete();
                            $processed++;
                            break;
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to process comment {$comment->id}: " . $e->getMessage());
                    continue;
                }
            }
            
            $actionNames = [
                'approve' => 'approved',
                'delete' => 'deleted',
                'restore' => 'restored',
                'force_delete' => 'permanently deleted'
            ];
            
            $actionName = $actionNames[$action] ?? $action;
            
            return response()->json([
                'success' => true,
                'message' => "{$processed} comment(s) {$actionName} successfully."
            ]);
            
        } catch (\Exception $e) {
            Log::error('Bulk action failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Bulk action failed.'
            ], 500);
        }
    }
}
