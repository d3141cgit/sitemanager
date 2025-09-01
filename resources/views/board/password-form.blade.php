@extends($layoutPath ?? 'sitemanager::layouts.app')

@section('title', '비밀글 - ' . $post->title)

@push('head')
    <meta name="robots" content="noindex,nofollow">
    {!! resource('sitemanager::js/board/password-form.js') !!}
@endpush

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
        <div class="text-center mb-6">
            <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <h2 class="text-xl font-semibold text-gray-900 mb-2">🔒 비밀글</h2>
            <p class="text-gray-600">이 게시글을 보려면 비밀번호를 입력해주세요.</p>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <h3 class="font-medium text-gray-900 truncate">{{ $post->title }}</h3>
            <p class="text-sm text-gray-500 mt-1">
                작성자: {{ $post->author_name ?: $post->member?->name ?: '익명' }} | 
                작성일: {{ $post->created_at->format('Y-m-d H:i') }}
            </p>
        </div>

        <form id="passwordForm" action="{{ route('board.verify-password', [$board->slug, $post->slug ?: $post->id]) }}" method="POST">
            @csrf
            
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                    비밀번호
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="비밀번호를 입력하세요"
                    required
                    autofocus
                >
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-3">
                <button 
                    type="submit" 
                    class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200"
                >
                    확인
                </button>
                <a 
                    href="{{ route('board.index', $board->slug) }}" 
                    class="flex-1 bg-gray-200 text-gray-800 py-2 px-4 rounded-md text-center hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition duration-200"
                >
                    목록으로
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
