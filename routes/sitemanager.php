<?php

use Illuminate\Support\Facades\Route;
use SiteManager\Http\Controllers\SiteManager\SiteManagerController;
use SiteManager\Http\Controllers\SiteManager\SiteManagerMemberController;
use SiteManager\Http\Controllers\SiteManager\SiteManagerGroupController;
use SiteManager\Http\Controllers\SiteManager\SiteManagerBoardController;
use SiteManager\Http\Controllers\SiteManager\SiteManagerCommentController;
use SiteManager\Http\Controllers\MenuController;

// 사이트매니저 라우트
Route::middleware(['auth', 'sitemanager'])->prefix('sitemanager')->name('sitemanager.')->group(function () {
    // 사이트매니저 대시보드
    Route::get('/dashboard', [SiteManagerController::class, 'dashboard'])->name('dashboard');
    // Route::get('/statistics', [SiteManagerController::class, 'statistics'])->name('statistics');
    Route::get('/settings', [SiteManagerController::class, 'settings'])->name('settings');
    Route::post('/settings/process-config', [SiteManagerController::class, 'processConfig'])->name('settings.process-config');
    Route::post('/settings/reset-config', [SiteManagerController::class, 'resetConfig'])->name('settings.reset-config');
    Route::post('/settings/reset-resources', [SiteManagerController::class, 'resetResources'])->name('settings.reset-resources');
    
    // 멤버 관리 (사이트매니저용)
    Route::get('/members/search', [SiteManagerMemberController::class, 'search'])->name('members.search');
    Route::post('/members/{id}/restore', [SiteManagerMemberController::class, 'restore'])->name('members.restore');
    Route::delete('/members/{id}/force-delete', [SiteManagerMemberController::class, 'forceDelete'])->name('members.force-delete');
    Route::patch('/members/{member}/toggle-status', [SiteManagerMemberController::class, 'toggleStatus'])->name('members.toggle-status');
    Route::resource('members', SiteManagerMemberController::class);
    
    // 그룹 관리
    Route::post('/groups/{id}/restore', [SiteManagerGroupController::class, 'restore'])->name('groups.restore');
    Route::delete('/groups/{id}/force-delete', [SiteManagerGroupController::class, 'forceDelete'])->name('groups.force-delete');
    Route::resource('groups', SiteManagerGroupController::class);
    
    // 메뉴 관리
    Route::get('/menus/routes', [MenuController::class, 'getRoutes'])->name('menus.routes');
    Route::get('/menus/section/{section}/parents', [MenuController::class, 'getSectionParents'])->name('menus.section-parents');
    Route::post('/menus/rebuild-tree', [MenuController::class, 'rebuildTree'])->name('menus.rebuild-tree');
    Route::post('/menus/check-board-connection', [MenuController::class, 'checkBoardConnection'])->name('menus.check-board-connection');
    // Route::get('/menus/tree', [MenuController::class, 'treeIndex'])->name('menus.tree');
    Route::post('/menus/move', [MenuController::class, 'moveNode'])->name('menus.move');
    Route::resource('menus', MenuController::class);
    
    // 게시판 관리
    Route::patch('/boards/{board}/toggle-status', [SiteManagerBoardController::class, 'toggleStatus'])->name('boards.toggle-status');
    Route::post('/boards/{board}/regenerate-tables', [SiteManagerBoardController::class, 'regenerateTables'])->name('boards.regenerate-tables');
    Route::resource('boards', SiteManagerBoardController::class);
    
    // 댓글 관리
    Route::prefix('comments')->name('comments.')->group(function () {
        Route::get('/', [SiteManagerCommentController::class, 'index'])->name('index');
        Route::post('/approve', [SiteManagerCommentController::class, 'approve'])->name('approve');
        Route::post('/delete', [SiteManagerCommentController::class, 'delete'])->name('delete');
        Route::post('/restore', [SiteManagerCommentController::class, 'restore'])->name('restore');
        Route::post('/force-delete', [SiteManagerCommentController::class, 'forceDelete'])->name('force-delete');
        Route::post('/bulk', [SiteManagerCommentController::class, 'bulkAction'])->name('bulk');
    });
});