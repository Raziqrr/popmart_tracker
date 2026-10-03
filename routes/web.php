<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\LuckyPointsController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\PopmartAccountController;
use App\Http\Controllers\PopMartTaskController;
use App\Http\Controllers\PopNowRefreshController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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

    Route::middleware('capability:alerts')->group(function () {
        Route::get('/alerts', [AlertController::class, 'index']);
        Route::post('/alerts/read', [AlertController::class, 'markRead']);
        Route::get('/alert-settings', [AlertController::class, 'settings']);
        Route::put('/alert-settings', [AlertController::class, 'updateSettings']);
    });
});
