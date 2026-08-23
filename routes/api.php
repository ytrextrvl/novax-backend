<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlightsController;
use App\Http\Controllers\RequestsController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\AgenciesController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\TravelProvidersController;
use App\Http\Controllers\Admin\UsersController as AdminUsersController;
use App\Http\Controllers\Admin\AuditController as AdminAuditController;
use App\Http\Controllers\HealthController;

// Health & Readiness Endpoints
Route::get('/health', [HealthController::class, 'health']);
Route::get('/ready', [HealthController::class, 'ready']);
Route::get('/version', [HealthController::class, 'version']);

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware(['jwt.auth']);
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware(['jwt.refresh']);
    Route::get('/me', [AuthController::class, 'me'])->middleware(['jwt.auth']);

    Route::post('/mfa/enable', [AuthController::class, 'mfaEnable'])->middleware(['jwt.auth']);
    Route::post('/mfa/verify', [AuthController::class, 'mfaVerify'])->middleware(['jwt.auth','throttle:10,1']);
});

// Compatibility alias for existing admin frontend call
Route::post('/admin/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(['jwt.auth'])->group(function () {
    Route::get('/flights/search', [FlightsController::class, 'search']);
    Route::post('/flights/manual/create', [FlightsController::class, 'manualCreate'])->middleware('role:admin');
    Route::get('/flights/routes', [FlightsController::class, 'routes']);
    Route::post('/flights/ticket/upload', [FlightsController::class, 'ticketUpload'])->middleware('role:admin');

    // Unified booking requests: flight / hotel / car.
    Route::post('/requests/create', [RequestsController::class, 'create']);
    Route::get('/requests/{id}', [RequestsController::class, 'show']);
    Route::post('/requests/{id}/quote', [RequestsController::class, 'quote'])->middleware('role:admin');
    Route::post('/requests/{id}/state/change', [RequestsController::class, 'stateChange'])->middleware('role:admin');
    Route::post('/requests/{id}/payment/verify', [RequestsController::class, 'paymentVerify'])->middleware('role:admin');

    Route::get('/travel/providers/status', [TravelProvidersController::class, 'status'])->middleware('role:admin');

    Route::get('/pricing/rules', [PricingController::class, 'rules'])->middleware('role:admin');
    Route::post('/pricing/rules/create', [PricingController::class, 'createRule'])->middleware('role:admin');
    Route::post('/pricing/rules/apply', [PricingController::class, 'apply']);

    Route::get('/agencies', [AgenciesController::class, 'index'])->middleware('role:admin');
    Route::post('/agencies/create', [AgenciesController::class, 'create'])->middleware('role:admin');
    Route::get('/agencies/{id}/balance', [AgenciesController::class, 'balance'])->middleware('role:admin');
    Route::post('/agencies/{id}/commission', [AgenciesController::class, 'commission'])->middleware('role:admin');

    Route::get('/wallet', [WalletController::class, 'wallet']);
    Route::post('/wallet/credit', [WalletController::class, 'credit'])->middleware('role:admin');
    Route::post('/wallet/debit', [WalletController::class, 'debit'])->middleware('role:admin');
    Route::get('/loyalty', [WalletController::class, 'loyalty']);
});

// Admin API group
Route::prefix('admin')->middleware(['jwt.auth','role:admin'])->group(function () {
    Route::get('/users', [AdminUsersController::class, 'index']);
    Route::post('/users', [AdminUsersController::class, 'create']);
    Route::get('/audit', [AdminAuditController::class, 'index']);
});
