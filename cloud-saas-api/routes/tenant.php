<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\PostController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('api')->group(function () {
    Route::middleware(['auth:sanctum', 'tenant.access'])->group(function () {
        Route::get('/posts', [PostController::class, 'index']);
        Route::get('/posts/{post}', [PostController::class, 'show']);
        Route::post('/posts', [PostController::class, 'store'])->middleware('role:admin');
        Route::put('/posts/{post}', [PostController::class, 'update'])->middleware('role:admin');
        Route::delete('/posts/{post}', [PostController::class, 'destroy'])->middleware('role:admin');
    });
});
