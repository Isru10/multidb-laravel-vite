<?php

use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Central\TenantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback']);

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});


Route::middleware('auth:sanctum')->group(function () {

    // Super Admin Only Routes
    Route::middleware('superadmin')->group(function () {
        Route::post('/organizations', [TenantController::class, 'store']);
        Route::get('/organizations', [TenantController::class, 'index']);
        Route::get('/organizations/{tenantId}', [TenantController::class, 'show']);
    });

});
