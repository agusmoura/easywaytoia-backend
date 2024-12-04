<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

use App\Http\Middleware\JwtMiddleware;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\BundleController;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');

// Checkout endpoint (no auth required)
Route::post('/checkout', [PaymentController::class, 'checkout']);

// Rutas protegidas
Route::group(['middleware' => [JwtMiddleware::class]], function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('test', [AuthController::class, 'test']);
    Route::get('/my-account', [AuthController::class, 'myAccount']);
    Route::get('/payments/create-link', [PaymentController::class, 'createPaymentLink']);


      // Rutas de superadmin
      Route::group(['middleware' => [AdminMiddleware::class]], function () {
        // Rutas de cursos
        Route::post('/courses', [CourseController::class, 'store']);
        Route::put('/courses/{course}', [CourseController::class, 'update']);
        Route::patch('/courses/{course}/price', [CourseController::class, 'updatePrice']);

        // Rutas de bundles
        Route::post('/bundles', [BundleController::class, 'store']);
        Route::put('/bundles/{bundle}', [BundleController::class, 'update']);
        Route::patch('/bundles/{bundle}/price', [BundleController::class, 'updatePrice']);
    });
});

Route::post('/webhooks/stripe', [PaymentController::class, 'handleStripeWebhook']);
Route::post('/webhooks/uala', [PaymentController::class, 'handleUalaWebhook']);

Route::post('/email/verification-notification', [VerificationController::class, 'sendVerificationEmail']);
Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->name('verification.verify')
    ->middleware('signed');

