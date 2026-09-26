<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\ContentController as Content;
use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\WorkspaceController as Workspace;
use App\Http\Middleware\MobileAssetToken;
use App\Http\Middleware\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Stateful JSON API: web middleware provides sessions and CSRF protection.
Route::prefix('api')->group(function () {
    Route::get('/session', fn (Request $r) => response()->json(['user' => $r->user()?->profilePayload()]));
    Route::post('/login', [Workspace::class, 'login'])->middleware('throttle:5,1')->name('login');
    Route::post('/mobile/login', [MobileAuthController::class, 'login'])->middleware('throttle:5,1');
    // Resolve short-lived native asset links before Sanctum authenticates the
    // request; normal API calls continue using their bearer header/session.
    Route::middleware(MobileAssetToken::class)->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/mobile/session', [MobileAuthController::class, 'session']);
        Route::get('/mobile/asset-token', [MobileAuthController::class, 'assetToken']);
        Route::post('/logout', [Workspace::class, 'logout']);
        Route::post('/two-factor/setup', [TwoFactorController::class, 'setup'])->middleware('throttle:5,1');
        Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1');
        Route::post('/two-factor/disable', [TwoFactorController::class, 'disable'])->middleware('throttle:5,1');
        Route::get('/workspaces', [AdministrationController::class, 'spaces']);
        Route::post('/workspaces', [AdministrationController::class, 'saveSpace']);
        Route::put('/workspaces/{id}', [AdministrationController::class, 'saveSpace']);
        Route::get('/users', [AdministrationController::class, 'users']);
        Route::post('/users', [AdministrationController::class, 'saveUser']);
        Route::put('/users/{user}', [AdministrationController::class, 'saveUser']);
        Route::get('/users/{user}/photo', [AdministrationController::class, 'profilePhoto']);
        Route::post('/users/{user}/photo', [AdministrationController::class, 'saveProfilePhoto'])->middleware('throttle:30,1');
        Route::delete('/users/{user}/photo', [AdministrationController::class, 'deleteProfilePhoto']);
        Route::middleware(WorkspaceAccess::class)->group(function () {
            Route::get('/catalog', [Content::class, 'catalog']);
            Route::post('/catalog/{kind}', [Content::class, 'saveCatalog']);
            Route::put('/catalog/{kind}/{id}', [Content::class, 'saveCatalog']);
            Route::delete('/catalog/{kind}/{id}', [Content::class, 'deleteCatalog']);
            Route::get('/entries', [Content::class, 'index']);
            Route::get('/members', [Content::class, 'members']);
            Route::get('/notification-contacts', [AdministrationController::class, 'notificationContacts']);
            Route::post('/notification-contacts', [AdministrationController::class, 'saveNotificationContact']);
            Route::put('/notification-contacts/{id}', [AdministrationController::class, 'saveNotificationContact']);
            Route::delete('/notification-contacts/{id}', [AdministrationController::class, 'deleteNotificationContact']);
            Route::post('/entries', [Content::class, 'save']);
            Route::get('/entries/{entry}', [Content::class, 'show']);
            Route::put('/entries/{entry}', [Content::class, 'save']);
            Route::delete('/entries/{entry}', [Content::class, 'destroy']);
            Route::post('/media', [Content::class, 'upload'])->middleware('throttle:60,1');
            Route::get('/media/{media}', [Content::class, 'media']);
            Route::get('/history', [Content::class, 'history']);
            Route::patch('/tasks/{task}/move', [Workspace::class, 'move']);

            Route::get('/tasks', [Workspace::class, 'index']);
            Route::post('/tasks', [Workspace::class, 'store']);
            Route::get('/tasks/{task}', [Workspace::class, 'edit']);
            Route::put('/tasks/{task}', [Workspace::class, 'update']);
            Route::patch('/tasks/{task}/status', [Workspace::class, 'status']);
            Route::patch('/tasks/{task}/checklist', [Workspace::class, 'checklist']);
            Route::delete('/tasks/{task}', [Workspace::class, 'destroy']);
            Route::get('/attachments/{attachment}', [Workspace::class, 'download']);
            Route::delete('/attachments/{attachment}', [Workspace::class, 'deleteAttachment']);
        });
    });
    });
});
Route::get('/', fn () => redirect('/app/'));
Route::get('/app/{path?}', fn () => response()->file(public_path('app/index.html')))->where('path', '.*');
