<?php

use App\Http\Controllers\MeController;
use App\Http\Controllers\PopmartAccountController;
use App\Http\Controllers\PopMartTaskController;
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
});
