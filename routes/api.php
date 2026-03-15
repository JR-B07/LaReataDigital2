<?php

use App\Http\Controllers\Api\Admin\EventController as AdminEventController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarPosController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\PublicEventController;
use App\Http\Controllers\Api\TaquillaPosController;
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
        Route::post('/bar/products/{product}/movement', [BarPosController::class, 'manualMovement']);
        Route::get('/bar/stock-movements', [BarPosController::class, 'stockMovements']);
        Route::get('/bar/stock-alerts', [BarPosController::class, 'stockAlerts']);
        Route::get('/bar/cuts/current', [BarPosController::class, 'currentCut']);
        Route::post('/bar/cuts/open', [BarPosController::class, 'openCut']);
        Route::post('/bar/cuts/close', [BarPosController::class, 'closeCut']);
        Route::get('/bar/cuts/history', [BarPosController::class, 'cutHistory']);
        Route::get('/bar/cuts/global-summary', [BarPosController::class, 'globalCutSummary']);
        Route::post('/bar/sales', [BarPosController::class, 'storeSale']);
        Route::get('/bar/sales/recent', [BarPosController::class, 'recentSales']);
        Route::post('/bar/sales/{sale}/refund', [BarPosController::class, 'refundSale']);
        Route::get('/bar/refunds', [BarPosController::class, 'refundHistory']);

        Route::get('/bar/promotions', [BarPosController::class, 'promotions']);
        Route::post('/bar/promotions', [BarPosController::class, 'storePromotion']);
        Route::put('/bar/promotions/{promotion}', [BarPosController::class, 'updatePromotion']);
        Route::delete('/bar/promotions/{promotion}', [BarPosController::class, 'destroyPromotion']);

        Route::get('/bar/dashboard', [BarPosController::class, 'liveDashboard']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        Route::get('/bar/reports/sales-by-product', [BarPosController::class, 'reportSalesByProduct']);
        Route::get('/bar/reports/sales-by-payment', [BarPosController::class, 'reportSalesByPayment']);
        Route::get('/bar/reports/sales-by-operator', [BarPosController::class, 'reportSalesByOperator']);
        Route::get('/bar/reports/revenue-by-event', [BarPosController::class, 'reportRevenueByEvent']);
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
        Route::get('/bar/promotions/active', [BarPosController::class, 'activePromotions']);
        Route::get('/bar/stock-alerts', [BarPosController::class, 'stockAlerts']);

        Route::get('/taquilla/events', [TaquillaPosController::class, 'availableEvents']);
        Route::get('/taquilla/zone-availability', [TaquillaPosController::class, 'zoneAvailability']);
        Route::post('/taquilla/sell', [TaquillaPosController::class, 'sellTickets']);
        Route::get('/taquilla/sales/recent', [TaquillaPosController::class, 'recentSales']);
        Route::post('/taquilla/sales/{sale}/cancel', [TaquillaPosController::class, 'cancelSale']);
    });
});
