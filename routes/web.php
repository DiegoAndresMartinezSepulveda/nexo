<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WorkspaceController as Workspace;
use Illuminate\Http\Request;
use App\Http\Controllers\ContentController as Content;

// Stateful JSON API: web middleware provides sessions and CSRF protection.
Route::prefix('api')->group(function () {
    Route::get('/session', fn (Request $r) => response()->json(['user' => $r->user()?->only('id', 'name', 'email', 'role')]));
    Route::post('/login', [Workspace::class, 'login'])->middleware('throttle:5,1')->name('login');
    Route::middleware('auth')->group(function () {
        Route::post('/logout', [Workspace::class, 'logout']);
        Route::get('/workspaces', [\App\Http\Controllers\AdministrationController::class,'spaces']);
        Route::post('/workspaces', [\App\Http\Controllers\AdministrationController::class,'saveSpace']);
        Route::put('/workspaces/{id}', [\App\Http\Controllers\AdministrationController::class,'saveSpace']);
        Route::get('/users', [\App\Http\Controllers\AdministrationController::class,'users']);
        Route::post('/users', [\App\Http\Controllers\AdministrationController::class,'saveUser']);
        Route::put('/users/{user}', [\App\Http\Controllers\AdministrationController::class,'saveUser']);
        Route::middleware(\App\Http\Middleware\WorkspaceAccess::class)->group(function(){
        Route::get('/catalog', [Content::class, 'catalog']);
        Route::post('/catalog/{kind}', [Content::class, 'saveCatalog']);
        Route::put('/catalog/{kind}/{id}', [Content::class, 'saveCatalog']);
        Route::delete('/catalog/{kind}/{id}', [Content::class, 'deleteCatalog']);
        Route::get('/entries', [Content::class, 'index']);
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
Route::get('/', fn () => redirect('/app/'));
Route::get('/app/{path?}', fn () => response()->file(public_path('app/index.html')))->where('path', '.*');
