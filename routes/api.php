<?php

use App\Http\Controllers\Api\Admin\EventController as AdminEventController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarPosController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\PublicEventController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\ValidatorController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::get('/events', [PublicEventController::class, 'index']);
Route::get('/events/{event}', [PublicEventController::class, 'show']);
Route::get('/tickets/{code}', [TicketController::class, 'show']);
Route::get('/tickets/{code}/pdf', [TicketController::class, 'downloadPdf']);

Route::post('/checkout', [CheckoutController::class, 'store']);
Route::post('/checkout/mercadopago/preference', [CheckoutController::class, 'createMercadoPagoPreference']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders/history', [CheckoutController::class, 'history']);

    Route::prefix('validator')->middleware('role:validator,admin')->group(function () {
        Route::post('/scan', [ValidatorController::class, 'scan']);
        Route::get('/scans', [ValidatorController::class, 'scans']);
    });

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/events', [AdminEventController::class, 'index']);
        Route::post('/events', [AdminEventController::class, 'store']);
        Route::get('/events/{event}', [AdminEventController::class, 'show']);
        Route::put('/events/{event}', [AdminEventController::class, 'update']);
        Route::delete('/events/{event}', [AdminEventController::class, 'destroy']);
        Route::post('/events/{event}/publish', [AdminEventController::class, 'publish']);
        Route::post('/events/{event}/cancel', [AdminEventController::class, 'cancel']);
        Route::post('/events/{event}/validators', [AdminEventController::class, 'assignValidator']);
        Route::delete('/events/{event}/validators', [AdminEventController::class, 'unassignValidator']);

        Route::get('/reports/summary', [ReportController::class, 'summary']);
        Route::get('/reports/sales-by-zone', [ReportController::class, 'salesByZone']);

        Route::get('/bar/products', [BarPosController::class, 'products']);
        Route::post('/bar/products', [BarPosController::class, 'storeProduct']);
        Route::put('/bar/products/{product}', [BarPosController::class, 'updateProduct']);
        Route::delete('/bar/products/{product}', [BarPosController::class, 'destroyProduct']);
        Route::patch('/bar/products/{product}/stock', [BarPosController::class, 'updateStock']);
        Route::get('/bar/cuts/current', [BarPosController::class, 'currentCut']);
        Route::post('/bar/cuts/open', [BarPosController::class, 'openCut']);
        Route::post('/bar/cuts/close', [BarPosController::class, 'closeCut']);
        Route::get('/bar/cuts/history', [BarPosController::class, 'cutHistory']);
        Route::get('/bar/cuts/global-summary', [BarPosController::class, 'globalCutSummary']);
        Route::post('/bar/sales', [BarPosController::class, 'storeSale']);
        Route::get('/bar/sales/recent', [BarPosController::class, 'recentSales']);
    });

    Route::prefix('seller')->middleware('role:seller,admin')->group(function () {
        Route::get('/events', [AdminEventController::class, 'index']);
        Route::get('/events/{event}', [AdminEventController::class, 'show']);

        Route::get('/reports/summary', [ReportController::class, 'summary']);
        Route::get('/reports/sales-by-zone', [ReportController::class, 'salesByZone']);

        Route::get('/bar/products', [BarPosController::class, 'products']);
        Route::get('/bar/cuts/current', [BarPosController::class, 'currentCut']);
        Route::post('/bar/cuts/open', [BarPosController::class, 'openCut']);
        Route::post('/bar/cuts/close', [BarPosController::class, 'closeCut']);
        Route::get('/bar/cuts/history', [BarPosController::class, 'cutHistory']);
        Route::post('/bar/sales', [BarPosController::class, 'storeSale']);
        Route::get('/bar/sales/recent', [BarPosController::class, 'recentSales']);
    });
});
