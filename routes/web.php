<?php

use Illuminate\Support\Facades\Route;
use SiteManager\Http\Controllers\BoardController;
use SiteManager\Http\Controllers\CommentController;
use SiteManager\Http\Controllers\EditorController;
use SiteManager\Http\Controllers\MenuController;
use SiteManager\Http\Controllers\Auth\LoginController;
use SiteManager\Http\Controllers\User\UserController;

// 홈페이지
Route::get('/', function () {
    return view('sitemanager::main');
})->name('sitemanager.home');

// 로그인 관련 라우트
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('sitemanager.login');
Route::post('/login', [LoginController::class, 'login'])->name('sitemanager.login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('sitemanager.logout');

// 에디터 이미지 업로드 라우트 (전역)
Route::middleware(['auth'])->group(function () {
    Route::post('/editor/upload-image', [EditorController::class, 'uploadImage'])->name('sitemanager.editor.upload-image');
    Route::get('/editor/images', [EditorController::class, 'getImages'])->name('sitemanager.editor.get-images');
    Route::delete('/editor/images/{filename}', [EditorController::class, 'deleteImage'])->name('sitemanager.editor.delete-image');
});

// 일반 사용자 라우트
Route::middleware(['auth'])->prefix('user')->name('sitemanager.user.')->group(function () {
    Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');
    Route::get('/profile', [UserController::class, 'profile'])->name('profile');
    Route::get('/profile/edit', [UserController::class, 'editProfile'])->name('profile.edit');
    Route::put('/profile', [UserController::class, 'updateProfile'])->name('profile.update');
    
    Route::get('/password/change', [UserController::class, 'changePasswordForm'])->name('password.change');
    Route::put('/password', [UserController::class, 'changePassword'])->name('password.update');
    
    Route::get('/groups', [UserController::class, 'myGroups'])->name('groups');
    
    Route::get('/delete-account', [UserController::class, 'deleteAccountForm'])->name('delete-account');
    Route::delete('/delete-account', [UserController::class, 'deleteAccount'])->name('delete-account.confirm');
});

// 게시판 라우트
Route::prefix('board')->name('sitemanager.board.')->group(function () {
    Route::get('/{slug}', [BoardController::class, 'index'])->name('index')->where('slug', '[a-z0-9_]+');
    Route::get('/{slug}/create', [BoardController::class, 'create'])->name('create')->where('slug', '[a-z0-9_]+');
    Route::post('/{slug}', [BoardController::class, 'store'])->name('store')->where('slug', '[a-z0-9_]+');
    Route::get('/{slug}/{id}', [BoardController::class, 'show'])->name('show')->where('slug', '[a-z0-9_]+');
    Route::get('/{slug}/{id}/edit', [BoardController::class, 'edit'])->name('edit')->where('slug', '[a-z0-9_]+');
    Route::put('/{slug}/{id}', [BoardController::class, 'update'])->name('update')->where('slug', '[a-z0-9_]+');
    Route::delete('/{slug}/{id}', [BoardController::class, 'destroy'])->name('destroy')->where('slug', '[a-z0-9_]+');
    
    // 파일 다운로드
    Route::get('/{slug}/attachment/{attachmentId}', [BoardController::class, 'downloadFile'])->name('attachment.download')->where('slug', '[a-z0-9_]+');
    
    // 첨부파일 삭제
    Route::delete('/attachments/{attachmentId}', [BoardController::class, 'deleteAttachment'])->name('attachment.delete');
    
    // 첨부파일 순서 업데이트
    Route::post('/attachments/sort-order', [BoardController::class, 'updateAttachmentSortOrder'])->name('attachment.sort-order');
    
    // 댓글 라우트
    Route::group([
        'prefix' => '/{slug}/{postId}/comments',
        'where' => ['slug' => '[a-z0-9_]+'],
        'as' => 'comments.'
    ], function () {
        Route::get('/', [CommentController::class, 'index'])->name('index');
        Route::post('/', [CommentController::class, 'store'])->name('store');
        Route::put('/{commentId}', [CommentController::class, 'update'])->name('update');
        Route::delete('/{commentId}', [CommentController::class, 'destroy'])->name('destroy');
        Route::patch('/{commentId}/approve', [CommentController::class, 'approve'])->name('approve');
        Route::patch('/{commentId}/reject', [CommentController::class, 'reject'])->name('reject');
    });
});
