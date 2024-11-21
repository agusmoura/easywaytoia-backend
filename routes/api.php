<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

use App\Http\Middleware\JwtMiddleware;
use App\Http\Controllers\Api\PaymentController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas
Route::group(['middleware' => [JwtMiddleware::class]], function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('test', [AuthController::class, 'test']);
});

Route::middleware('auth:api')->group(function () {
    Route::post('/payments/stripe/create-checkout', [PaymentController::class, 'createStripeCheckout']);
});

Route::post('/webhooks/stripe', [PaymentController::class, 'handleStripeWebhook']);

