<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SquadWebhookController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store']);
});

Route::post('/payments/webhook', SquadWebhookController::class)->name('payments.webhook');
Route::get('/payments/callback', [PaymentController::class, 'callback'])->name('payments.callback');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/verifications/create', [VerificationController::class, 'create'])->name('verifications.create');
    Route::post('/verifications', [VerificationController::class, 'store'])->name('verifications.store');
    Route::get('/verifications/{verification}', [VerificationController::class, 'show'])->name('verifications.show');
    Route::get('/verifications/{verification}/asset/{type}', [VerificationController::class, 'asset'])->name('verifications.asset');

    Route::get('/verifications/{verification}/payment', [PaymentController::class, 'initiate'])->name('payments.initiate');
    Route::get('/payments/{payment}/mock-complete', [PaymentController::class, 'mock'])->name('payments.mock');
});
