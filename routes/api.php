<?php

use App\Http\Controllers\PopmartAccountController;
use App\Http\Controllers\PopMartTaskController;
use App\Http\Controllers\PopNowPredictionController;
use Illuminate\Support\Facades\Route;

Route::post('/popmart-accounts/connect', [PopmartAccountController::class, 'connect']);
Route::post('/popmart-accounts/{popmartAccount}/claim-tasks', [PopMartTaskController::class, 'claim']);
Route::get('/pop-now-sets/{popNowSet}/predictions', [PopNowPredictionController::class, 'forSet']);
Route::get('/products/{product}/box-ranking', [PopNowPredictionController::class, 'forProduct']);
