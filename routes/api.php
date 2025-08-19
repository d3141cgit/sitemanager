<?php

use Illuminate\Support\Facades\Route;
use SiteManager\Http\Controllers\Admin\AdminMemberController;

// API 라우트 (AJAX 요청용)
Route::middleware(['auth'])->prefix('api/sitemanager')->name('sitemanager.api.')->group(function () {
    Route::middleware('admin')->group(function () {
        Route::patch('/members/{member}/status', [AdminMemberController::class, 'toggleStatus'])->name('members.toggle-status');
    });
});
