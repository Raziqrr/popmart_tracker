<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AutoLockController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\LuckyPointsController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PopmartAccountController;
use App\Http\Controllers\PopMartTaskController;
use App\Http\Controllers\PopNowRefreshController;
use Illuminate\Support\Facades\Route;

// Inertia pages (resources/js/Pages). Login and register pages come from
// Fortify (views registered in AppServiceProvider).
Route::get('/', [PageController::class, 'dashboard'])->name('dashboard');
Route::get('/catalog', [PageController::class, 'catalog'])->name('catalog');
Route::get('/drops', [PageController::class, 'drops'])->name('drops');
Route::get('/pop-now', [PageController::class, 'popNow'])->name('pop-now');
Route::middleware('auth')->group(function () {
    Route::get('/watchlist', [PageController::class, 'watchlist'])->name('watchlist');
    Route::get('/alerts', [PageController::class, 'alerts'])->name('alerts');
    Route::get('/auto-lock', [PageController::class, 'autoLock'])->name('auto-lock');
});

// Throwaway page for manually testing the browser extension handshake.
Route::get('/extension-test', function () {
    return view('extension-test');
});

// Account API: JSON, but on the web stack so it shares the Fortify login
// session and CSRF protection. Every route except /me needs a capability
// (app/Auth/Capability.php); tests/Feature/CapabilityTest.php fails if one
// is added without.
Route::prefix('api')->group(function () {
    Route::get('/me', MeController::class);

    Route::post('/popmart-accounts/connect', [PopmartAccountController::class, 'connect'])
        ->middleware('capability:connect_popmart');

    Route::post('/popmart-accounts/{popmartAccount}/claim-tasks', [PopMartTaskController::class, 'claim'])
        ->middleware('capability:claim_tasks');

    Route::post('/pop-now-sets/{popNowSet}/refresh', PopNowRefreshController::class)
        ->middleware('capability:refresh_popnow');

    Route::get('/lucky-points', [LuckyPointsController::class, 'show'])
        ->middleware('capability:lucky_points');

    Route::post('/popmart-accounts/{popmartAccount}/redeem-points', [LuckyPointsController::class, 'redeem'])
        ->middleware('capability:redeem_points');

    Route::middleware('capability:watchlist')->group(function () {
        Route::post('/pins', [ListController::class, 'togglePin']);
        Route::post('/watches', [ListController::class, 'toggleWatch']);
    });

    Route::middleware('capability:auto_lock')->group(function () {
        Route::get('/auto-lock', [AutoLockController::class, 'index']);
        Route::post('/auto-lock', [AutoLockController::class, 'store']);
        Route::patch('/auto-lock/{rule}', [AutoLockController::class, 'update']);
        Route::delete('/auto-lock/{rule}', [AutoLockController::class, 'destroy']);
    });

    Route::middleware('capability:alerts')->group(function () {
        Route::get('/alerts', [AlertController::class, 'index']);
        Route::post('/alerts/read', [AlertController::class, 'markRead']);
        Route::get('/alert-settings', [AlertController::class, 'settings']);
        Route::put('/alert-settings', [AlertController::class, 'updateSettings']);
    });
});
