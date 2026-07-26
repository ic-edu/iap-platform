<?php

use App\Http\Controllers\Api\v1\ApiAuthController;
use App\Http\Controllers\Api\v1\InternalApiController;
use App\Http\Controllers\Api\v1\PublicApiController;
use App\Http\Middleware\ApiAuditLogMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API Routes (Version 1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // 1. Authentication Endpoints
    Route::post('auth/login', [ApiAuthController::class, 'login']);

    // 2. Unauthenticated Public Endpoints
    Route::prefix('public')->group(function () {
        Route::get('verify/{code}', [PublicApiController::class, 'verifyCertificate']);
        Route::get('courses', [PublicApiController::class, 'courses']);
        Route::get('products', [PublicApiController::class, 'products']);
        Route::get('announcements', [PublicApiController::class, 'announcements']);
        Route::get('categories', [PublicApiController::class, 'categories']);
    });

    // 3. Authenticated Internal API Endpoints (Sanctum + Audit Logging)
    Route::middleware(['auth:sanctum', ApiAuditLogMiddleware::class])->group(function () {
        Route::post('auth/logout', [ApiAuthController::class, 'logout']);
        Route::get('auth/me', [ApiAuthController::class, 'me']);

        Route::get('question-banks', [InternalApiController::class, 'questionBanks']);
        Route::get('tests', [InternalApiController::class, 'tests']);
        Route::get('attempts', [InternalApiController::class, 'userAttempts']);
        Route::get('certificates', [InternalApiController::class, 'userCertificates']);
        Route::get('orders', [InternalApiController::class, 'userOrders']);
        Route::get('invoices', [InternalApiController::class, 'userInvoices']);
        Route::get('system/health', [InternalApiController::class, 'systemHealth']);
    });
});
