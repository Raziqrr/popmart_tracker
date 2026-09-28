<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Throwaway page for manually testing the browser extension handshake.
Route::get('/extension-test', function () {
    return view('extension-test');
});
