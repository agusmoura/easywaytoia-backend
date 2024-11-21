<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

use App\Http\Middleware\JwtMiddleware;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\VerificationController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas
Route::group(['middleware' => [JwtMiddleware::class]], function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('test', [AuthController::class, 'test']);
    Route::get('/payments/create-link', [PaymentController::class, 'createPaymentLink']);
   
});

Route::post('/webhooks/stripe', [PaymentController::class, 'handleStripeWebhook']);
Route::post('/email/verification-notification', [VerificationController::class, 'sendVerificationEmail']);
Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->name('verification.verify')
    ->middleware('signed');

