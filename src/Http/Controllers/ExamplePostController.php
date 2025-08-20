<?php

namespace SiteManager\Http\Controllers;

use Illuminate\Http\Request;
use SiteManager\Models\Post; // 예시 모델

class PostController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        
        // 메뉴 권한 미들웨어 적용
        $this->middleware('menu.permission:posts,1')->only(['index']); // Index 권한 필요
        $this->middleware('menu.permission:posts,2')->only(['show']);  // Read 권한 필요
        $this->middleware('menu.permission:posts,32')->only(['create', 'store', 'edit', 'update']); // Write 권한 필요
        $this->middleware('menu.permission:posts,128')->only(['destroy']); // FullControl 권한 필요
    }

    /**
     * 게시글 목록
     */
    public function index(Request $request)
    {
        // 미들웨어에서 권한이 확인되었으므로 안전하게 실행
        $this->initializeMenuPermission($request);
        
        // 추가 권한 확인이 필요한 경우
        $this->requirePermission(1, '게시글 목록을 볼 권한이 없습니다.');
        
        $posts = Post::paginate(10);
        
        return view('sitemanager::posts.index', compact('posts'));
    }

    /**
     * 게시글 상세보기
     */
    public function show(Request $request, $id)
    {
        $this->initializeMenuPermission($request);
        
        $post = Post::findOrFail($id);
        
        return view('sitemanager::posts.show', [
            'post' => $post,
            'canEdit' => $this->canWrite(),
            'canDelete' => $this->canFullControl()
        ]);
    }

    /**
     * 게시글 생성 폼
     */
    public function create(Request $request)
    {
        $this->initializeMenuPermission($request);
        
        return view('sitemanager::posts.create');
    }

    /**
     * 게시글 저장
     */
    public function store(Request $request)
    {
        $this->initializeMenuPermission($request);
        
        // 파일 업로드가 포함된 경우 추가 권한 확인
        if ($request->hasFile('attachment')) {
            $this->requirePermission(64, '파일 업로드 권한이 없습니다.');
        }
        
        $validated = $request->validate([
            'title' => 'required|max:255',
            'content' => 'required',
            'attachment' => 'nullable|file|max:10240'
        ]);
        
        $post = Post::create($validated);
        
        return redirect()->route('posts.show', $post)->with('success', '게시글이 작성되었습니다.');
    }

    /**
     * 게시글 수정 폼
     */
    public function edit(Request $request, $id)
    {
        $this->initializeMenuPermission($request);
        
        $post = Post::findOrFail($id);
        
        return view('sitemanager::posts.edit', compact('post'));
    }

    /**
     * 게시글 업데이트
     */
    public function update(Request $request, $id)
    {
        $this->initializeMenuPermission($request);
        
        $post = Post::findOrFail($id);
        
        $validated = $request->validate([
            'title' => 'required|max:255',
            'content' => 'required'
        ]);
        
        $post->update($validated);
        
        return redirect()->route('posts.show', $post)->with('success', '게시글이 수정되었습니다.');
    }

    /**
     * 게시글 삭제
     */
    public function destroy(Request $request, $id)
    {
        $this->initializeMenuPermission($request);
        
        $post = Post::findOrFail($id);
        $post->delete();
        
        return redirect()->route('posts.index')->with('success', '게시글이 삭제되었습니다.');
    }

    /**
     * AJAX로 권한 정보 반환
     */
    public function getPermissionInfo(Request $request)
    {
        $this->initializeMenuPermission($request);
        
        return response()->json($this->getPermissionInfo());
    }
}
