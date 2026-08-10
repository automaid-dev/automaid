<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\UserMenuItem;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

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
        Event::listen('App\Events\RiderPendingAcceptance', 'App\Listeners\RiderPendingAcceptance');
        Event::listen('App\Events\RiderPendingAcceptance', 'App\Listeners\Telegram\RiderPendingAcceptance');

        Event::listen('App\Events\RiderPickupWashOutlet', 'App\Listeners\RiderPickupWashOutlet');
        Event::listen('App\Events\RiderPickupWashOutlet', 'App\Listeners\Telegram\RiderPickupWashOutlet');

        Event::listen('App\Events\MerchantDeliveryWashOutlet', 'App\Listeners\MerchantDeliveryWashOutlet');
        Event::listen('App\Events\MerchantDeliveryWashOutlet', 'App\Listeners\Telegram\MerchantDeliveryWashOutlet');

        Event::listen('App\Events\MerchantOrderDelivered', 'App\Listeners\MerchantOrderDelivered');
        Event::listen('App\Events\MerchantOrderDelivered', 'App\Listeners\Telegram\MerchantOrderDelivered');

        Event::listen('App\Events\MerchantPendingAcceptance', 'App\Listeners\MerchantPendingAcceptance');
        Event::listen('App\Events\MerchantPendingAcceptance', 'App\Listeners\Telegram\MerchantPendingAcceptance');

        Event::listen('App\Events\CustomerDeliveryToCustomer', 'App\Listeners\CustomerDeliveryToCustomer');
        Event::listen('App\Events\CustomerDeliveryToCustomer', 'App\Listeners\Telegram\CustomerDeliveryToCustomer');

        Event::listen('App\Events\CustomerDeliveryWashOutlet', 'App\Listeners\CustomerDeliveryWashOutlet');
        Event::listen('App\Events\CustomerDeliveryWashOutlet', 'App\Listeners\Telegram\CustomerDeliveryWashOutlet');

        Event::listen('App\Events\CustomerNewAnnouncement', 'App\Listeners\CustomerNewAnnouncement');
        // Event::listen('App\Events\CustomerNewAnnouncement', 'App\Listeners\Telegram\CustomerNewAnnouncement');
        
        Event::listen('App\Events\CustomerNewSupportTicket', 'App\Listeners\CustomerNewSupportTicket');
        Event::listen('App\Events\CustomerNewSupportTicket', 'App\Listeners\Telegram\CustomerNewSupportTicket');

        Event::listen('App\Events\CustomerOrderDelivered', 'App\Listeners\CustomerOrderDelivered');
        Event::listen('App\Events\CustomerOrderDelivered', 'App\Listeners\Telegram\CustomerOrderDelivered');

        Event::listen('App\Events\CustomerReadyPickup', 'App\Listeners\CustomerReadyPickup');
        Event::listen('App\Events\CustomerReadyPickup', 'App\Listeners\Telegram\CustomerReadyPickup');

        Event::listen('App\Events\CustomerWashInProgress', 'App\Listeners\CustomerWashInProgress');
        Event::listen('App\Events\CustomerWashInProgress', 'App\Listeners\Telegram\CustomerWashInProgress');

        Event::listen('App\Events\CustomerAdminCancelOrder', 'App\Listeners\CustomerAdminCancelOrder');
        Event::listen('App\Events\RiderAdminCancelOrder', 'App\Listeners\RiderAdminCancelOrder');
        Event::listen('App\Events\MerchantAdminCancelOrder', 'App\Listeners\MerchantAdminCancelOrder');


        // Always force HTTPS for generated URLs (route(), url(), etc.) —
        // this used to be conditional on `!app()->environment('local')`,
        // which meant it silently did nothing if APP_ENV was ever left as
        // 'local' on a live server (as it currently is here — see the
        // 'local.ERROR' prefix in storage/logs/laravel.log). That's why
        // the Fiuu payment return/notification URLs were generating as
        // http:// instead of https://, which Android's WebView correctly
        // refuses to load (net::ERR_CLEARTEXT_NOT_PERMITTED). Forcing
        // this unconditionally removes the dependency on APP_ENV being
        // set correctly — but APP_ENV should still be fixed to
        // 'production' in .env regardless, since running a live server
        // as 'local' also typically means APP_DEBUG is on, which can
        // leak stack traces and sensitive data in error responses.
        URL::forceScheme('https');
    }
}
