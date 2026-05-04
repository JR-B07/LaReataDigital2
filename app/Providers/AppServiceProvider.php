<?php

namespace App\Providers;

use App\Events\PaymentReceived;
use App\Listeners\SendTicketsAfterPayment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar listeners de eventos
        Event::listen(
            PaymentReceived::class,
            SendTicketsAfterPayment::class,
        );
    }
}
