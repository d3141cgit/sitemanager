<?php

use Illuminate\Support\Facades\Route;
use SiteManager\Http\Controllers\BoardController;
use SiteManager\Http\Controllers\CommentController;
use SiteManager\Http\Controllers\EditorController;
use SiteManager\Http\Controllers\Auth\LoginController;

// 로그인 관련 라우트
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// 에디터 이미지 업로드 라우트 (전역)
Route::middleware(['auth'])->group(function () {
    Route::post('/editor/upload-image', [EditorController::class, 'uploadImage'])->name('editor.upload-image');
    Route::get('/editor/images', [EditorController::class, 'getImages'])->name('editor.get-images');
    Route::delete('/editor/images/{filename}', [EditorController::class, 'deleteImage'])->name('editor.delete-image');
});

// 게시판 라우트
Route::prefix('board')->name('board.')->group(function () {
    // 첨부파일 관련 라우트 (더 구체적인 패턴을 먼저 배치)
    Route::delete('/attachments/{attachment_id}', [BoardController::class, 'deleteAttachment'])
        ->name('attachment.delete')
        ->where('attachment_id', '[0-9]+');
    Route::post('/attachments/sort-order', [BoardController::class, 'updateAttachmentSortOrder'])
        ->name('attachment.sort-order');
    
    // 게시판 기본 라우트
    Route::get('/{slug}', [BoardController::class, 'index'])
        ->name('index')
        ->where('slug', '[a-z0-9_]+');
    Route::get('/{slug}/create', [BoardController::class, 'create'])
        ->name('create')
        ->where('slug', '[a-z0-9_]+');
    Route::post('/{slug}', [BoardController::class, 'store'])
        ->name('store')
        ->where('slug', '[a-z0-9_]+');
    
    // 게시글 관련 라우트
    Route::get('/{slug}/{id}', [BoardController::class, 'show'])
        ->name('show')
        ->where('slug', '[a-z0-9_]+')
        ->where('id', '[a-z0-9\-_]+');
    Route::get('/{slug}/{id}/edit', [BoardController::class, 'edit'])
        ->name('edit')
        ->where('slug', '[a-z0-9_]+')
        ->where('id', '[a-z0-9\-_]+');
    Route::put('/{slug}/{id}', [BoardController::class, 'update'])
        ->name('update')
        ->where('slug', '[a-z0-9_]+')
        ->where('id', '[a-z0-9\-_]+');
    Route::delete('/{slug}/{id}', [BoardController::class, 'destroy'])
        ->name('destroy')
        ->where('slug', '[a-z0-9_]+')
        ->where('id', '[a-z0-9\-_]+');
    
    // 파일 다운로드
    Route::get('/{slug}/attachment/{attachmentId}', [BoardController::class, 'downloadFile'])
        ->name('attachment.download')
        ->where('slug', '[a-z0-9_]+');
    
    // API 라우트 (AJAX용)
    Route::post('/{slug}/check-slug', [BoardController::class, 'checkSlug'])
        ->name('check-slug')
        ->where('slug', '[a-z0-9_]+');
    Route::post('/{slug}/generate-slug', [BoardController::class, 'generateSlugFromTitle'])
        ->name('generate-slug')
        ->where('slug', '[a-z0-9_]+');
    Route::post('/generate-excerpt', [BoardController::class, 'generateExcerptFromContent'])
        ->name('generate-excerpt');
    
    // 댓글 라우트
    Route::group([
        'prefix' => '/{slug}/{postId}/comments',
        'where' => ['slug' => '[a-z0-9_]+', 'postId' => '[a-z0-9\-_]+'],
        'as' => 'comments.'
    ], function () {
        Route::get('/', [CommentController::class, 'index'])->name('index');
        Route::post('/', [CommentController::class, 'store'])->name('store');
        Route::put('/{commentId}', [CommentController::class, 'update'])->name('update');
        Route::delete('/{commentId}', [CommentController::class, 'destroy'])->name('destroy');
        Route::patch('/{commentId}/approve', [CommentController::class, 'approve'])->name('approve');
    });
});