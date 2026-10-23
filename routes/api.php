<?php

use App\Http\Controllers\PopNowPredictionController;
use Illuminate\Support\Facades\Route;

// Public, stateless reads: no login, no session. Anything tied to a user or a
// Pop Mart account lives in routes/web.php under /api, where the login session
// and CSRF protection apply.
Route::get('/pop-now-sets/{popNowSet}/predictions', [PopNowPredictionController::class, 'forSet']);
Route::get('/products/{product}/box-ranking', [PopNowPredictionController::class, 'forProduct']);
