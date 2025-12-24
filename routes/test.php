<?php

use Illuminate\Support\Facades\Route;

/**
 * Test routes for OTP handler (non-production only)
 */
Route::prefix('test/otp-handler')->group(function () {
    Route::get('/', function () {
        return view('otp-handler-test');
    })->name('test.otp-handler');
});
