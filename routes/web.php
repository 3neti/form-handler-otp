<?php

use Illuminate\Support\Facades\Route;
use LBHurtado\FormHandlerOtp\Http\Controllers\OtpChallengeController;

Route::middleware(['web', 'throttle:10,1'])
    ->prefix('form-flow/{flowId}/step/{step}/otp-challenge')
    ->group(function (): void {
        Route::post('/', [OtpChallengeController::class, 'store'])
            ->name('form-flow.otp-challenge.store');
        Route::post('/resend', [OtpChallengeController::class, 'resend'])
            ->name('form-flow.otp-challenge.resend');
    });
