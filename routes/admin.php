<?php

use Illuminate\Support\Facades\Route;
use SiteManager\Http\Controllers\Admin\AdminController;
use SiteManager\Http\Controllers\Admin\AdminMemberController;
use SiteManager\Http\Controllers\Admin\AdminGroupController;
use SiteManager\Http\Controllers\Admin\AdminBoardController;
use SiteManager\Http\Controllers\MenuController;

// 관리자 라우트
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // 관리자 대시보드
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    // Route::get('/statistics', [AdminController::class, 'statistics'])->name('statistics');
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::post('/settings/process-config', [AdminController::class, 'processConfig'])->name('settings.process-config');
    Route::post('/settings/reset-config', [AdminController::class, 'resetConfig'])->name('settings.reset-config');
    Route::post('/settings/reset-resources', [AdminController::class, 'resetResources'])->name('settings.reset-resources');
    
    // 멤버 관리 (관리자용)
    Route::get('/members/search', [AdminMemberController::class, 'search'])->name('members.search');
    Route::post('/members/{id}/restore', [AdminMemberController::class, 'restore'])->name('members.restore');
    Route::delete('/members/{id}/force-delete', [AdminMemberController::class, 'forceDelete'])->name('members.force-delete');
    Route::patch('/members/{member}/toggle-status', [AdminMemberController::class, 'toggleStatus'])->name('members.toggle-status');
    Route::resource('members', AdminMemberController::class);
    
    // 그룹 관리
    Route::post('/groups/{id}/restore', [AdminGroupController::class, 'restore'])->name('groups.restore');
    Route::delete('/groups/{id}/force-delete', [AdminGroupController::class, 'forceDelete'])->name('groups.force-delete');
    Route::resource('groups', AdminGroupController::class);
    
    // 메뉴 관리
    Route::get('/menus/routes', [MenuController::class, 'getRoutes'])->name('menus.routes');
    Route::get('/menus/section/{section}/parents', [MenuController::class, 'getSectionParents'])->name('menus.section-parents');
    Route::post('/menus/rebuild-tree', [MenuController::class, 'rebuildTree'])->name('menus.rebuild-tree');
    Route::post('/menus/check-board-connection', [MenuController::class, 'checkBoardConnection'])->name('menus.check-board-connection');
    // Route::get('/menus/tree', [MenuController::class, 'treeIndex'])->name('menus.tree');
    Route::post('/menus/move', [MenuController::class, 'moveNode'])->name('menus.move');
    Route::resource('menus', MenuController::class);
    
    // 게시판 관리
    Route::patch('/boards/{board}/toggle-status', [AdminBoardController::class, 'toggleStatus'])->name('boards.toggle-status');
    Route::post('/boards/{board}/regenerate-tables', [AdminBoardController::class, 'regenerateTables'])->name('boards.regenerate-tables');
    Route::resource('boards', AdminBoardController::class);
});