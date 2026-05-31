<?php

namespace SiteManager\Http\Controllers\SiteManager;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use SiteManager\Http\Controllers\Controller;
use SiteManager\Models\Board;
use SiteManager\Models\Member;
use SiteManager\Services\BoardPostService;

class SiteManagerBoardPostController extends Controller
{
    public function __construct(
        private BoardPostService $postService
    ) {}

    public function index(Request $request, Board $board): View
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $query = $this->postService->postsQuery($board);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('author_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category')) {
            $category = trim((string) $request->input('category'), '|');
            $query->where(function ($q) use ($category) {
                $q->where('category', $category)
                    ->orWhere('category', 'like', "%|{$category}|%");
            });
        }

        $orderBy = $request->input('orderby', 'published_at');
        $allowedOrderBy = ['id', 'title', 'author_name', 'status', 'view_count', 'comment_count', 'published_at', 'created_at', 'updated_at'];
        if (!in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = 'published_at';
        }

        $query->orderBy($orderBy, $request->boolean('desc', true) ? 'desc' : 'asc')
            ->orderByDesc('id');

        $posts = $query->paginate($perPage)->appends($request->query());

        return view('sitemanager::sitemanager.board-post.index', compact('board', 'posts'));
    }

    public function create(Board $board): View
    {
        $members = $this->members();
        $postOptions = [];
        $postMeta = [];
        $postFields = $board->getPostFieldDefinitions();
        $postOptionFields = $board->getPostOptionDefinitions();

        return view('sitemanager::sitemanager.board-post.form', compact('board', 'members', 'postOptions', 'postMeta', 'postFields', 'postOptionFields'));
    }

    public function store(Request $request, Board $board): RedirectResponse
    {
        $post = $this->postService->createFromRequest($board, $request);

        return redirect()
            ->route('sitemanager.boards.posts.edit', [$board, $post->id])
            ->with('success', 'Post has been created successfully.');
    }

    public function edit(Board $board, int $post): View
    {
        $post = $this->postService->getPost($board, $post);
        $members = $this->members();
        $postOptions = $this->postService->decodedOptions($post->options);
        $postMeta = $this->postService->decodedMeta($post);
        $postFields = $board->getPostFieldDefinitions();
        $postOptionFields = $board->getPostOptionDefinitions();

        return view('sitemanager::sitemanager.board-post.form', compact('board', 'post', 'members', 'postOptions', 'postMeta', 'postFields', 'postOptionFields'));
    }

    public function update(Request $request, Board $board, int $post): RedirectResponse
    {
        $post = $this->postService->getPost($board, $post);
        $post = $this->postService->updateFromRequest($board, $post, $request);

        return redirect()
            ->route('sitemanager.boards.posts.edit', [$board, $post->id])
            ->with('success', 'Post has been updated successfully.');
    }

    public function destroy(Board $board, int $post): RedirectResponse
    {
        $post = $this->postService->getPost($board, $post);
        $this->postService->deletePost($board, $post);

        return redirect()
            ->route('sitemanager.boards.posts.index', $board)
            ->with('success', 'Post has been deleted successfully.');
    }

    private function members()
    {
        return Member::query()
            ->orderBy('name')
            ->limit(500)
            ->get();
    }
}
