<?php

use Illuminate\Support\Facades\Route;
use SiteManager\Http\Controllers\BoardController;
use SiteManager\Http\Controllers\CommentController;
use SiteManager\Http\Controllers\EditorController;
use SiteManager\Http\Controllers\EmailVerificationController;
use SiteManager\Http\Controllers\Auth\LoginController;
// use SiteManager\Http\Controllers\Auth\CustomerLoginController; // Deprecated: Use LoginController with fallback instead

// 로그인 관련 라우트
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// 고객 로그인 관련 라우트 (EdmMember) - Deprecated: Use /login with attemptFallbackLogin() instead
// Route::get('/customer/login', [CustomerLoginController::class, 'showLoginForm'])->name('customer.login');
// Route::post('/customer/login', [CustomerLoginController::class, 'login']);
// Route::post('/customer/logout', [CustomerLoginController::class, 'logout'])->name('customer.logout');

// 에디터 이미지 업로드 라우트 (전역)
Route::middleware(['auth'])->group(function () {
    Route::post('/editor/upload-image', [EditorController::class, 'uploadImage'])->name('editor.upload-image');
    Route::get('/editor/images', [EditorController::class, 'getImages'])->name('editor.get-images');
    Route::delete('/editor/images/{filename}', [EditorController::class, 'deleteImage'])->name('editor.delete-image');
});


// 비회원 댓글 인증 라우트
Route::post('/board/comment/verify-guest', [CommentController::class, 'verifyGuest'])->name('board.comment.verify-guest');

// 이메일 인증 라우트
Route::group(['prefix' => 'board/email', 'as' => 'board.email.'], function () {
    Route::get('/verify/{token}', [EmailVerificationController::class, 'verify'])->name('verify');
    Route::get('/edit-verify/{token}', [EmailVerificationController::class, 'editVerify'])->name('edit-verify');
    Route::post('/resend-verification', [EmailVerificationController::class, 'resendVerification'])->name('resend-verification');
    Route::post('/send-edit-verification', [EmailVerificationController::class, 'sendEditVerification'])->name('send-edit-verification');
    Route::post('/confirm-delete', [EmailVerificationController::class, 'confirmDelete'])->name('confirm-delete');
    Route::post('/setup-password', [EmailVerificationController::class, 'setupPassword'])->name('setup-password');
    Route::get('/setup-complete', [EmailVerificationController::class, 'setupComplete'])->name('setup-complete');
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
        ->where('id', '[\w\-\p{Hangul}]+');
    Route::post('/{slug}/{id}/verify-password', [BoardController::class, 'verifyPassword'])
        ->name('verify-password')
        ->where('slug', '[a-z0-9_]+')
        ->where('id', '[\w\-\p{Hangul}]+');
    Route::get('/{slug}/{id}/edit', [BoardController::class, 'edit'])
        ->name('edit')
        ->where('slug', '[a-z0-9_]+')
        ->where('id', '[\w\-\p{Hangul}]+');
    Route::put('/{slug}/{id}', [BoardController::class, 'update'])
        ->name('update')
        ->where('slug', '[a-z0-9_]+')
        ->where('id', '[\w\-\p{Hangul}]+');
    Route::delete('/{slug}/{id}', [BoardController::class, 'destroy'])
        ->name('destroy')
        ->where('slug', '[a-z0-9_]+')
        ->where('id', '[\w\-\p{Hangul}]+');
    
    // 좋아요 기능
    Route::post('/{slug}/{id}/like', [BoardController::class, 'toggleLike'])
        ->name('like')
        ->where('slug', '[a-z0-9_]+')
        ->where('id', '[\w\-\p{Hangul}]+');
    
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
    
    // 댓글 관련 추가 라우트 (전역)
    Route::post('/board/comment/verify-guest', [CommentController::class, 'verifyGuest'])
        ->name('board.comment.verify-guest');
    
    // 댓글 라우트
    Route::group([
        'prefix' => '/{slug}/{postId}/comments',
        'where' => ['slug' => '[a-z0-9_]+', 'postId' => '[\w\-\p{Hangul}]+'],
        'as' => 'comments.'
    ], function () {
        Route::get('/', [CommentController::class, 'index'])->name('index');
        Route::post('/', [CommentController::class, 'store'])->name('store');
        Route::get('/{commentId}/reply-form', [CommentController::class, 'getReplyForm'])->name('reply-form');
        Route::get('/{commentId}/edit-form', [CommentController::class, 'getEditForm'])->name('edit-form');
        Route::put('/{commentId}', [CommentController::class, 'update'])->name('update');
        Route::post('/{commentId}', [CommentController::class, 'update'])->name('update.post'); // FormData 지원
        Route::delete('/{commentId}', [CommentController::class, 'destroy'])->name('destroy');
        Route::patch('/{commentId}/approve', [CommentController::class, 'approve'])->name('approve');
        Route::get('/{commentId}/attachments/{attachmentId}', [CommentController::class, 'downloadAttachment'])->name('attachment.download');
        Route::delete('/{commentId}/attachments/{attachmentId}', [CommentController::class, 'deleteAttachment'])->name('attachment.delete');
    });
});
